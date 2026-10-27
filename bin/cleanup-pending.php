<?php

declare(strict_types=1);

require dirname(__DIR__).'/src/bootstrap.php';

use Jmr\Support\Db;

$hours = 24;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--hours=')) {
        $hours = max(1, (int) substr($arg, 8));
    }
}

$deleted = 0;
$directory = jmr_storage_path('imports/pending');
if (is_dir($directory)) {
    foreach (scandir($directory) ?: [] as $name) {
        if ($name === '.' || $name === '..') {
            continue;
        }
        $absolute = $directory.'/'.$name;
        if (! is_file($absolute) || filemtime($absolute) > time() - ($hours * 3600)) {
            continue;
        }

        $relative = 'imports/pending/'.$name;
        $used = Db::value(
            "SELECT id FROM import_batches WHERE stored_path = ? AND status IN ('pending', 'processing')",
            [$relative],
        );
        if ($used === null) {
            unlink($absolute);
            $deleted++;
        }
    }
}

echo "Deleted {$deleted} orphaned upload(s) older than {$hours} hour(s).\n";
