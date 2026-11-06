<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\ApiException;
use App\Http\Request;
use App\Http\Response;
use App\Repository\ProfileRepository;
use App\Repository\UserRepository;
use App\Validation\Validator;

/**
 * Perfil de un usuario (relación 1:1): /users/{id}/profile
 */
final class ProfileController
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly ProfileRepository $profiles,
    ) {
    }

    /** GET /users/{id}/profile */
    public function show(Request $request, array $params): Response
    {
        $userId = $params['id'];
        $this->requireUser($userId);

        $profile = $this->profiles->findByUserId($userId)
            ?? throw ApiException::notFound("User $userId has no profile");

        return Response::ok($profile->toArray());
    }

    /**
     * PUT|PATCH /users/{id}/profile
     *
     * Crea el perfil si no existe (201) o actualiza los campos enviados si ya existe (200).
     */
    public function upsert(Request $request, array $params): Response
    {
        $userId = $params['id'];
        $this->requireUser($userId);

        [$data, $errors] = Validator::profile($request->json());

        if ($errors !== []) {
            throw ApiException::unprocessable($errors);
        }

        $existed = $this->profiles->findByUserId($userId) !== null;

        $this->profiles->save($userId, $data);

        $profile = $this->profiles->findByUserId($userId)
            ?? throw new \RuntimeException('Profile not found after saving');

        if ($existed) {
            return Response::ok($profile->toArray(), 'Profile updated successfully');
        }

        return Response::created(
            $profile->toArray(),
            $request->basePath() . "/users/$userId/profile",
            'Profile created successfully'
        );
    }

    /** DELETE /users/{id}/profile */
    public function destroy(Request $request, array $params): Response
    {
        $userId = $params['id'];
        $this->requireUser($userId);

        if (!$this->profiles->deleteByUserId($userId)) {
            throw ApiException::notFound("User $userId has no profile");
        }

        return Response::message('Profile deleted successfully');
    }

    private function requireUser(int $userId): void
    {
        if (!$this->users->exists($userId)) {
            throw ApiException::notFound("User $userId not found");
        }
    }
}
