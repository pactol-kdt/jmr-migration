<?php

declare(strict_types=1);

function jmr_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);

    return sprintf(
        '%s-%s-%s-%s-%s',
        substr($hex, 0, 8),
        substr($hex, 8, 4),
        substr($hex, 12, 4),
        substr($hex, 16, 4),
        substr($hex, 20, 12),
    );
}

function jmr_now(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
}

function jmr_display_time(mixed $value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }

    $timezone = $_ENV['APP_TIMEZONE'] ?? 'UTC';

    try {
        $dt = new DateTimeImmutable((string) $value, new DateTimeZone('UTC'));
    } catch (Throwable) {
        return null;
    }

    return $dt->setTimezone(new DateTimeZone($timezone))->format('Y-m-d H:i');
}

function jmr_bool(mixed $value): bool
{
    return $value === true || $value === 1 || $value === '1';
}

function jmr_bool_or_null(mixed $value): ?bool
{
    if ($value === null || $value === '') {
        return null;
    }

    return jmr_bool($value);
}

function jmr_like(string $value): string
{
    return '%'.addcslashes($value, '%_\\').'%';
}

function jmr_prefix(string $value): string
{
    return addcslashes($value, '%_\\').'%';
}

function jmr_storage_path(string $relative = ''): string
{
    $root = dirname(__DIR__).'/storage/app';

    return $relative === '' ? $root : $root.'/'.ltrim(str_replace('\\', '/', $relative), '/');
}

function jmr_log(string $level, string $message, array $context = []): void
{
    $dir = dirname(__DIR__).'/storage/logs';
    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $line = sprintf(
        "[%s] %s: %s %s\n",
        jmr_now(),
        strtoupper($level),
        $message,
        $context === [] ? '' : json_encode($context, JSON_UNESCAPED_UNICODE),
    );

    file_put_contents($dir.'/app.log', $line, FILE_APPEND);
}
