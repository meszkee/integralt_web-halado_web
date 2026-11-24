<?php

declare(strict_types=1);

namespace App;

use Dotenv\Dotenv;
use PDO;

/**
 * Loads environment variables and provides a shared PDO database connection.
 */
final class Database
{
    private static ?PDO $connection = null;

    /**
     * Returns the singleton PDO instance configured from .env.
     */
    public static function getConnection(): PDO
    {
        if (self::$connection !== null) {
            return self::$connection;
        }

        $rootDir = dirname(__DIR__);
        if (file_exists($rootDir . '/.env')) {
            $dotenv = Dotenv::createImmutable($rootDir);
            $dotenv->safeLoad();
        }

        $host = $_ENV['DB_HOST'] ?? $_SERVER['DB_HOST'] ?? '127.0.0.1';
        $port = $_ENV['DB_PORT'] ?? $_SERVER['DB_PORT'] ?? '3306';
        $name = $_ENV['DB_NAME'] ?? $_SERVER['DB_NAME'] ?? '';
        $user = $_ENV['DB_USER'] ?? $_SERVER['DB_USER'] ?? '';
        $password = $_ENV['DB_PASSWORD'] ?? $_SERVER['DB_PASSWORD'] ?? '';

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);

        self::$connection = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return self::$connection;
    }
}
