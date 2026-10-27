<?php

declare(strict_types=1);

namespace Jmr\Support;

final class Env
{
    public static function load(string $path): void
    {
        if (! is_file($path)) {
            return;
        }

        $vars = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $eq = strpos($line, '=');
            if ($eq === false) {
                continue;
            }

            $key = trim(substr($line, 0, $eq));
            $value = trim(substr($line, $eq + 1));
            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"'))
                || (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            $vars[$key] = $value;
        }

        foreach ($vars as $key => $value) {
            $value = preg_replace_callback('/\$\{([A-Z0-9_]+)\}/', function (array $match) use ($vars) {
                return $vars[$match[1]] ?? '';
            }, $value) ?? $value;

            $_ENV[$key] = $value;
            putenv($key.'='.$value);
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = $_ENV[$key] ?? getenv($key);

        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        return (string) $value;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);
        if ($value === null) {
            return $default;
        }

        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }
}
