<?php

declare(strict_types=1);

namespace Jmr\Http;

use Jmr\Support\Account;
use Jmr\Support\Db;

final class Request
{
    public string $method;

    public string $path;

    /** @var array<string, mixed> */
    public array $query;

    /** @var array<string, mixed> */
    public array $body;

    public ?Account $user = null;

    public function __construct()
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($this->method === 'POST' && isset($_POST['_method'])) {
            $this->method = strtoupper((string) $_POST['_method']);
        }

        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $uri = rawurldecode($uri);
        if (str_starts_with($uri, '/api/')) {
            $uri = substr($uri, 4);
        } elseif ($uri === '/api') {
            $uri = '/';
        }
        $this->path = rtrim($uri, '/') ?: '/';
        $this->query = $_GET;
        $this->body = $this->readBody();
    }

    public function wantsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $requested = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';

        return str_contains($accept, 'application/json')
            || strcasecmp($requested, 'XMLHttpRequest') === 0
            || $this->method !== 'GET'
            || str_starts_with(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '', '/api');
    }

    /** @return list<string>|null */
    public function partial(): ?array
    {
        $only = $_SERVER['HTTP_X_PARTIAL_DATA'] ?? '';
        if (! is_string($only) || $only === '') {
            return null;
        }

        return array_values(array_filter(array_map('trim', explode(',', $only))));
    }

    public function input(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->body)) {
            return $this->body[$key];
        }

        return $this->query[$key] ?? $default;
    }

    public function integer(string $key): ?int
    {
        $value = $this->input($key);
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    public function csrfToken(): ?string
    {
        $header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (is_string($header) && $header !== '') {
            return $header;
        }

        $body = $this->body['_token'] ?? null;

        return is_string($body) ? $body : null;
    }

    /**
     * @return list<array{name:string,tmp_name:string,size:int,error:int}>
     */
    public function files(string $key): array
    {
        if (! isset($_FILES[$key])) {
            return [];
        }

        $file = $_FILES[$key];
        if (! is_array($file['name'])) {
            return [[
                'name' => (string) $file['name'],
                'tmp_name' => (string) $file['tmp_name'],
                'size' => (int) $file['size'],
                'error' => (int) $file['error'],
            ]];
        }

        $files = [];
        foreach ($file['name'] as $index => $name) {
            $files[] = [
                'name' => (string) $name,
                'tmp_name' => (string) $file['tmp_name'][$index],
                'size' => (int) $file['size'][$index],
                'error' => (int) $file['error'][$index],
            ];
        }

        return $files;
    }

    public function userFromCookie(): ?Account
    {
        $hash = $_COOKIE['userId'] ?? null;
        if (! is_string($hash) || $hash === '') {
            return null;
        }

        $kdt = Db::kdt();
        if ($kdt === null) {
            return null;
        }

        $stmt = $kdt->prepare('SELECT fldEmployeeNum FROM kdtlogin WHERE fldUserHash = ? LIMIT 1');
        $stmt->execute([$hash]);
        $employeeNum = $stmt->fetchColumn();
        if ($employeeNum === false || $employeeNum === null) {
            return null;
        }

        return Account::findByEmployeeId((string) $employeeNum);
    }

    /** @return array<string, mixed> */
    private function readBody(): array
    {
        $type = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($type, 'application/json')) {
            $decoded = json_decode((string) file_get_contents('php://input'), true);

            return is_array($decoded) ? $decoded : [];
        }

        return $_POST;
    }
}
