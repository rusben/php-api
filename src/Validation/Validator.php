<?php

declare(strict_types=1);

namespace App\Validation;

use DateTimeImmutable;

/**
 * Validación y normalización de las entradas de la API.
 *
 * Todos los métodos públicos devuelven una tupla [datosLimpios, errores]:
 *  - datosLimpios: valores ya normalizados (trim, mayúsculas en DNI, minúsculas en email...).
 *  - errores:      mapa campo => motivo. Si está vacío, la validación ha sido correcta.
 */
final class Validator
{
    public const USER_FIELDS = ['name', 'surname', 'email', 'dni', 'phone', 'born'];

    /** Campo => longitud máxima. */
    public const PROFILE_FIELDS = [
        'address' => 255,
        'city' => 100,
        'postal_code' => 10,
        'country' => 100,
        'bio' => 1000,
        'website' => 255,
    ];

    public const POST_FIELDS = ['title', 'content'];

    private const DNI_LETTERS = 'TRWAGMYFPDXBNJZSQVHLCKE';

    /**
     * @param  array<string, mixed> $data
     * @param  bool                 $partial true para actualizaciones: solo se validan los campos presentes.
     * @return array{0: array<string, string>, 1: array<string, string>}
     */
    public static function user(array $data, bool $partial = false): array
    {
        $errors = [];
        $clean = [];

        foreach (array_diff(array_keys($data), self::USER_FIELDS) as $unknown) {
            $errors[(string) $unknown] = 'Unknown field';
        }

        foreach (self::USER_FIELDS as $field) {
            if (!array_key_exists($field, $data)) {
                if (!$partial) {
                    $errors[$field] = 'Required field';
                }
                continue;
            }

            $value = $data[$field];

            $result = match ($field) {
                'name' => self::string($value, $field, 1, 100, $errors),
                'surname' => self::string($value, $field, 1, 150, $errors),
                'email' => self::email($value, $field, $errors),
                'dni' => self::dni($value, $field, $errors),
                'phone' => self::phone($value, $field, $errors),
                'born' => self::date($value, $field, $errors),
            };

            if (!isset($errors[$field]) && $result !== null) {
                $clean[$field] = $result;
            }
        }

        if ($partial && $data === []) {
            $errors['body'] = 'At least one field is required';
        }

        return [$clean, $errors];
    }

    /**
     * Valida los datos de un perfil. Todos los campos son opcionales;
     * una cadena vacía o null se interpreta como "borrar el valor".
     *
     * @param  array<string, mixed> $data
     * @return array{0: array<string, string|null>, 1: array<string, string>}
     */
    public static function profile(array $data): array
    {
        $errors = [];
        $clean = [];

        foreach (array_diff(array_keys($data), array_keys(self::PROFILE_FIELDS)) as $unknown) {
            $errors[(string) $unknown] = 'Unknown field';
        }

        foreach (self::PROFILE_FIELDS as $field => $max) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            $value = $data[$field];

            if ($value === null || (is_string($value) && trim($value) === '')) {
                $clean[$field] = null;
                continue;
            }

            $result = self::string($value, $field, 1, $max, $errors);

            if ($result !== null && $field === 'website' && !self::isHttpUrl($result)) {
                $errors[$field] = 'Must be a valid http(s) URL';
            }

            if (!isset($errors[$field])) {
                $clean[$field] = $result;
            }
        }

        if ($data === []) {
            $errors['body'] = 'At least one field is required';
        }

        return [$clean, $errors];
    }

    /**
     * @param  array<string, mixed> $data
     * @return array{0: array<string, string>, 1: array<string, string>}
     */
    public static function post(array $data, bool $partial = false): array
    {
        $errors = [];
        $clean = [];

        foreach (array_diff(array_keys($data), self::POST_FIELDS) as $unknown) {
            $errors[(string) $unknown] = 'Unknown field';
        }

        foreach (self::POST_FIELDS as $field) {
            if (!array_key_exists($field, $data)) {
                if (!$partial) {
                    $errors[$field] = 'Required field';
                }
                continue;
            }

            $result = match ($field) {
                'title' => self::string($data[$field], $field, 1, 150, $errors),
                'content' => self::string($data[$field], $field, 1, 10000, $errors),
            };

            if (!isset($errors[$field]) && $result !== null) {
                $clean[$field] = $result;
            }
        }

        if ($partial && $data === []) {
            $errors['body'] = 'At least one field is required';
        }

        return [$clean, $errors];
    }

    /**
     * Añade un prefijo a las claves de un mapa de errores (para entidades anidadas: "profile.city").
     *
     * @param  array<string, string> $errors
     * @return array<string, string>
     */
    public static function prefix(array $errors, string $prefix): array
    {
        $prefixed = [];

        foreach ($errors as $key => $message) {
            $prefixed[$prefix . $key] = $message;
        }

        return $prefixed;
    }

    /** @param array<string, string> $errors */
    private static function string(mixed $value, string $field, int $min, int $max, array &$errors): ?string
    {
        if (!is_string($value)) {
            $errors[$field] = 'Must be a string';
            return null;
        }

        $value = trim($value);
        $length = mb_strlen($value, 'UTF-8');

        if ($length < $min || $length > $max) {
            $errors[$field] = "Must be between $min and $max characters";
            return null;
        }

        return $value;
    }

    /** @param array<string, string> $errors */
    private static function email(mixed $value, string $field, array &$errors): ?string
    {
        $email = self::string($value, $field, 1, 320, $errors);

        if ($email === null) {
            return null;
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors[$field] = 'Must be a valid email address';
            return null;
        }

        return strtolower($email);
    }

    /** @param array<string, string> $errors */
    private static function dni(mixed $value, string $field, array &$errors): ?string
    {
        if (!is_string($value)) {
            $errors[$field] = 'Must be a string';
            return null;
        }

        $dni = strtoupper(trim($value));

        if (
            preg_match('/^[0-9]{8}[A-Z]$/', $dni) !== 1
            || $dni[8] !== self::DNI_LETTERS[((int) substr($dni, 0, 8)) % 23]
        ) {
            $errors[$field] = 'Must be a valid DNI (8 digits followed by the correct control letter)';
            return null;
        }

        return $dni;
    }

    /** @param array<string, string> $errors */
    private static function phone(mixed $value, string $field, array &$errors): ?string
    {
        $phone = self::string($value, $field, 1, 20, $errors);

        if ($phone === null) {
            return null;
        }

        if (preg_match('/^\+?[0-9][0-9 ]{7,17}[0-9]$/', $phone) !== 1) {
            $errors[$field] = 'Must be a valid phone number (8 to 18 digits, optional leading + and spaces)';
            return null;
        }

        return $phone;
    }

    /** @param array<string, string> $errors */
    private static function date(mixed $value, string $field, array &$errors): ?string
    {
        if (!is_string($value)) {
            $errors[$field] = 'Must be a string with format YYYY-MM-DD';
            return null;
        }

        $value = trim($value);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $parseErrors = DateTimeImmutable::getLastErrors();

        if (
            $date === false
            || ($parseErrors !== false && ($parseErrors['warning_count'] > 0 || $parseErrors['error_count'] > 0))
            || $date->format('Y-m-d') !== $value
        ) {
            $errors[$field] = 'Must be a valid date with format YYYY-MM-DD';
            return null;
        }

        if ($date > new DateTimeImmutable('today')) {
            $errors[$field] = 'Cannot be in the future';
            return null;
        }

        if ((int) $date->format('Y') < 1900) {
            $errors[$field] = 'Cannot be before 1900';
            return null;
        }

        return $value;
    }

    private static function isHttpUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
    }
}
