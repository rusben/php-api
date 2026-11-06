<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Profile;
use PDO;

/**
 * Acceso a datos de la tabla Profile (relación 1:1 con User).
 */
final class ProfileRepository
{
    private const COLUMNS = ['address', 'city', 'postal_code', 'country', 'bio', 'website'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findByUserId(int $userId): ?Profile
    {
        $stmt = $this->pdo->prepare('SELECT * FROM Profile WHERE user_id = :user_id');
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch();

        return $row === false ? null : Profile::fromRow($row);
    }

    /**
     * Carga los perfiles de varios usuarios con una sola consulta (evita el problema N+1).
     *
     * @param  int[] $userIds
     * @return array<int, Profile> Perfiles indexados por user_id.
     */
    public function findByUserIds(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($userIds), '?'));
        $stmt = $this->pdo->prepare("SELECT * FROM Profile WHERE user_id IN ($placeholders)");
        $stmt->execute(array_values($userIds));

        $profiles = [];
        foreach ($stmt->fetchAll() as $row) {
            $profile = Profile::fromRow($row);
            $profiles[$profile->userId] = $profile;
        }

        return $profiles;
    }

    /**
     * Crea el perfil si el usuario no tiene uno; si ya lo tiene, actualiza solo los campos recibidos.
     *
     * @param array<string, string|null> $data Campos ya validados.
     */
    public function save(int $userId, array $data): void
    {
        $columns = array_values(array_intersect(array_keys($data), self::COLUMNS));

        if ($this->findByUserId($userId) === null) {
            $insertColumns = array_merge(['user_id'], $columns);
            $placeholders = array_map(static fn (string $c): string => ':' . $c, $insertColumns);

            $stmt = $this->pdo->prepare(sprintf(
                'INSERT INTO Profile (%s) VALUES (%s)',
                implode(', ', $insertColumns),
                implode(', ', $placeholders)
            ));
        } else {
            if ($columns === []) {
                return;
            }

            $set = implode(', ', array_map(static fn (string $c): string => "$c = :$c", $columns));
            $stmt = $this->pdo->prepare("UPDATE Profile SET $set WHERE user_id = :user_id");
        }

        foreach ($columns as $column) {
            $stmt->bindValue(':' . $column, $data[$column]);
        }
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function deleteByUserId(int $userId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM Profile WHERE user_id = :user_id');
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }
}
