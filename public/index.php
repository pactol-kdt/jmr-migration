<?php

declare(strict_types=1);

if (PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $file = __DIR__.$path;
    if ($path !== '/' && is_file($file)) {
        return false;
    }
}

require dirname(__DIR__).'/src/bootstrap.php';

use Jmr\Domain\DirectorySync;
use Jmr\Domain\ProjectReport;
use Jmr\Domain\RecordBrowser;
use Jmr\Domain\XmlImporter;
use Jmr\Http\Actions;
use Jmr\Http\Request;
use Jmr\Http\Response;
use Jmr\Support\Account;
use Jmr\Support\Env;

$sessionDir = dirname(__DIR__).'/storage/framework/sessions';
if (! is_dir($sessionDir)) {
    mkdir($sessionDir, 0777, true);
}

session_save_path($sessionDir);
session_name('JMRSESSID');
session_set_cookie_params([
    'lifetime' => 7200,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

if (empty($_SESSION['csrf']) || ! is_string($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$request = new Request();
if (isset($_SESSION['user_id'])) {
    $request->user = Account::find((int) $_SESSION['user_id']);
}

$cookieUser = $request->userFromCookie();
if ($cookieUser instanceof Account) {
    $request->user = $cookieUser;
}

$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$isApi = $uriPath === '/api' || str_starts_with($uriPath, '/api/');

if (! $isApi && $request->method === 'GET' && ! str_contains($request->path, '/export')) {
    Response::html(jmr_spa_html());
}

try {
    $records = new RecordBrowser();
    (new Actions(
        $request,
        $records,
        new ProjectReport($records),
        new XmlImporter(),
        new DirectorySync(new XmlImporter()),
    ))->dispatch();
} catch (Throwable $e) {
    jmr_log('error', $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
    $message = Env::bool('APP_DEBUG') ? $e->getMessage() : 'Server error.';
    Response::error($message, 500);
}
