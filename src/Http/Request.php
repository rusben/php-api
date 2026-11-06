<?php

declare(strict_types=1);

namespace App\Http;

use JsonException;

/**
 * Representa la petición HTTP entrante.
 */
final class Request
{
    /** @var array<string, mixed>|null */
    private ?array $json = null;

    /** @param array<string, mixed> $query */
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $query,
        private readonly string $rawBody,
        private readonly string $basePath = '',
    ) {
    }

    /**
     * Construye la petición a partir de las superglobales de PHP.
     * $basePath es el prefijo de URL que se elimina antes de enrutar (ej: "/api").
     */
    public static function fromGlobals(string $basePath = ''): self
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $uri = rawurldecode(is_string($uri) ? $uri : '/');

        $basePath = rtrim($basePath, '/');
        if ($basePath !== '' && ($uri === $basePath || str_starts_with($uri, $basePath . '/'))) {
            $uri = substr($uri, strlen($basePath));
        }

        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            '/' . trim($uri, '/'),
            $_GET,
            file_get_contents('php://input') ?: '',
            $basePath,
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    /** Ruta sin el prefijo base ni la query string. Siempre empieza por "/". */
    public function path(): string
    {
        return $this->path;
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /**
     * Devuelve el cuerpo de la petición decodificado como objeto JSON.
     *
     * @return array<string, mixed>
     * @throws ApiException 400 si el cuerpo está vacío, no es JSON válido o no es un objeto.
     */
    public function json(): array
    {
        if ($this->json !== null) {
            return $this->json;
        }

        if (trim($this->rawBody) === '') {
            throw ApiException::badRequest('The request body is empty');
        }

        try {
            $data = json_decode($this->rawBody, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw ApiException::badRequest('Invalid JSON: ' . $e->getMessage());
        }

        if (!is_array($data) || ($data !== [] && array_is_list($data))) {
            throw ApiException::badRequest('The JSON body must be an object');
        }

        return $this->json = $data;
    }

    /**
     * Lee ?page= y ?per_page= con límites razonables.
     *
     * @return array{0: int, 1: int} [page, perPage]
     */
    public function pagination(int $defaultPerPage = 20, int $maxPerPage = 100): array
    {
        $page = max(1, (int) $this->query('page', 1));
        $perPage = (int) $this->query('per_page', $defaultPerPage);
        $perPage = min($maxPerPage, max(1, $perPage));

        return [$page, $perPage];
    }

    /**
     * Lee ?include=a,b y valida que solo se pidan relaciones permitidas.
     *
     * @param  string[] $allowed
     * @return string[]
     * @throws ApiException 400 si se solicita una relación desconocida.
     */
    public function includes(array $allowed): array
    {
        $raw = $this->query('include', '');

        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $requested = array_values(array_unique(array_filter(
            array_map('trim', explode(',', $raw)),
            static fn (string $value): bool => $value !== ''
        )));

        $invalid = array_diff($requested, $allowed);
        if ($invalid !== []) {
            throw ApiException::badRequest(sprintf(
                'Invalid include: %s. Allowed values: %s',
                implode(', ', $invalid),
                implode(', ', $allowed)
            ));
        }

        return $requested;
    }
}
