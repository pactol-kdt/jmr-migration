<?php

declare(strict_types=1);

require dirname(__DIR__).'/src/bootstrap.php';

use Jmr\Support\Account;
use Jmr\Support\Db;

$driver = Db::driver();
$id = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
$bool = $driver === 'sqlite' ? 'INTEGER' : 'TINYINT(1)';
$timestamp = $driver === 'sqlite' ? 'TEXT' : 'TIMESTAMP NULL';

$statements = [
    "CREATE TABLE IF NOT EXISTS roles (
        id {$id},
        name VARCHAR(255) NOT NULL UNIQUE
    )",
    "CREATE TABLE IF NOT EXISTS users (
        id {$id},
        employee_id VARCHAR(255) NULL UNIQUE,
        user_hash VARCHAR(64) NULL UNIQUE,
        name VARCHAR(255) NULL,
        username VARCHAR(255) NULL UNIQUE,
        pc VARCHAR(255) NULL,
        group_name VARCHAR(255) NULL,
        designation VARCHAR(255) NULL,
        directory_active {$bool} NULL,
        resigned_on DATE NULL,
        access_type VARCHAR(255) NULL,
        role_id BIGINT NULL,
        can_edit {$bool} NOT NULL DEFAULT 0,
        email VARCHAR(255) NULL UNIQUE,
        email_verified_at {$timestamp},
        password VARCHAR(255) NULL,
        remember_token VARCHAR(100) NULL,
        created_at {$timestamp},
        updated_at {$timestamp}
    )",
    "CREATE TABLE IF NOT EXISTS password_reset_tokens (
        email VARCHAR(255) NOT NULL PRIMARY KEY,
        token VARCHAR(255) NOT NULL,
        created_at {$timestamp}
    )",
    "CREATE TABLE IF NOT EXISTS years (
        id {$id},
        year INTEGER NOT NULL UNIQUE,
        created_at {$timestamp},
        updated_at {$timestamp}
    )",
    "CREATE TABLE IF NOT EXISTS import_batches (
        id {$id},
        year_id BIGINT NOT NULL,
        employee_id VARCHAR(255) NULL,
        user_id BIGINT NULL,
        original_filename VARCHAR(255) NOT NULL,
        stored_path VARCHAR(255) NOT NULL,
        status VARCHAR(255) NOT NULL DEFAULT 'pending',
        inserted_count INTEGER NOT NULL DEFAULT 0,
        updated_count INTEGER NOT NULL DEFAULT 0,
        skipped_count INTEGER NOT NULL DEFAULT 0,
        failed_count INTEGER NOT NULL DEFAULT 0,
        error_message TEXT NULL,
        row_errors TEXT NULL,
        started_at {$timestamp},
        finished_at {$timestamp},
        created_at {$timestamp},
        updated_at {$timestamp}
    )",
    "CREATE TABLE IF NOT EXISTS records (
        id {$id},
        year_id BIGINT NOT NULL,
        employee_id VARCHAR(255) NULL,
        import_batch_id BIGINT NULL,
        work_date DATE NOT NULL,
        location VARCHAR(255) NULL,
        project VARCHAR(255) NULL,
        item VARCHAR(255) NULL,
        description TEXT NULL,
        type VARCHAR(255) NULL,
        hour DECIMAL(10,2) NULL,
        remarks TEXT NULL,
        category VARCHAR(255) NULL,
        `group` VARCHAR(255) NULL,
        person_in_charge VARCHAR(255) NULL,
        dimension VARCHAR(255) NULL,
        change_log TEXT NULL,
        member VARCHAR(255) NULL,
        sheet_count VARCHAR(255) NULL,
        paper_size VARCHAR(255) NULL,
        progress VARCHAR(255) NULL,
        revision VARCHAR(255) NULL,
        is_active {$bool} NOT NULL DEFAULT 1,
        merge_key CHAR(64) NOT NULL,
        content_hash CHAR(64) NOT NULL,
        created_at {$timestamp},
        updated_at {$timestamp},
        UNIQUE (year_id, employee_id, merge_key)
    )",
];

foreach ($statements as $sql) {
    Db::pdo()->exec($sql);
}

foreach ([Account::DEVELOPER, Account::MANAGEMENT, Account::LEADER, Account::MEMBER] as $role) {
    if (Db::value('SELECT id FROM roles WHERE name = ?', [$role]) === null) {
        Db::exec('INSERT INTO roles (name) VALUES (?)', [$role]);
    }
}

$email = 'adm@ex.com';
if (Db::value('SELECT id FROM users WHERE email = ?', [$email]) === null) {
    $now = jmr_now();
    Db::exec(
        'INSERT INTO users (name, email, password, email_verified_at, role_id, can_edit, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, 0, ?, ?)',
        ['Admin', $email, password_hash('12345678', PASSWORD_BCRYPT), $now, Account::roleId(Account::DEVELOPER), $now, $now],
    );
    echo "Seeded admin {$email} / 12345678 (developer)\n";
} else {
    echo "Admin {$email} already exists\n";
}

echo "Database is ready ({$driver}).\n";
