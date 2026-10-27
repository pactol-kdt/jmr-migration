<?php

declare(strict_types=1);

namespace Jmr\Domain;

use Jmr\Support\Account;
use Jmr\Support\Cache;
use Jmr\Support\Db;

final class RecordBrowser
{
    /** @var list<string> */
    public const SORTS = [
        'work_date', 'location', 'project', 'item', 'type', 'hour', 'category',
        'group', 'person_in_charge', 'employee_id',
    ];

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function filters(array $input, bool $withMember = false): array
    {
        $filters = [
            'search' => $this->text($input['search'] ?? null, 255),
            'year' => $this->year($input['year'] ?? null),
            'employee_id' => $this->text($input['employee_id'] ?? null, 255),
            'location' => $this->text($input['location'] ?? null, 255),
            'project' => $this->text($input['project'] ?? null, 255),
            'type' => $this->text($input['type'] ?? null, 255),
            'category' => $this->text($input['category'] ?? null, 255),
            'group' => $this->text($input['group'] ?? null, 255),
            'person_in_charge' => $this->text($input['person_in_charge'] ?? null, 255),
            'date_from' => $this->date($input['date_from'] ?? null),
            'date_to' => $this->date($input['date_to'] ?? null),
            'sort' => in_array($input['sort'] ?? '', self::SORTS, true) ? $input['sort'] : 'work_date',
            'direction' => ($input['direction'] ?? '') === 'asc' ? 'asc' : 'desc',
        ];

        if ($withMember) {
            $filters['member'] = $this->text($input['member'] ?? null, 255);
        }

        return $filters;
    }

    /** @param  array<string, mixed>  $filters */
    public function present(array $filters, string $sort = 'work_date', string $direction = 'desc'): array
    {
        return [
            'search' => $filters['search'] ?? '',
            'year' => isset($filters['year']) && $filters['year'] !== '' && $filters['year'] !== null
                ? (string) $filters['year']
                : '',
            'employee_id' => $filters['employee_id'] ?? '',
            'location' => $filters['location'] ?? '',
            'project' => $filters['project'] ?? '',
            'type' => $filters['type'] ?? '',
            'category' => $filters['category'] ?? '',
            'group' => $filters['group'] ?? '',
            'member' => $filters['member'] ?? '',
            'person_in_charge' => $filters['person_in_charge'] ?? '',
            'date_from' => $filters['date_from'] ?? '',
            'date_to' => $filters['date_to'] ?? '',
            'sort' => $sort,
            'direction' => $direction,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0:string,1:list<mixed>}
     */
    public function where(array $filters, ?Account $user): array
    {
        $clauses = ['1 = 1'];
        $params = [];

        if ($user) {
            [$sql, $groupParams] = $user->groupClause('group');
            $clauses[] = $sql;
            $params = [...$params, ...$groupParams];
        }

        if (! empty($filters['employee_id_exact'])) {
            $clauses[] = 'employee_id = ?';
            $params[] = $filters['employee_id_exact'];
        }

        if (! empty($filters['year'])) {
            $clauses[] = 'year_id = ?';
            $params[] = $this->yearId((int) $filters['year']) ?? 0;
        }

        if (! empty($filters['search'])) {
            $like = jmr_like((string) $filters['search']);
            $columns = [
                'location', 'project', 'item', 'description', 'remarks', '`group`',
                'person_in_charge', 'employee_id', 'dimension', 'change_log', 'member',
                'paper_size', 'progress', 'revision',
            ];
            $clauses[] = '('.implode(' OR ', array_map(fn ($column) => "{$column} LIKE ?", $columns)).')';
            $params = [...$params, ...array_fill(0, count($columns), $like)];
        }

        $prefixes = [
            'location' => 'location',
            'project' => 'project',
            'type' => 'type',
            'category' => 'category',
            'group' => '`group`',
            'member' => 'member',
            'person_in_charge' => 'person_in_charge',
        ];

        if (empty($filters['employee_id_exact']) && ! empty($filters['employee_id'])) {
            $clauses[] = 'employee_id LIKE ?';
            $params[] = jmr_prefix((string) $filters['employee_id']);
        }

        foreach ($prefixes as $key => $column) {
            if (! empty($filters[$key])) {
                $clauses[] = "{$column} LIKE ?";
                $params[] = jmr_prefix((string) $filters[$key]);
            }
        }

        if (! empty($filters['date_from'])) {
            $clauses[] = 'work_date >= ?';
            $params[] = $filters['date_from'];
        }
        if (! empty($filters['date_to'])) {
            $clauses[] = 'work_date <= ?';
            $params[] = $filters['date_to'];
        }

        return [implode(' AND ', $clauses), $params];
    }

    /** @param  array<string, mixed>  $filters */
    public function page(array $filters, ?Account $user, string $path, array $query): array
    {
        $sort = $filters['sort'] ?? 'work_date';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
        $tie = $sort === 'work_date' ? $direction : 'ASC';
        [$where, $params] = $this->where($filters, $user);
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = 25;
        $order = $this->orderSql($sort, $direction)." , id {$tie}";
        $rows = Db::all(
            "SELECT records.id, records.year_id, records.employee_id, records.work_date, records.location,
                    records.project, records.item, records.type, records.hour, records.category,
                    records.`group`, records.person_in_charge, records.is_active, years.year
             FROM records
             LEFT JOIN years ON years.id = records.year_id
             WHERE {$where}
             ORDER BY {$order}
             LIMIT ".($perPage + 1).' OFFSET '.(($page - 1) * $perPage),
            $params,
        );

        $hasMore = count($rows) > $perPage;
        $rows = array_slice($rows, 0, $perPage);
        $data = array_map(fn (array $row) => [
            'id' => (int) $row['id'],
            'year' => $row['year'] !== null ? (int) $row['year'] : null,
            'year_id' => (int) $row['year_id'],
            'employee_id' => $row['employee_id'],
            'work_date' => $row['work_date'] ? substr((string) $row['work_date'], 0, 10) : null,
            'location' => $row['location'],
            'project' => $row['project'],
            'item' => $row['item'],
            'type' => $row['type'],
            'hour' => $row['hour'] !== null ? number_format((float) $row['hour'], 2, '.', '') : null,
            'category' => $row['category'],
            'group' => $row['group'],
            'person_in_charge' => $row['person_in_charge'],
            'is_active' => jmr_bool($row['is_active']),
        ], $rows);

        $from = $data === [] ? null : (($page - 1) * $perPage) + 1;
        $to = $data === [] ? null : $from + count($data) - 1;

        return [
            'data' => $data,
            'current_page' => $page,
            'per_page' => $perPage,
            'from' => $from,
            'to' => $to,
            'prev_page_url' => $page > 1 ? $this->pageUrl($path, $query, $page - 1) : null,
            'next_page_url' => $hasMore ? $this->pageUrl($path, $query, $page + 1) : null,
            'path' => $path,
        ];
    }

    /** @param  array<string, mixed>  $filters */
    public function hourTotal(array $filters, ?Account $user): string
    {
        $scope = $this->statScope($filters);
        $scope['__viewer'] = $user?->viewerKey() ?? 'all';
        $total = Cache::remember('hours', $scope, function () use ($filters, $user) {
            [$where, $params] = $this->where($filters, $user);

            return Db::value(
                "SELECT COALESCE(SUM(hour), 0) FROM records WHERE {$where} AND is_active = 1",
                $params,
            );
        });

        return number_format((float) $total, 2, '.', '');
    }

    /** @param  array<string, mixed>  $filters */
    public function matchingCount(array $filters, ?Account $user): int
    {
        $scope = $this->statScope($filters);
        $scope['__viewer'] = $user?->viewerKey() ?? 'all';

        return (int) Cache::remember('count', $scope, function () use ($filters, $user) {
            [$where, $params] = $this->where($filters, $user);

            return Db::value("SELECT COUNT(*) FROM records WHERE {$where}", $params);
        });
    }

    /** @return list<array{id:int,year:int}> */
    public function years(?string $employeeId = null): array
    {
        if ($employeeId !== null) {
            $rows = Db::all(
                'SELECT id, year FROM years
                 WHERE id IN (SELECT DISTINCT year_id FROM records WHERE employee_id = ?)
                 ORDER BY year DESC',
                [$employeeId],
            );
        } else {
            $rows = Db::all('SELECT id, year FROM years ORDER BY year DESC');
        }

        return array_map(fn (array $row) => [
            'id' => (int) $row['id'],
            'year' => (int) $row['year'],
        ], $rows);
    }

    /** @param  array<string, mixed>  $filters */
    public function filterOptions(array $filters, ?Account $user): array
    {
        $scope = [
            'year' => (string) ($filters['year'] ?? ''),
            'employee_id_exact' => (string) ($filters['employee_id_exact'] ?? ''),
            '__viewer' => $user?->viewerKey() ?? 'all',
        ];

        return Cache::remember('filter-options', $scope, function () use ($filters, $user) {
            $scoped = [
                'year' => $filters['year'] ?? null,
                'employee_id_exact' => $filters['employee_id_exact'] ?? null,
            ];
            [$where, $params] = $this->where($scoped, $user);
            $distinct = function (string $column) use ($where, $params) {
                $rows = Db::all(
                    "SELECT DISTINCT {$column} AS value FROM records
                     WHERE {$where} AND {$column} IS NOT NULL AND {$column} != ''
                     ORDER BY {$column}",
                    $params,
                );

                return array_values(array_map(fn (array $row) => $row['value'], $rows));
            };

            return [
                'locations' => $distinct('location'),
                'projects' => $distinct('project'),
                'types' => $distinct('type'),
                'categories' => $distinct('category'),
                'groups' => $distinct('`group`'),
                'people_in_charge' => $distinct('person_in_charge'),
                'employee_ids' => $distinct('employee_id'),
            ];
        });
    }

    /** @param  array<string, mixed>  $filters */
    public function csv(array $filters, ?Account $user): string
    {
        $sort = $filters['sort'] ?? 'work_date';
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';
        [$where, $params] = $this->where($filters, $user);
        $handle = fopen('php://temp', 'w+');
        fputcsv($handle, [
            'id', 'year', 'employee_id', 'work_date', 'location', 'project', 'item', 'description',
            'type', 'hour', 'remarks', 'category', 'group', 'person_in_charge', 'dimension',
            'change_log', 'member', 'sheet_count', 'paper_size', 'progress', 'revision', 'active',
        ]);

        $rows = Db::all(
            "SELECT records.*, years.year AS year_number
             FROM records
             LEFT JOIN years ON years.id = records.year_id
             WHERE {$where}
             ORDER BY ".$this->orderSql($sort, $direction).', records.id',
            $params,
        );

        foreach ($rows as $record) {
            fputcsv($handle, [
                $record['id'],
                $record['year_number'],
                $record['employee_id'],
                $record['work_date'] ? substr((string) $record['work_date'], 0, 10) : null,
                $record['location'],
                $record['project'],
                $record['item'],
                $record['description'],
                $record['type'],
                $record['hour'],
                $record['remarks'],
                $record['category'],
                $record['group'],
                $record['person_in_charge'],
                $record['dimension'],
                $record['change_log'],
                $record['member'],
                $record['sheet_count'],
                $record['paper_size'],
                $record['progress'],
                $record['revision'],
                jmr_bool($record['is_active']) ? 'active' : 'inactive',
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $csv;
    }

    public function show(int $id): ?array
    {
        return Db::one(
            'SELECT records.*, years.year AS year_number, years.id AS year_row_id,
                    import_batches.id AS batch_id, import_batches.original_filename, import_batches.status AS batch_status,
                    import_batches.created_at AS batch_created_at, users.name AS imported_by
             FROM records
             LEFT JOIN years ON years.id = records.year_id
             LEFT JOIN import_batches ON import_batches.id = records.import_batch_id
             LEFT JOIN users ON users.id = import_batches.user_id
             WHERE records.id = ?',
            [$id],
        );
    }

    public function yearId(int $year): ?int
    {
        $id = Db::value('SELECT id FROM years WHERE year = ?', [$year]);

        return $id === null ? null : (int) $id;
    }

    /** @param  array<string, mixed>  $filters */
    private function statScope(array $filters): array
    {
        return [
            'search' => (string) ($filters['search'] ?? ''),
            'year' => (string) ($filters['year'] ?? ''),
            'employee_id' => (string) ($filters['employee_id'] ?? ''),
            'employee_id_exact' => (string) ($filters['employee_id_exact'] ?? ''),
            'location' => (string) ($filters['location'] ?? ''),
            'project' => (string) ($filters['project'] ?? ''),
            'type' => (string) ($filters['type'] ?? ''),
            'category' => (string) ($filters['category'] ?? ''),
            'group' => (string) ($filters['group'] ?? ''),
            'person_in_charge' => (string) ($filters['person_in_charge'] ?? ''),
            'date_from' => (string) ($filters['date_from'] ?? ''),
            'date_to' => (string) ($filters['date_to'] ?? ''),
        ];
    }

    private function orderSql(string $sort, string $direction): string
    {
        $column = $sort === 'group' ? 'records.`group`' : 'records.'.$sort;
        if ($sort === 'employee_id') {
            $cast = Db::driver() === 'mysql' ? 'UNSIGNED' : 'INTEGER';

            return "CAST(records.employee_id AS {$cast}) {$direction}";
        }

        return "{$column} {$direction}";
    }

    private function pageUrl(string $path, array $query, int $page): string
    {
        $query['page'] = $page;
        unset($query['employee_id_exact']);

        return $path.'?'.http_build_query($query);
    }

    private function text(mixed $value, int $max): string
    {
        $text = trim((string) ($value ?? ''));

        return mb_substr($text, 0, $max);
    }

    private function year(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $year = (int) $value;

        return ($year >= 1900 && $year <= 2100) ? $year : null;
    }

    private function date(mixed $value): string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $text)) {
            return '';
        }

        return $text;
    }
}
