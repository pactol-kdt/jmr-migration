<?php

declare(strict_types=1);

namespace Jmr\Domain;

use Jmr\Support\Db;
use PDOException;
use RuntimeException;
use SimpleXMLElement;
use Throwable;

final class XmlImporter
{
    public const MAX_XML_BYTES = 52_428_800;

    public const CHUNK_SIZE = 250;

    public const MAX_STORED_ROW_ERRORS = 100;

    /** @var list<string> */
    public const SUPPORTED_ROW_FIELDS = [
        'Location',
        'Project',
        'Item',
        'Description',
        'Type',
        'Hour',
        'Remarks',
        'Manhour Type',
        'Group',
        'Person in Charge',
        '2D/3D',
        'Change Log',
        'Member',
        'No. of Sheets',
        'Paper Size',
        'Progress',
        'Revision',
    ];

    public function decodeDateElementName(string $name): ?string
    {
        $decoded = $this->decodeEncodedName($name);
        if (! preg_match('/^(\d{4})_(\d{2})_(\d{2})$/', $decoded, $matches)) {
            return null;
        }

        $year = (int) $matches[1];
        $month = (int) $matches[2];
        $day = (int) $matches[3];
        if (! checkdate($month, $day, $year)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    public function looksLikeDateElement(string $name): bool
    {
        return (bool) preg_match('/^\d{4}_\d{2}_\d{2}$/', $this->decodeEncodedName($name));
    }

    /** @return array{year:int, employee_id:string}|null */
    public function parseUploadFilename(string $filename): ?array
    {
        $name = basename(str_replace('\\', '/', trim($filename)));
        if (! preg_match('/^(\d{4})_(\d+)\.(xml|txt)$/i', $name, $matches)) {
            return null;
        }

        return [
            'year' => (int) $matches[1],
            'employee_id' => $matches[2],
        ];
    }

    public function normalizeKeyPart(?string $value): string
    {
        return trim((string) $value);
    }

    public function parseHour(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $normalized = str_replace(',', '.', trim($value));
        if (! is_numeric($normalized)) {
            return null;
        }

        return number_format((float) $normalized, 2, '.', '');
    }

    /** @param  array<string, mixed>  $row */
    public function contentHash(array $row): string
    {
        return hash('sha256', json_encode([
            $row['work_date'],
            $row['location'],
            $row['project'],
            $row['item'],
            $row['description'],
            $row['type'],
            $row['hour'],
            $row['remarks'],
            $row['category'],
            $row['group'],
            $row['person_in_charge'],
            $row['dimension'],
            $row['change_log'],
            $row['member'],
            $row['sheet_count'],
            $row['paper_size'],
            $row['progress'],
            $row['revision'],
        ], JSON_THROW_ON_ERROR));
    }

    /** @param  array<string, mixed>  $row */
    public function mergeKey(array $row): string
    {
        return hash('sha256', json_encode([
            $row['work_date'],
            $row['location'],
            $row['project'],
            $row['item'],
            $row['type'],
            $row['category'],
            $row['group'],
            $row['person_in_charge'],
            $row['dimension'],
            $row['member'],
            $row['paper_size'],
            $row['revision'],
        ], JSON_THROW_ON_ERROR));
    }

    public function loadXml(string $absolutePath): SimpleXMLElement
    {
        if (! is_file($absolutePath)) {
            throw new RuntimeException('Uploaded XML file is missing on disk.');
        }

        $size = filesize($absolutePath);
        if ($size === false || $size === 0) {
            throw new RuntimeException('XML file is empty or unreadable.');
        }

        if ($size > self::MAX_XML_BYTES) {
            throw new RuntimeException('XML file exceeds the maximum allowed size.');
        }

        $contents = file_get_contents($absolutePath);
        if ($contents === false || $contents === '') {
            throw new RuntimeException('Unable to read XML file contents.');
        }

        if (preg_match('/<!DOCTYPE/i', $contents) || preg_match('/<!ENTITY/i', $contents)) {
            throw new RuntimeException('XML containing DOCTYPE or ENTITY declarations is not allowed.');
        }

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $xml = simplexml_load_string(
                $contents,
                SimpleXMLElement::class,
                LIBXML_NONET | LIBXML_COMPACT | LIBXML_NOCDATA,
            );

            if ($xml === false) {
                $messages = [];
                foreach (array_slice(libxml_get_errors(), 0, 3) as $error) {
                    $text = trim($error->message);
                    if ($text !== '') {
                        $messages[] = $text;
                    }
                }

                throw new RuntimeException(
                    $messages !== []
                        ? 'Invalid XML: '.implode('; ', $messages)
                        : 'Invalid or malformed XML.',
                );
            }

            return $xml;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /**
     * @return array{
     *     total_rows:int,
     *     years:list<array{year:int,count:int,date_from:string,date_to:string}>,
     *     unsupported_fields:list<string>
     * }
     */
    public function detectYears(string $absolutePath): array
    {
        $xml = $this->loadXml($absolutePath);
        $byYear = [];
        $unsupported = [];

        foreach ($xml->children() as $child) {
            $workDate = $this->decodeDateElementName($child->getName());
            if ($workDate === null) {
                continue;
            }

            $year = (int) substr($workDate, 0, 4);
            if (! isset($byYear[$year])) {
                $byYear[$year] = [
                    'year' => $year,
                    'count' => 0,
                    'date_from' => $workDate,
                    'date_to' => $workDate,
                ];
            }

            $byYear[$year]['count']++;
            $byYear[$year]['date_from'] = min($byYear[$year]['date_from'], $workDate);
            $byYear[$year]['date_to'] = max($byYear[$year]['date_to'], $workDate);

            foreach ($this->collectUnsupportedFields($child) as $fieldName) {
                $unsupported[$fieldName] = true;
            }
        }

        ksort($byYear);
        ksort($unsupported);

        return [
            'total_rows' => array_sum(array_column($byYear, 'count')),
            'years' => array_values($byYear),
            'unsupported_fields' => array_keys($unsupported),
        ];
    }

    /** @return list<string> */
    public function collectUnsupportedFields(SimpleXMLElement $rowNode): array
    {
        $unsupported = [];
        foreach ($rowNode->children() as $field) {
            $name = $this->decodeEncodedName($field->getName());
            if ($name === '' || in_array($name, self::SUPPORTED_ROW_FIELDS, true)) {
                continue;
            }
            $unsupported[$name] = true;
        }

        return array_keys($unsupported);
    }

    /**
     * @param  array{id:int,year_id:int,employee_id:?string}  $batch
     * @return array{inserted:int,updated:int,skipped:int,failed:int,row_errors:list<array{element:?string,work_date:?string,message:string}>}
     */
    public function import(array $batch, string $absolutePath, int $targetYear): array
    {
        $counts = ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
        $rowErrors = [];
        $xml = $this->loadXml($absolutePath);
        $pendingRows = [];

        foreach ($xml->children() as $child) {
            $elementName = $child->getName();
            if (str_contains(strtolower($elementName), 'schema')) {
                continue;
            }

            if ($this->looksLikeDateElement($elementName) && $this->decodeDateElementName($elementName) === null) {
                $counts['failed']++;
                $this->pushRowError($rowErrors, $elementName, null, 'Invalid calendar date in element name.');
                continue;
            }

            $workDate = $this->decodeDateElementName($elementName);
            if ($workDate === null || (int) substr($workDate, 0, 4) !== $targetYear) {
                continue;
            }

            try {
                $pendingRows[] = $this->mapRow((int) $batch['year_id'], $batch['employee_id'], $workDate, $child);
            } catch (Throwable $e) {
                $counts['failed']++;
                $this->pushRowError($rowErrors, $elementName, $workDate, $e->getMessage());
            }

            if (count($pendingRows) >= self::CHUNK_SIZE) {
                $this->mergeCounts($counts, $this->processChunk($batch, $pendingRows, $rowErrors));
                $pendingRows = [];
            }
        }

        if ($pendingRows !== []) {
            $this->mergeCounts($counts, $this->processChunk($batch, $pendingRows, $rowErrors));
        }

        return [...$counts, 'row_errors' => $rowErrors];
    }

    /**
     * @param  array{id:int,year_id:int,employee_id:?string}  $batch
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array{element:?string,work_date:?string,message:string}>  $rowErrors
     * @return array{inserted:int,updated:int,skipped:int,failed:int}
     */
    private function processChunk(array $batch, array $rows, array &$rowErrors): array
    {
        $counts = ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];

        try {
            Db::transaction(function () use ($batch, $rows, &$counts, &$rowErrors) {
                $this->upsertChunk($batch, $rows, $counts, $rowErrors);
            });
        } catch (Throwable $e) {
            foreach ($rows as $row) {
                try {
                    Db::transaction(function () use ($batch, $row, &$counts) {
                        $counts[$this->upsertRow($batch, $row)]++;
                    });
                } catch (Throwable $rowError) {
                    $counts['failed']++;
                    $this->pushRowError($rowErrors, null, $row['work_date'] ?? null, $rowError->getMessage());
                }
            }
        }

        return $counts;
    }

    /**
     * @param  array{id:int,year_id:int,employee_id:?string}  $batch
     * @param  list<array<string, mixed>>  $rows
     * @param  array{inserted:int,updated:int,skipped:int,failed:int}  $counts
     * @param  list<array{element:?string,work_date:?string,message:string}>  $rowErrors
     */
    private function upsertChunk(array $batch, array $rows, array &$counts, array &$rowErrors): void
    {
        $mergeKeys = array_values(array_unique(array_column($rows, 'merge_key')));
        $placeholders = implode(',', array_fill(0, count($mergeKeys), '?'));
        $existingRows = Db::all(
            "SELECT * FROM records
             WHERE year_id = ? AND employee_id = ? AND merge_key IN ({$placeholders})",
            [(int) $batch['year_id'], $batch['employee_id'], ...$mergeKeys],
        );
        $existing = [];
        foreach ($existingRows as $row) {
            $existing[$row['merge_key']] = $row;
        }

        $toInsert = [];
        $now = jmr_now();

        foreach ($rows as $row) {
            $current = $existing[$row['merge_key']] ?? null;
            if ($current === null) {
                $toInsert[] = $row + [
                    'import_batch_id' => $batch['id'],
                    'is_active' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                continue;
            }

            if ($current['content_hash'] === $row['content_hash']) {
                $counts['skipped']++;
                continue;
            }

            Db::exec(
                'UPDATE records
                 SET description = ?, hour = ?, remarks = ?, content_hash = ?, import_batch_id = ?, updated_at = ?
                 WHERE id = ?',
                [
                    $row['description'],
                    $row['hour'],
                    $row['remarks'],
                    $row['content_hash'],
                    $batch['id'],
                    $now,
                    $current['id'],
                ],
            );
            $counts['updated']++;
        }

        if ($toInsert === []) {
            return;
        }

        try {
            $this->insertRows($toInsert);
            $counts['inserted'] += count($toInsert);
        } catch (PDOException $e) {
            if (! Db::isUniqueViolation($e)) {
                throw $e;
            }

            foreach ($toInsert as $row) {
                unset($row['created_at'], $row['updated_at'], $row['is_active']);
                try {
                    $counts[$this->upsertRow($batch, $row)]++;
                } catch (Throwable $rowError) {
                    $counts['failed']++;
                    $this->pushRowError($rowErrors, null, $row['work_date'] ?? null, $rowError->getMessage());
                }
            }
        }
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function insertRows(array $rows): void
    {
        $columns = [
            'year_id', 'employee_id', 'import_batch_id', 'work_date', 'location', 'project', 'item',
            'description', 'type', 'hour', 'remarks', 'category', '`group`', 'person_in_charge',
            'dimension', 'change_log', 'member', 'sheet_count', 'paper_size', 'progress', 'revision',
            'is_active', 'merge_key', 'content_hash', 'created_at', 'updated_at',
        ];
        $keys = [
            'year_id', 'employee_id', 'import_batch_id', 'work_date', 'location', 'project', 'item',
            'description', 'type', 'hour', 'remarks', 'category', 'group', 'person_in_charge',
            'dimension', 'change_log', 'member', 'sheet_count', 'paper_size', 'progress', 'revision',
            'is_active', 'merge_key', 'content_hash', 'created_at', 'updated_at',
        ];

        $rowPlaceholder = '('.implode(',', array_fill(0, count($keys), '?')).')';
        $sql = 'INSERT INTO records ('.implode(',', $columns).') VALUES '
            .implode(',', array_fill(0, count($rows), $rowPlaceholder));
        $params = [];
        foreach ($rows as $row) {
            foreach ($keys as $key) {
                $params[] = $row[$key] ?? null;
            }
        }

        Db::exec($sql, $params);
    }

    /** @return array<string, mixed> */
    private function mapRow(int $yearId, ?string $employeeId, string $workDate, SimpleXMLElement $node): array
    {
        $row = [
            'year_id' => $yearId,
            'employee_id' => $employeeId,
            'work_date' => $workDate,
            'location' => $this->normalizeKeyPart($this->childValue($node, 'Location')),
            'project' => $this->normalizeKeyPart($this->childValue($node, 'Project')),
            'item' => $this->normalizeKeyPart($this->childValue($node, 'Item')),
            'description' => $this->childValue($node, 'Description'),
            'type' => $this->normalizeKeyPart($this->childValue($node, 'Type')),
            'hour' => $this->parseHour($this->childValue($node, 'Hour')),
            'remarks' => $this->childValue($node, 'Remarks'),
            'category' => $this->normalizeKeyPart($this->childValue($node, 'Manhour_x0020_Type', 'Manhour Type')),
            'group' => $this->normalizeKeyPart($this->childValue($node, 'Group')),
            'person_in_charge' => $this->normalizeKeyPart($this->childValue(
                $node,
                'Person_x0020_in_x0020_Charge',
                'Person in Charge',
            )),
            'dimension' => $this->normalizeKeyPart($this->childValue($node, '_x0032_D_x002F_3D', '2D/3D')),
            'change_log' => $this->childValue($node, 'Change_x0020_Log', 'Change Log'),
            'member' => $this->normalizeKeyPart($this->childValue($node, 'Member')),
            'sheet_count' => $this->normalizeKeyPart($this->childValue(
                $node,
                'No_x002E__x0020_of_x0020_Sheets',
                'No. of Sheets',
            )),
            'paper_size' => $this->normalizeKeyPart($this->childValue($node, 'Paper_x0020_Size', 'Paper Size')),
            'progress' => $this->normalizeKeyPart($this->childValue($node, 'Progress')),
            'revision' => $this->normalizeKeyPart($this->childValue($node, 'Revision')),
        ];
        $row['merge_key'] = $this->mergeKey($row);
        $row['content_hash'] = $this->contentHash($row);

        return $row;
    }

    private function childValue(SimpleXMLElement $node, string ...$names): ?string
    {
        foreach ($names as $name) {
            if (! isset($node->{$name})) {
                continue;
            }
            $value = trim((string) $node->{$name});
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /** @param  array{id:int,year_id:int,employee_id:?string}  $batch */
    private function upsertRow(array $batch, array $row): string
    {
        $existing = Db::one(
            'SELECT * FROM records WHERE year_id = ? AND employee_id = ? AND merge_key = ?',
            [$row['year_id'], $row['employee_id'], $row['merge_key']],
        );

        if ($existing === null) {
            try {
                $now = jmr_now();
                $this->insertRows([$row + [
                    'import_batch_id' => $batch['id'],
                    'is_active' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]]);

                return 'inserted';
            } catch (PDOException $e) {
                if (! Db::isUniqueViolation($e)) {
                    throw $e;
                }
                $existing = Db::one(
                    'SELECT * FROM records WHERE year_id = ? AND employee_id = ? AND merge_key = ?',
                    [$row['year_id'], $row['employee_id'], $row['merge_key']],
                );
                if ($existing === null) {
                    throw $e;
                }
            }
        }

        if ($existing['content_hash'] === $row['content_hash']) {
            return 'skipped';
        }

        Db::exec(
            'UPDATE records
             SET description = ?, hour = ?, remarks = ?, change_log = ?, sheet_count = ?, progress = ?,
                 content_hash = ?, import_batch_id = ?, updated_at = ?
             WHERE id = ?',
            [
                $row['description'],
                $row['hour'],
                $row['remarks'],
                $row['change_log'],
                $row['sheet_count'],
                $row['progress'],
                $row['content_hash'],
                $batch['id'],
                jmr_now(),
                $existing['id'],
            ],
        );

        return 'updated';
    }

    private function decodeEncodedName(string $name): string
    {
        return (string) preg_replace_callback('/_x([0-9A-Fa-f]{4})_/', function (array $matches) {
            $codepoint = hexdec($matches[1]);
            if (function_exists('mb_chr')) {
                $char = mb_chr($codepoint, 'UTF-8');
                if ($char !== false) {
                    return $char;
                }
            }

            return $codepoint <= 0xFF ? chr($codepoint) : '';
        }, $name);
    }

    /** @param  list<array{element:?string,work_date:?string,message:string}>  $rowErrors */
    private function pushRowError(array &$rowErrors, ?string $element, ?string $workDate, string $message): void
    {
        if (count($rowErrors) >= self::MAX_STORED_ROW_ERRORS) {
            return;
        }

        $rowErrors[] = [
            'element' => $element,
            'work_date' => $workDate,
            'message' => mb_substr($message, 0, 500),
        ];
    }

    /** @param  array{inserted:int,updated:int,skipped:int,failed:int}  $counts */
    private function mergeCounts(array &$counts, array $chunkCounts): void
    {
        foreach (['inserted', 'updated', 'skipped', 'failed'] as $key) {
            $counts[$key] += $chunkCounts[$key];
        }
    }
}
