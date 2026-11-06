<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Respuesta JSON de la API.
 *
 * Formato de éxito:  {"status": "success", "message"?: "...", "data": ..., "meta"?: {...}}
 * Formato de error:  {"status": "error", "message": "...", "errors"?: {"campo": "motivo"}}
 */
final class Response
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private readonly mixed $body = null,
        private readonly int $status = 200,
        private array $headers = [],
    ) {
    }

    /** @param array<string, mixed> $meta */
    public static function ok(mixed $data, ?string $message = null, array $meta = []): self
    {
        $body = ['status' => 'success'];

        if ($message !== null) {
            $body['message'] = $message;
        }

        $body['data'] = $data;

        if ($meta !== []) {
            $body['meta'] = $meta;
        }

        return new self($body, 200);
    }

    public static function created(mixed $data, string $location, ?string $message = null): self
    {
        $body = ['status' => 'success'];

        if ($message !== null) {
            $body['message'] = $message;
        }

        $body['data'] = $data;

        return new self($body, 201, ['Location' => $location]);
    }

    /** Respuesta de éxito sin datos (por ejemplo, tras un DELETE). */
    public static function message(string $message): self
    {
        return new self(['status' => 'success', 'message' => $message], 200);
    }

    /**
     * @param array<int, mixed> $items
     */
    public static function paginated(array $items, int $total, int $page, int $perPage): self
    {
        return self::ok($items, null, [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => (int) ceil($total / $perPage),
        ]);
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, string> $headers
     */
    public static function error(int $status, string $message, array $errors = [], array $headers = []): self
    {
        $body = ['status' => 'error', 'message' => $message];

        if ($errors !== []) {
            $body['errors'] = $errors;
        }

        return new self($body, $status, $headers);
    }

    /** @param array<string, string> $headers */
    public function withHeaders(array $headers): self
    {
        $clone = clone $this;
        $clone->headers = array_merge($clone->headers, $headers);

        return $clone;
    }

    public function send(): void
    {
        http_response_code($this->status);
        header('Content-Type: application/json; charset=utf-8');

        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        if ($this->body !== null && $this->status !== 204) {
            echo json_encode(
                $this->body,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
        }
    }
}
