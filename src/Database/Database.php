<?php

declare(strict_types=1);

namespace App\Database;

use App\Config\Config;
use PDO;

/**
 * Proporciona una única conexión PDO por petición (creada de forma perezosa).
 */
final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                Config::get('DB_HOST', 'localhost'),
                Config::get('DB_PORT', '3306'),
                Config::get('DB_NAME', 'api'),
            );

            self::$connection = new PDO(
                $dsn,
                Config::get('DB_USER', 'usuario'),
                Config::get('DB_PASS', 'password'),
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        }

        return self::$connection;
    }
}
