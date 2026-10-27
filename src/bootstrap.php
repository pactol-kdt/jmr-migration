<?php

declare(strict_types=1);

require __DIR__.'/functions.php';

spl_autoload_register(function (string $class): void {
    $prefix = 'Jmr\\';
    if (! str_starts_with($class, $prefix)) {
        return;
    }

    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = __DIR__.'/'.$relative.'.php';
    if (is_file($file)) {
        require $file;
    }
});

Jmr\Support\Env::load(dirname(__DIR__).'/.env');
date_default_timezone_set(Jmr\Support\Env::get('APP_TIMEZONE', 'UTC') ?? 'UTC');

function jmr_spa_html(): string
{
    $manifestPaths = [
        dirname(__DIR__).'/public/build/.vite/manifest.json',
        dirname(__DIR__).'/public/build/manifest.json',
    ];
    $tags = '';
    foreach ($manifestPaths as $manifestPath) {
        if (! is_file($manifestPath)) {
            continue;
        }
        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        $entry = $manifest['index.html']
            ?? $manifest['resources/js/app.jsx']
            ?? null;
        if (! is_array($entry)) {
            foreach ($manifest as $candidate) {
                if (is_array($candidate) && ($candidate['isEntry'] ?? false)) {
                    $entry = $candidate;
                    break;
                }
            }
        }
        if (! is_array($entry)) {
            continue;
        }
        foreach ($entry['css'] ?? [] as $css) {
            $tags .= '<link rel="stylesheet" href="/build/'.$css.'">';
        }
        $tags .= '<script type="module" src="/build/'.$entry['file'].'"></script>';
        break;
    }

    if ($tags === '') {
        $tags = '<script type="module" src="http://localhost:5173/@vite/client"></script>'
            .'<script type="module" src="http://localhost:5173/resources/js/app.jsx"></script>';
    }

    $title = htmlspecialchars(Jmr\Support\Env::get('APP_NAME', 'JMR Migration') ?? 'JMR Migration', ENT_QUOTES);

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$title}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700&display=swap" rel="stylesheet">
</head>
<body class="font-sans antialiased">
    <div id="app"></div>
    {$tags}
</body>
</html>
HTML;
}
