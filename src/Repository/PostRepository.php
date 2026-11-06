<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Post;
use PDO;

/**
 * Acceso a datos de la tabla Post (relación N:1 con User).
 */
final class PostRepository
{
    private const COLUMNS = ['title', 'content'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Lista posts paginados, opcionalmente filtrados por autor. Los más recientes primero.
     *
     * @return Post[]
     */
    public function paginate(int $page, int $perPage, ?int $userId = null): array
    {
        $sql = 'SELECT * FROM Post';
        if ($userId !== null) {
            $sql .= ' WHERE user_id = :user_id';
        }
        $sql .= ' ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        if ($userId !== null) {
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(static fn (array $row): Post => Post::fromRow($row), $stmt->fetchAll());
    }

    public function count(?int $userId = null): int
    {
        if ($userId === null) {
            return (int) $this->pdo->query('SELECT COUNT(*) FROM Post')->fetchColumn();
        }

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM Post WHERE user_id = :user_id');
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function find(int $id): ?Post
    {
        $stmt = $this->pdo->prepare('SELECT * FROM Post WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch();

        return $row === false ? null : Post::fromRow($row);
    }

    /**
     * Carga los posts de varios usuarios con una sola consulta (evita el problema N+1).
     *
     * @param  int[] $userIds
     * @return array<int, Post[]> Posts agrupados por user_id.
     */
    public function findByUserIds(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($userIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT * FROM Post WHERE user_id IN ($placeholders) ORDER BY created_at DESC, id DESC"
        );
        $stmt->execute(array_values($userIds));

        $grouped = [];
        foreach ($stmt->fetchAll() as $row) {
            $post = Post::fromRow($row);
            $grouped[$post->userId][] = $post;
        }

        return $grouped;
    }

    /**
     * @param array<string, string> $data Campos ya validados (title, content).
     * @return int ID del post creado.
     */
    public function create(int $userId, array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO Post (user_id, title, content) VALUES (:user_id, :title, :content)'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':title', $data['title']);
        $stmt->bindValue(':content', $data['content']);
        $stmt->execute();

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, string> $data Solo los campos a modificar. */
    public function update(int $id, array $data): void
    {
        $columns = array_values(array_intersect(array_keys($data), self::COLUMNS));

        if ($columns === []) {
            return;
        }

        $set = implode(', ', array_map(static fn (string $c): string => "$c = :$c", $columns));

        $stmt = $this->pdo->prepare("UPDATE Post SET $set WHERE id = :id");
        foreach ($columns as $column) {
            $stmt->bindValue(':' . $column, $data[$column]);
        }
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM Post WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }
}
