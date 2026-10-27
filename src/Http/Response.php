<?php

declare(strict_types=1);

namespace Jmr\Http;

use Jmr\Support\Account;
use Jmr\Support\Env;

final class Response
{
    public static function page(Request $request, array $props, int $status = 200): never
    {
        $partial = $request->partial();
        if ($partial !== null) {
            $picked = [];
            foreach ($partial as $key) {
                if (array_key_exists($key, $props)) {
                    $picked[$key] = $props[$key];
                }
            }
            $props = $picked;
        }

        $flash = $_SESSION['flash'] ?? [
            'success' => null,
            'error' => null,
            'warning' => null,
            'info' => null,
        ];
        unset($_SESSION['flash']);
        $statusMessage = $_SESSION['status'] ?? null;
        unset($_SESSION['status']);

        $user = $request->user;

        self::json(array_merge($props, [
            'auth' => [
                'user' => $user?->toPublic(),
                'can' => [
                    'control' => $user?->hasControl() ?? false,
                    'upload' => $user?->canUpload() ?? false,
                ],
            ],
            'flash' => [
                'success' => $flash['success'] ?? null,
                'error' => $flash['error'] ?? null,
                'warning' => $flash['warning'] ?? null,
                'info' => $flash['info'] ?? null,
            ],
            'status' => $statusMessage,
            'externalSite' => self::externalSite(),
            'csrf' => $_SESSION['csrf'] ?? null,
        ]), $status);
    }

    public static function redirect(string $to, array $flash = [], ?string $status = null): never
    {
        foreach ($flash as $key => $message) {
            $_SESSION['flash'][$key] = $message;
        }
        if ($status !== null) {
            $_SESSION['status'] = $status;
        }

        self::json([
            'redirect' => $to,
            'csrf' => $_SESSION['csrf'] ?? null,
        ]);
    }

    /** @param  array<string, string>  $errors */
    public static function invalid(array $errors, string $message = 'The given data was invalid.'): never
    {
        self::json([
            'message' => $message,
            'errors' => $errors,
        ], 422);
    }

    public static function error(string $message, int $status): never
    {
        self::json(['message' => $message], $status);
    }

    public static function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        exit;
    }

    public static function download(string $filename, string $contentType, string $body): never
    {
        http_response_code(200);
        header('Content-Type: '.$contentType);
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        header('Content-Length: '.strlen($body));
        echo $body;
        exit;
    }

    public static function html(string $html): never
    {
        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        exit;
    }

    /** @return array{url:string,label:string}|null */
    private static function externalSite(): ?array
    {
        $url = trim((string) Env::get('EXTERNAL_SITE_URL', ''));
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if ($url === '' || ! in_array($scheme, ['http', 'https'], true) || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $label = trim((string) Env::get('EXTERNAL_SITE_LABEL', 'External site'));

        return [
            'url' => $url,
            'label' => $label !== '' ? $label : 'External site',
        ];
    }
}
