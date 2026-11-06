<?php

declare(strict_types=1);

namespace App\Model;

/**
 * Perfil ampliado de un usuario (relación 1:1 con User).
 *
 * En base de datos, Profile.user_id es UNIQUE: un usuario no puede tener dos perfiles.
 */
final class Profile
{
    public function __construct(
        public readonly int $id,
        public readonly int $userId,
        public readonly ?string $address,
        public readonly ?string $city,
        public readonly ?string $postalCode,
        public readonly ?string $country,
        public readonly ?string $bio,
        public readonly ?string $website,
    ) {
    }

    /** @param array<string, mixed> $row Fila de la tabla Profile. */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['user_id'],
            $row['address'] !== null ? (string) $row['address'] : null,
            $row['city'] !== null ? (string) $row['city'] : null,
            $row['postal_code'] !== null ? (string) $row['postal_code'] : null,
            $row['country'] !== null ? (string) $row['country'] : null,
            $row['bio'] !== null ? (string) $row['bio'] : null,
            $row['website'] !== null ? (string) $row['website'] : null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'address' => $this->address,
            'city' => $this->city,
            'postal_code' => $this->postalCode,
            'country' => $this->country,
            'bio' => $this->bio,
            'website' => $this->website,
        ];
    }
}
