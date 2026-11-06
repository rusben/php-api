<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\User;
use InvalidArgumentException;
use PDO;

/**
 * Acceso a datos de la tabla User. Todas las consultas usan sentencias preparadas.
 */
final class UserRepository
{
    /** Columnas escribibles (lista blanca usada al construir INSERT/UPDATE). */
    private const COLUMNS = ['name', 'surname', 'email', 'dni', 'phone', 'born'];

    /** Columnas con restricción UNIQUE que se pueden consultar con isTaken(). */
    private const UNIQUE_COLUMNS = ['email', 'dni'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return User[] */
    public function paginate(int $page, int $perPage): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM User ORDER BY id LIMIT :limit OFFSET :offset');
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(static fn (array $row): User => User::fromRow($row), $stmt->fetchAll());
    }

    public function count(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM User')->fetchColumn();
    }

    public function find(int $id): ?User
    {
        $stmt = $this->pdo->prepare('SELECT * FROM User WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch();

        return $row === false ? null : User::fromRow($row);
    }

    public function exists(int $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM User WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchColumn() !== false;
    }

    /**
     * @param array<string, string> $data Campos ya validados (name, surname, email, dni, phone, born).
     * @return int ID del usuario creado.
     */
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO User (name, surname, email, dni, phone, born)
             VALUES (:name, :surname, :email, :dni, :phone, :born)'
        );

        foreach (self::COLUMNS as $column) {
            $stmt->bindValue(':' . $column, $data[$column]);
        }

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

        $stmt = $this->pdo->prepare("UPDATE User SET $set WHERE id = :id");
        foreach ($columns as $column) {
            $stmt->bindValue(':' . $column, $data[$column]);
        }
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM User WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    /**
     * Indica si ya existe otro usuario con ese valor en una columna UNIQUE.
     *
     * @param string   $column   'email' o 'dni'
     * @param int|null $exceptId Usuario a ignorar (útil al actualizar).
     */
    public function isTaken(string $column, string $value, ?int $exceptId = null): bool
    {
        if (!in_array($column, self::UNIQUE_COLUMNS, true)) {
            throw new InvalidArgumentException("Column '$column' is not unique-checked");
        }

        $sql = "SELECT 1 FROM User WHERE $column = :value";
        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
        }
        $sql .= ' LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':value', $value);
        if ($exceptId !== null) {
            $stmt->bindValue(':id', $exceptId, PDO::PARAM_INT);
        }
        $stmt->execute();

        return $stmt->fetchColumn() !== false;
    }
}
