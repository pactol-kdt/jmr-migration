<?php

declare(strict_types=1);

namespace Jmr\Support;

use PDO;
use PDOException;
use Throwable;

final class Db
{
    private static ?PDO $pdo = null;

    private static ?PDO $kdt = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $connection = Env::get('DB_CONNECTION', 'sqlite');

        if ($connection === 'sqlite') {
            $database = Env::get('DB_DATABASE', 'database/database.sqlite') ?? 'database/database.sqlite';
            if (! str_starts_with($database, '/') && ! preg_match('/^[A-Za-z]:[\\\\\\/]/', $database)) {
                $database = dirname(__DIR__, 2).'/'.$database;
            }
            $dir = dirname($database);
            if (! is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            if (! is_file($database)) {
                touch($database);
            }
            $pdo = new PDO('sqlite:'.$database);
            $pdo->exec('PRAGMA foreign_keys = ON');
        } else {
            $host = Env::get('DB_HOST', '127.0.0.1');
            $port = Env::get('DB_PORT', '3306');
            $name = Env::get('DB_DATABASE', 'manhour_importer');
            $user = Env::get('DB_USERNAME', 'root');
            $pass = Env::get('DB_PASSWORD', '') ?? '';
            $pdo = new PDO(
                "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
                $user,
                $pass,
            );
        }

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return self::$pdo = $pdo;
    }

    public static function kdt(): ?PDO
    {
        if (self::$kdt instanceof PDO) {
            return self::$kdt;
        }

        $name = Env::get('DB_KDT_DATABASE');
        if ($name === null) {
            return null;
        }

        try {
            $host = Env::get('DB_HOST', '127.0.0.1');
            $port = Env::get('DB_PORT', '3306');
            $user = Env::get('DB_USERNAME', 'root');
            $pass = Env::get('DB_PASSWORD', '') ?? '';
            $pdo = new PDO(
                "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
                $user,
                $pass,
            );
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            self::$kdt = $pdo;
        } catch (PDOException) {
            return null;
        }

        return self::$kdt;
    }

    public static function driver(): string
    {
        return self::pdo()->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute(self::params($params));
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public static function all(string $sql, array $params = []): array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute(self::params($params));

        return $stmt->fetchAll();
    }

    public static function value(string $sql, array $params = []): mixed
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute(self::params($params));
        $value = $stmt->fetchColumn();

        return $value === false ? null : $value;
    }

    public static function exec(string $sql, array $params = []): int
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute(self::params($params));

        return $stmt->rowCount();
    }

    public static function insert(string $sql, array $params = []): string
    {
        self::exec($sql, $params);

        return self::pdo()->lastInsertId();
    }

    public static function transaction(callable $callback): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();

        try {
            $result = $callback();
            $pdo->commit();

            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    public static function isUniqueViolation(Throwable $e): bool
    {
        if (! $e instanceof PDOException) {
            return false;
        }

        $state = $e->errorInfo[0] ?? null;
        $code = $e->errorInfo[1] ?? null;

        return $state === '23000' || $code === 1062 || $code === 19 || $code === '19';
    }

    /**
     * @param  list<mixed>|array<string, mixed>  $params
     * @return list<mixed>
     */
    private static function params(array $params): array
    {
        $index = 0;
        foreach ($params as $key => $unused) {
            if ($key !== $index) {
                return array_values($params);
            }
            $index++;
        }

        return $params;
    }
}
