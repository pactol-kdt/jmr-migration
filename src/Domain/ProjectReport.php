<?php

declare(strict_types=1);

namespace Jmr\Domain;

use Jmr\Support\Account;
use Jmr\Support\Cache;
use Jmr\Support\Db;

final class ProjectReport
{
    public function __construct(private RecordBrowser $records) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{projects:list<array{project:string,total:string,months:list<string>}>,totals:array{total:string,months:list<string>}}
     */
    public function report(array $filters, ?Account $user): array
    {
        $projectSql = "COALESCE(NULLIF(project, ''), '(No project)')";
        $monthSql = $this->monthSql();
        [$where, $params] = $this->records->where($filters, $user);
        $buckets = Db::all(
            "SELECT {$projectSql} AS project_name, {$monthSql} AS work_month, COALESCE(SUM(hour), 0) AS hours
             FROM records
             WHERE {$where} AND is_active = 1
             GROUP BY {$projectSql}, {$monthSql}",
            $params,
        );

        return $this->pivot($buckets, 'project_name', 'project', 'total');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{lines:list<array{item:string,description:string,type:string,subtotal:string,months:list<string>}>,subtotal:array{total:string,months:list<string>}}
     */
    public function breakdown(array $filters, string $project, ?Account $user): array
    {
        $scoped = $filters;
        unset($scoped['project']);
        [$where, $params] = $this->records->where($scoped, $user);
        [$projectSql, $projectParams] = $this->projectConstraint($project);
        $itemSql = "COALESCE(NULLIF(item, ''), '(No item of works)')";
        $descriptionSql = "COALESCE(NULLIF(description, ''), '(No job request description)')";
        $typeSql = "COALESCE(NULLIF(type, ''), '(No type of works)')";
        $monthSql = $this->monthSql();

        $buckets = Db::all(
            "SELECT {$itemSql} AS item_name, {$descriptionSql} AS description_name, {$typeSql} AS type_name,
                    {$monthSql} AS work_month, COALESCE(SUM(hour), 0) AS hours
             FROM records
             WHERE {$where} AND is_active = 1 AND {$projectSql}
             GROUP BY {$itemSql}, {$descriptionSql}, {$typeSql}, {$monthSql}",
            [...$params, ...$projectParams],
        );

        $rows = [];
        foreach ($buckets as $bucket) {
            $item = (string) $bucket['item_name'];
            $description = (string) $bucket['description_name'];
            $type = (string) $bucket['type_name'];
            $key = $item."\0".$description."\0".$type;
            $month = (int) $bucket['work_month'];
            if (! isset($rows[$key])) {
                $rows[$key] = [
                    'item' => $item,
                    'description' => $description,
                    'type' => $type,
                    'subtotal' => 0.0,
                    'months' => array_fill(0, 12, 0.0),
                ];
            }
            if ($month < 1 || $month > 12) {
                continue;
            }
            $hours = (float) $bucket['hours'];
            $rows[$key]['months'][$month - 1] += $hours;
            $rows[$key]['subtotal'] += $hours;
        }

        $lines = array_values($rows);
        usort($lines, fn (array $a, array $b) => strnatcasecmp($a['item'], $b['item'])
            ?: strnatcasecmp($a['description'], $b['description'])
            ?: strnatcasecmp($a['type'], $b['type']));

        $subtotal = ['total' => 0.0, 'months' => array_fill(0, 12, 0.0)];
        foreach ($lines as $index => $line) {
            $subtotal['total'] += $line['subtotal'];
            foreach ($line['months'] as $monthIndex => $hours) {
                $subtotal['months'][$monthIndex] += $hours;
                $lines[$index]['months'][$monthIndex] = $this->hours($hours);
            }
            $lines[$index]['subtotal'] = $this->hours($line['subtotal']);
        }

        return [
            'lines' => $lines,
            'subtotal' => [
                'total' => $this->hours($subtotal['total']),
                'months' => array_map(fn (float $hours) => $this->hours($hours), $subtotal['months']),
            ],
        ];
    }

    /** @return array{total:string,months:list<string>} */
    public function emptyTotals(): array
    {
        return [
            'total' => '0.00',
            'months' => array_fill(0, 12, '0.00'),
        ];
    }

    /**
     * @param  array<string, mixed>  $presented
     * @param  array<string, mixed>  $filters
     */
    public function cachedReport(array $presented, array $filters, ?Account $user): array
    {
        $presented['__viewer'] = $user?->viewerKey() ?? 'all';

        return Cache::remember('project-months', $presented, fn () => $this->report($filters, $user));
    }

    /** @param  list<array<string, mixed>>  $buckets */
    private function pivot(array $buckets, string $nameKey, string $labelKey, string $totalKey): array
    {
        $rows = [];
        foreach ($buckets as $bucket) {
            $name = (string) $bucket[$nameKey];
            $month = (int) $bucket['work_month'];
            if (! isset($rows[$name])) {
                $rows[$name] = [
                    $labelKey => $name,
                    $totalKey => 0.0,
                    'months' => array_fill(0, 12, 0.0),
                ];
            }
            if ($month < 1 || $month > 12) {
                continue;
            }
            $hours = (float) $bucket['hours'];
            $rows[$name]['months'][$month - 1] += $hours;
            $rows[$name][$totalKey] += $hours;
        }

        $projects = array_values($rows);
        usort($projects, fn (array $a, array $b) => strnatcasecmp($a[$labelKey], $b[$labelKey]));
        $totals = ['total' => 0.0, 'months' => array_fill(0, 12, 0.0)];
        foreach ($projects as $index => $project) {
            $totals['total'] += $project[$totalKey];
            foreach ($project['months'] as $monthIndex => $hours) {
                $totals['months'][$monthIndex] += $hours;
                $projects[$index]['months'][$monthIndex] = $this->hours($hours);
            }
            $projects[$index][$totalKey] = $this->hours($project[$totalKey]);
        }

        return [
            'projects' => $projects,
            'totals' => [
                'total' => $this->hours($totals['total']),
                'months' => array_map(fn (float $hours) => $this->hours($hours), $totals['months']),
            ],
        ];
    }

    /** @return array{0:string,1:list<mixed>} */
    private function projectConstraint(string $project): array
    {
        if ($project === '(No project)') {
            return ["(project IS NULL OR project = '')", []];
        }

        return ['project = ?', [$project]];
    }

    private function monthSql(): string
    {
        return Db::driver() === 'mysql'
            ? 'MONTH(work_date)'
            : "CAST(strftime('%m', work_date) AS INTEGER)";
    }

    private function hours(float $hours): string
    {
        return number_format($hours, 2, '.', '');
    }
}
