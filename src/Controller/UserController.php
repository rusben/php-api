<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\ApiException;
use App\Http\Request;
use App\Http\Response;
use App\Model\User;
use App\Repository\PostRepository;
use App\Repository\ProfileRepository;
use App\Repository\UserRepository;
use App\Validation\Validator;
use PDO;
use Throwable;

/**
 * CRUD de usuarios. Soporta cargar relaciones (?include=profile,posts)
 * y crear un usuario junto con su perfil y sus posts en una sola petición.
 */
final class UserController
{
    private const INCLUDES = ['profile', 'posts'];
    private const MAX_NESTED_POSTS = 50;

    public function __construct(
        private readonly PDO $pdo,
        private readonly UserRepository $users,
        private readonly ProfileRepository $profiles,
        private readonly PostRepository $posts,
    ) {
    }

    /** GET /users */
    public function index(Request $request, array $params): Response
    {
        $includes = $request->includes(self::INCLUDES);
        [$page, $perPage] = $request->pagination();

        $users = $this->users->paginate($page, $perPage);
        $this->loadRelations($users, $includes);

        return Response::paginated(
            array_map(static fn (User $user): array => $user->toArray(), $users),
            $this->users->count(),
            $page,
            $perPage
        );
    }

    /** GET /users/{id} */
    public function show(Request $request, array $params): Response
    {
        $user = $this->findOrFail($params['id']);
        $this->loadRelations([$user], $request->includes(self::INCLUDES));

        return Response::ok($user->toArray());
    }

    /** POST /users */
    public function store(Request $request, array $params): Response
    {
        [$userData, $profileData, $postsData, $errors] = $this->validateCreatePayload($request->json());

        if ($errors !== []) {
            throw ApiException::unprocessable($errors);
        }

        $this->assertUnique($userData);

        // Usuario + perfil + posts: o se guarda todo, o no se guarda nada.
        $this->pdo->beginTransaction();
        try {
            $id = $this->users->create($userData);

            if ($profileData !== null) {
                $this->profiles->save($id, $profileData);
            }

            foreach ($postsData as $postData) {
                $this->posts->create($id, $postData);
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        $user = $this->findOrFail($id);
        $this->loadRelations([$user], self::INCLUDES);

        return Response::created($user->toArray(), $request->basePath() . '/users/' . $id, 'User created successfully');
    }

    /** PUT|PATCH /users/{id} (actualización parcial: solo se modifican los campos enviados) */
    public function update(Request $request, array $params): Response
    {
        $id = $params['id'];
        $this->findOrFail($id);

        [$data, $errors] = Validator::user($request->json(), true);

        if ($errors !== []) {
            throw ApiException::unprocessable($errors);
        }

        $this->assertUnique($data, $id);
        $this->users->update($id, $data);

        return Response::ok($this->findOrFail($id)->toArray(), 'User updated successfully');
    }

    /** DELETE /users/{id} (borra también su perfil y sus posts por ON DELETE CASCADE) */
    public function destroy(Request $request, array $params): Response
    {
        $id = $params['id'];
        $this->findOrFail($id);
        $this->users->delete($id);

        return Response::message('User deleted successfully');
    }

    private function findOrFail(int $id): User
    {
        return $this->users->find($id) ?? throw ApiException::notFound("User $id not found");
    }

    /**
     * Carga las relaciones pedidas con una consulta por relación (no una por usuario).
     *
     * @param User[]   $users
     * @param string[] $includes
     */
    private function loadRelations(array $users, array $includes): void
    {
        if ($users === [] || $includes === []) {
            return;
        }

        $ids = array_map(static fn (User $user): int => $user->id, $users);

        if (in_array('profile', $includes, true)) {
            $profiles = $this->profiles->findByUserIds($ids);
            foreach ($users as $user) {
                $user->setProfile($profiles[$user->id] ?? null);
            }
        }

        if (in_array('posts', $includes, true)) {
            $posts = $this->posts->findByUserIds($ids);
            foreach ($users as $user) {
                $user->setPosts($posts[$user->id] ?? []);
            }
        }
    }

    /**
     * Valida el cuerpo de POST /users: datos del usuario y, opcionalmente, "profile" (objeto) y "posts" (lista).
     *
     * @param  array<string, mixed> $body
     * @return array{0: array<string, string>, 1: array<string, string|null>|null, 2: array<int, array<string, string>>, 3: array<string, string>}
     */
    private function validateCreatePayload(array $body): array
    {
        $rawProfile = $body['profile'] ?? null;
        $rawPosts = $body['posts'] ?? null;
        unset($body['profile'], $body['posts']);

        [$userData, $errors] = Validator::user($body);

        $profileData = null;
        if ($rawProfile !== null) {
            if (!is_array($rawProfile) || ($rawProfile !== [] && array_is_list($rawProfile))) {
                $errors['profile'] = 'Must be an object';
            } else {
                [$profileData, $profileErrors] = Validator::profile($rawProfile);
                $errors = array_merge($errors, Validator::prefix($profileErrors, 'profile.'));
            }
        }

        $postsData = [];
        if ($rawPosts !== null) {
            if (!is_array($rawPosts) || !array_is_list($rawPosts)) {
                $errors['posts'] = 'Must be an array of objects';
            } elseif (count($rawPosts) > self::MAX_NESTED_POSTS) {
                $errors['posts'] = 'At most ' . self::MAX_NESTED_POSTS . ' posts can be created at once';
            } else {
                foreach ($rawPosts as $index => $rawPost) {
                    if (!is_array($rawPost)) {
                        $errors["posts.$index"] = 'Must be an object';
                        continue;
                    }

                    [$postData, $postErrors] = Validator::post($rawPost);
                    $errors = array_merge($errors, Validator::prefix($postErrors, "posts.$index."));
                    $postsData[] = $postData;
                }
            }
        }

        return [$userData, $profileData, $postsData, $errors];
    }

    /**
     * @param array<string, string> $data
     * @throws ApiException 409 si el email o el DNI ya pertenecen a otro usuario.
     */
    private function assertUnique(array $data, ?int $exceptId = null): void
    {
        $conflicts = [];

        foreach (['email', 'dni'] as $field) {
            if (isset($data[$field]) && $this->users->isTaken($field, $data[$field], $exceptId)) {
                $conflicts[$field] = 'Already in use by another user';
            }
        }

        if ($conflicts !== []) {
            throw ApiException::conflict(
                'Duplicate value: ' . implode(', ', array_keys($conflicts)),
                $conflicts
            );
        }
    }
}
