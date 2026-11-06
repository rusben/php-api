<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\ApiException;
use App\Http\Request;
use App\Http\Response;
use App\Model\Post;
use App\Repository\PostRepository;
use App\Repository\UserRepository;
use App\Validation\Validator;

/**
 * Posts (relación 1:N con User).
 *
 * Crear y listar los posts de un usuario cuelga de /users/{id}/posts;
 * leer, modificar y borrar un post concreto se hace sobre /posts/{id}.
 */
final class PostController
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PostRepository $posts,
    ) {
    }

    /** GET /posts (filtro opcional ?user_id=) */
    public function index(Request $request, array $params): Response
    {
        $userId = null;
        $rawUserId = $request->query('user_id');

        if ($rawUserId !== null) {
            if (!is_string($rawUserId) || !ctype_digit($rawUserId)) {
                throw ApiException::badRequest('user_id must be a positive integer');
            }
            $userId = (int) $rawUserId;
        }

        [$page, $perPage] = $request->pagination();

        return $this->listResponse($userId, $page, $perPage);
    }

    /** GET /users/{id}/posts */
    public function indexByUser(Request $request, array $params): Response
    {
        $userId = $params['id'];
        $this->requireUser($userId);

        [$page, $perPage] = $request->pagination();

        return $this->listResponse($userId, $page, $perPage);
    }

    /** POST /users/{id}/posts */
    public function store(Request $request, array $params): Response
    {
        $userId = $params['id'];
        $this->requireUser($userId);

        [$data, $errors] = Validator::post($request->json());

        if ($errors !== []) {
            throw ApiException::unprocessable($errors);
        }

        $id = $this->posts->create($userId, $data);
        $post = $this->findOrFail($id);

        return Response::created($post->toArray(), $request->basePath() . '/posts/' . $id, 'Post created successfully');
    }

    /** GET /posts/{id} */
    public function show(Request $request, array $params): Response
    {
        return Response::ok($this->findOrFail($params['id'])->toArray());
    }

    /** PUT|PATCH /posts/{id} (actualización parcial) */
    public function update(Request $request, array $params): Response
    {
        $id = $params['id'];
        $this->findOrFail($id);

        [$data, $errors] = Validator::post($request->json(), true);

        if ($errors !== []) {
            throw ApiException::unprocessable($errors);
        }

        $this->posts->update($id, $data);

        return Response::ok($this->findOrFail($id)->toArray(), 'Post updated successfully');
    }

    /** DELETE /posts/{id} */
    public function destroy(Request $request, array $params): Response
    {
        $id = $params['id'];
        $this->findOrFail($id);
        $this->posts->delete($id);

        return Response::message('Post deleted successfully');
    }

    private function listResponse(?int $userId, int $page, int $perPage): Response
    {
        $posts = $this->posts->paginate($page, $perPage, $userId);

        return Response::paginated(
            array_map(static fn (Post $post): array => $post->toArray(), $posts),
            $this->posts->count($userId),
            $page,
            $perPage
        );
    }

    private function findOrFail(int $id): Post
    {
        return $this->posts->find($id) ?? throw ApiException::notFound("Post $id not found");
    }

    private function requireUser(int $userId): void
    {
        if (!$this->users->exists($userId)) {
            throw ApiException::notFound("User $userId not found");
        }
    }
}
