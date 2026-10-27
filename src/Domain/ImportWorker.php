<?php

declare(strict_types=1);

namespace Jmr\Domain;

use Jmr\Support\Cache;
use Jmr\Support\Db;
use Throwable;

final class ImportWorker
{
    public function __construct(private XmlImporter $importer) {}

    public function run(): int
    {
        $lockPath = dirname(__DIR__, 2).'/storage/framework/import-worker.lock';
        $dir = dirname($lockPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $lock = fopen($lockPath, 'c');
        if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
            return 0;
        }

        $processed = 0;
        try {
            while ($batch = $this->claim()) {
                $this->process($batch);
                $processed++;
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        return $processed;
    }

    public static function kick(): void
    {
        $php = PHP_BINARY;
        $script = dirname(__DIR__, 2).'/bin/worker.php';
        $command = escapeshellarg($php).' '.escapeshellarg($script);

        if (PHP_OS_FAMILY === 'Windows') {
            pclose(popen('start /B "" '.$command, 'r'));

            return;
        }

        exec($command.' > /dev/null 2>&1 &');
    }

    /** @return array<string, mixed>|null */
    private function claim(): ?array
    {
        return Db::transaction(function () {
            $row = Db::one("SELECT * FROM import_batches WHERE status = 'pending' ORDER BY id LIMIT 1");
            if ($row === null) {
                return null;
            }

            $updated = Db::exec(
                "UPDATE import_batches
                 SET status = 'processing', started_at = ?, error_message = NULL, row_errors = NULL, updated_at = ?
                 WHERE id = ? AND status = 'pending'",
                [jmr_now(), jmr_now(), $row['id']],
            );

            return $updated === 1 ? $row : null;
        });
    }

    /** @param  array<string, mixed>  $batch */
    private function process(array $batch): void
    {
        $storedPath = (string) $batch['stored_path'];
        $year = Db::one('SELECT year FROM years WHERE id = ?', [$batch['year_id']]);

        try {
            $counts = $this->importer->import(
                [
                    'id' => (int) $batch['id'],
                    'year_id' => (int) $batch['year_id'],
                    'employee_id' => $batch['employee_id'] !== null ? (string) $batch['employee_id'] : null,
                ],
                jmr_storage_path($storedPath),
                (int) ($year['year'] ?? 0),
            );

            Db::exec(
                'UPDATE import_batches
                 SET status = ?, inserted_count = ?, updated_count = ?, skipped_count = ?, failed_count = ?,
                     row_errors = ?, finished_at = ?, updated_at = ?
                 WHERE id = ?',
                [
                    'completed',
                    $counts['inserted'],
                    $counts['updated'],
                    $counts['skipped'],
                    $counts['failed'],
                    $counts['row_errors'] !== [] ? json_encode($counts['row_errors']) : null,
                    jmr_now(),
                    jmr_now(),
                    $batch['id'],
                ],
            );
        } catch (Throwable $e) {
            jmr_log('error', 'XML import failed', [
                'batch_id' => $batch['id'],
                'message' => $e->getMessage(),
            ]);

            Db::exec(
                'UPDATE import_batches SET status = ?, error_message = ?, finished_at = ?, updated_at = ? WHERE id = ?',
                ['failed', $e->getMessage(), jmr_now(), jmr_now(), $batch['id']],
            );
        } finally {
            Cache::bump();
            $absolute = jmr_storage_path($storedPath);
            if (is_file($absolute)) {
                unlink($absolute);
            }
        }
    }
}
