<?php

declare(strict_types=1);

namespace App\Http;

use RuntimeException;

/**
 * Error controlado de la API: se traduce en una respuesta JSON con el código HTTP indicado.
 */
class ApiException extends RuntimeException
{
    /**
     * @param array<string, string> $errors  Errores por campo (opcional).
     * @param array<string, string> $headers Cabeceras HTTP adicionales (opcional).
     */
    public function __construct(
        private readonly int $statusCode,
        string $message,
        private readonly array $errors = [],
        private readonly array $headers = [],
    ) {
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /** @return array<string, string> */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /** @return array<string, string> */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public static function badRequest(string $message): self
    {
        return new self(400, $message);
    }

    public static function notFound(string $message): self
    {
        return new self(404, $message);
    }

    /** @param string[] $allowed */
    public static function methodNotAllowed(array $allowed): self
    {
        return new self(405, 'Method not allowed', [], ['Allow' => implode(', ', array_unique($allowed))]);
    }

    /** @param array<string, string> $errors */
    public static function conflict(string $message, array $errors = []): self
    {
        return new self(409, $message, $errors);
    }

    /** @param array<string, string> $errors */
    public static function unprocessable(array $errors, string $message = 'Validation failed'): self
    {
        return new self(422, $message, $errors);
    }
}
