<?php

declare(strict_types=1);

namespace App\Config;

/**
 * Acceso a la configuración de la aplicación.
 *
 * Los valores se leen, por orden de prioridad, de:
 *   1. Variables de entorno reales (getenv).
 *   2. El fichero ".env" de la raíz del proyecto (si existe).
 *   3. El valor por defecto indicado en la llamada.
 */
final class Config
{
    /** @var array<string, string> */
    private static array $fileValues = [];

    public static function load(string $projectRoot): void
    {
        self::$fileValues = [];
        $file = rtrim($projectRoot, '/') . '/.env';

        if (!is_readable($file)) {
            return;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            self::$fileValues[trim($key)] = trim($value, " \t\"'");
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $env = getenv($key);

        if ($env !== false) {
            return $env;
        }

        return self::$fileValues[$key] ?? $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);

        if ($value === null || $value === '') {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
