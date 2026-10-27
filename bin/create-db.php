<?php

declare(strict_types=1);

try {
    $pdo = new PDO('mysql:host=127.0.0.1;port=3306', 'root', '');
    $pdo->exec('CREATE DATABASE IF NOT EXISTS manhour_importer CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    echo "mysql_ok\n";
} catch (Throwable $e) {
    echo 'mysql_fail: '.$e->getMessage()."\n";
    exit(1);
}
