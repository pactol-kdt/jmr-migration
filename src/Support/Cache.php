<?php

declare(strict_types=1);

namespace Jmr\Support;

final class Cache
{
    public static function remember(string $name, array $scope, callable $callback): mixed
    {
        $scope['__viewer'] = $scope['__viewer'] ?? 'all';
        ksort($scope);
        $key = self::version().'.'.$name.'.'.md5((string) json_encode($scope));
        $file = self::dir().'/'.md5($key).'.json';

        if (is_file($file)) {
            $cached = json_decode((string) file_get_contents($file), true);
            if (is_array($cached) && ($cached['key'] ?? '') === $key && ($cached['exp'] ?? 0) >= time()) {
                return $cached['value'];
            }
        }

        $value = $callback();
        file_put_contents($file, json_encode([
            'key' => $key,
            'exp' => time() + 600,
            'value' => $value,
        ], JSON_THROW_ON_ERROR));

        return $value;
    }

    public static function bump(): void
    {
        file_put_contents(self::dir().'/version.txt', (string) (self::version() + 1));
    }

    private static function version(): int
    {
        $file = self::dir().'/version.txt';

        return is_file($file) ? (int) file_get_contents($file) : 1;
    }

    private static function dir(): string
    {
        $dir = dirname(__DIR__, 2).'/storage/framework/cache';
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        return $dir;
    }
}
