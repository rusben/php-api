<?php

declare(strict_types=1);

namespace App\Model;

/**
 * Usuario del sistema.
 *
 * Relaciones:
 *  - 1:1 con Profile (un usuario tiene como máximo un perfil).
 *  - 1:N con Post    (un usuario puede tener muchos posts).
 *
 * Las relaciones solo se serializan si se han cargado explícitamente
 * (setProfile / setPosts), para no devolver datos que nadie ha pedido.
 */
final class User
{
    private ?Profile $profile = null;
    private bool $profileLoaded = false;

    /** @var Post[]|null */
    private ?array $posts = null;

    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $surname,
        public readonly string $email,
        public readonly string $dni,
        public readonly string $phone,
        public readonly string $born,
    ) {
    }

    /** @param array<string, mixed> $row Fila de la tabla User. */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['name'],
            (string) $row['surname'],
            (string) $row['email'],
            (string) $row['dni'],
            (string) $row['phone'],
            (string) $row['born'],
        );
    }

    /** Relación 1:1. Se admite null: "cargado, pero el usuario no tiene perfil". */
    public function setProfile(?Profile $profile): void
    {
        $this->profile = $profile;
        $this->profileLoaded = true;
    }

    public function getProfile(): ?Profile
    {
        return $this->profile;
    }

    /**
     * Relación 1:N.
     *
     * @param Post[] $posts
     */
    public function setPosts(array $posts): void
    {
        $this->posts = $posts;
    }

    /** @return Post[]|null null si los posts no se han cargado. */
    public function getPosts(): ?array
    {
        return $this->posts;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'surname' => $this->surname,
            'email' => $this->email,
            'dni' => $this->dni,
            'phone' => $this->phone,
            'born' => $this->born,
        ];

        if ($this->profileLoaded) {
            $data['profile'] = $this->profile?->toArray();
        }

        if ($this->posts !== null) {
            $data['posts'] = array_map(static fn (Post $post): array => $post->toArray(), $this->posts);
        }

        return $data;
    }
}
