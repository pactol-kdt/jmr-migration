<?php

declare(strict_types=1);

namespace Jmr\Domain;

use Jmr\Support\Account;
use Jmr\Support\Db;
use SimpleXMLElement;

final class DirectorySync
{
    public const RESIGNED_ON_OR_BEFORE = '2026-09-29';

    public function __construct(private XmlImporter $xml) {}

    /** @return array{created:int,updated:int,skipped:int} */
    public function importFile(string $absolutePath): array
    {
        $xml = $this->xml->loadXml($absolutePath);
        $nodes = $this->employeeNodes($xml);
        if ($nodes === []) {
            throw new \RuntimeException($this->emptyFileMessage($xml));
        }

        $counts = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        Db::transaction(function () use ($nodes, &$counts) {
            foreach ($nodes as $node) {
                $employeeId = $this->employeeId($node);
                if ($employeeId === null) {
                    $counts['skipped']++;
                    continue;
                }

                $profile = [
                    'name' => $this->text($node, 'Name'),
                    'username' => $this->text($node, 'Username') ?? $this->text($node, 'User'),
                    'pc' => $this->text($node, 'PC'),
                    'group_name' => $this->text($node, 'Group'),
                    'designation' => $this->text($node, 'Designation') ?? $this->text($node, 'Desig'),
                    'access_type' => $this->text($node, 'AccessType'),
                ];

                $existing = Db::one(
                    'SELECT id FROM users WHERE employee_id = ?',
                    [$employeeId],
                );

                if ($existing === null) {
                    $this->insertEmployee($employeeId, $profile);
                    $counts['created']++;
                    continue;
                }

                $username = $profile['username'];
                if ($username !== null) {
                    $holder = Db::value(
                        'SELECT id FROM users WHERE username = ? AND employee_id != ?',
                        [$username, $employeeId],
                    );
                    if ($holder !== null) {
                        $username = null;
                    }
                }

                Db::exec(
                    'UPDATE users
                     SET name = ?, username = ?, pc = ?, group_name = ?, designation = ?, access_type = ?, updated_at = ?
                     WHERE employee_id = ?',
                    [
                        $profile['name'],
                        $username,
                        $profile['pc'],
                        $profile['group_name'],
                        $profile['designation'],
                        $profile['access_type'],
                        jmr_now(),
                        $employeeId,
                    ],
                );
                $counts['updated']++;
            }
        });

        if ($counts['created'] === 0 && $counts['updated'] === 0) {
            throw new \RuntimeException(
                'Employee rows were found, but none had a usable ID. Check that each row includes an ID field.',
            );
        }

        return $counts;
    }

    /**
     * @param  iterable<int, array{employee_id:string|int,name?:string|null,username?:string|null,group_name?:string|null,designation?:string|null,resigned_on?:string|null}>  $profiles
     * @return array{updated:int,resigned:int,unchanged:int,skipped:list<string>}
     */
    public function syncKdt(iterable $profiles): array
    {
        $directory = [];
        foreach ($profiles as $profile) {
            $employeeId = trim((string) ($profile['employee_id'] ?? ''));
            if ($employeeId === '') {
                continue;
            }
            $resignedOn = $this->date($profile['resigned_on'] ?? null);
            $directory[$employeeId] = [
                'name' => $this->clean($profile['name'] ?? null),
                'username' => $this->clean($profile['username'] ?? null),
                'group_name' => $this->clean($profile['group_name'] ?? null),
                'designation' => $this->clean($profile['designation'] ?? null),
                'directory_active' => ! $this->hasResigned($resignedOn),
                'resigned_on' => $resignedOn,
            ];
        }

        return Db::transaction(function () use ($directory) {
            $employees = Db::all('SELECT * FROM users WHERE employee_id IS NOT NULL');
            $skipped = $this->skippedIds($employees, $directory);
            $updated = 0;
            $resigned = 0;
            $unchanged = 0;

            foreach ($employees as $employee) {
                $employeeId = (string) $employee['employee_id'];
                $profile = $directory[$employeeId] ?? null;
                if ($profile === null || isset($skipped[$employeeId])) {
                    if ($profile === null) {
                        $unchanged++;
                    }
                    continue;
                }
                if ($profile['username'] !== $employee['username']) {
                    Db::exec('UPDATE users SET username = NULL, updated_at = ? WHERE id = ?', [jmr_now(), $employee['id']]);
                }
            }

            $employees = Db::all('SELECT * FROM users WHERE employee_id IS NOT NULL');
            foreach ($employees as $employee) {
                $employeeId = (string) $employee['employee_id'];
                $profile = $directory[$employeeId] ?? null;
                if ($profile === null || isset($skipped[$employeeId])) {
                    continue;
                }

                Db::exec(
                    'UPDATE users
                     SET name = COALESCE(?, name), username = ?, group_name = ?, designation = ?,
                         directory_active = ?, resigned_on = ?, updated_at = ?
                     WHERE id = ?',
                    [
                        $profile['name'],
                        $profile['username'],
                        $profile['group_name'],
                        $profile['designation'],
                        $profile['directory_active'] ? 1 : 0,
                        $profile['resigned_on'],
                        jmr_now(),
                        $employee['id'],
                    ],
                );
                $updated++;
                if ($profile['directory_active'] === false) {
                    $resigned++;
                }
            }

            return [
                'updated' => $updated,
                'resigned' => $resigned,
                'unchanged' => $unchanged,
                'skipped' => array_keys($skipped),
            ];
        });
    }

    public static function ensureEmployee(string $employeeId): void
    {
        $existing = Db::value('SELECT id FROM users WHERE employee_id = ?', [$employeeId]);
        if ($existing !== null) {
            return;
        }

        $now = jmr_now();
        Db::exec(
            'INSERT INTO users (employee_id, user_hash, role_id, can_edit, created_at, updated_at)
             VALUES (?, ?, ?, 0, ?, ?)',
            [$employeeId, self::userHash(), Account::roleId(Account::MEMBER), $now, $now],
        );
    }

    public static function ensureYear(int $year): array
    {
        $row = Db::one('SELECT * FROM years WHERE year = ?', [$year]);
        if ($row) {
            return $row;
        }

        $now = jmr_now();
        $id = Db::insert(
            'INSERT INTO years (year, created_at, updated_at) VALUES (?, ?, ?)',
            [$year, $now, $now],
        );
        $created = Db::one('SELECT * FROM years WHERE id = ?', [$id]);
        if ($created === null) {
            throw new \RuntimeException('Unable to save year '.$year.'.');
        }

        return $created;
    }

    /** @param  array<string, ?string>  $profile */
    private function insertEmployee(string $employeeId, array $profile): void
    {
        $username = $profile['username'];
        if ($username !== null && Db::value('SELECT id FROM users WHERE username = ?', [$username]) !== null) {
            $username = null;
        }

        $now = jmr_now();
        Db::exec(
            'INSERT INTO users
             (employee_id, user_hash, name, username, pc, group_name, designation, access_type, role_id, can_edit, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)',
            [
                $employeeId,
                self::userHash(),
                $profile['name'],
                $username,
                $profile['pc'],
                $profile['group_name'],
                $profile['designation'],
                $profile['access_type'],
                Account::roleId(Account::MEMBER),
                $now,
                $now,
            ],
        );
    }

    private static function userHash(): string
    {
        do {
            $hash = hash('sha256', jmr_uuid());
        } while (Db::value('SELECT id FROM users WHERE user_hash = ?', [$hash]) !== null);

        return $hash;
    }

    /** @return list<SimpleXMLElement> */
    private function employeeNodes(SimpleXMLElement $xml): array
    {
        // Prefer known directory table names. Microsoft DataSet / DiffGram
        // exports often nest rows under a default namespace.
        foreach (['Employee', 'Employees', 'emp_prof', 'Emp_Prof'] as $table) {
            $matches = $xml->xpath("//*[local-name()='{$table}']");
            if (! is_array($matches) || $matches === []) {
                continue;
            }
            $rows = array_values(array_filter(
                $matches,
                fn (SimpleXMLElement $node) => $this->looksLikeEmployeeRow($node),
            ));
            if ($rows !== []) {
                return $rows;
            }
        }

        $idFields = "local-name()='ID' or local-name()='Id' or local-name()='EmployeeID' or local-name()='EmployeeId' or local-name()='fldEmployeeNum'";
        $idNodes = $xml->xpath("//*[{$idFields}]");
        if (! is_array($idNodes) || $idNodes === []) {
            return [];
        }

        $rows = [];
        $seen = [];
        foreach ($idNodes as $idNode) {
            $parent = $idNode->xpath('..');
            $node = is_array($parent) ? ($parent[0] ?? null) : null;
            if (! $node instanceof SimpleXMLElement || ! $this->looksLikeEmployeeRow($node)) {
                continue;
            }
            $key = spl_object_id($node);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $rows[] = $node;
        }

        return $rows;
    }

    private function looksLikeEmployeeRow(SimpleXMLElement $node): bool
    {
        $name = $node->getName();
        if ($name === '' || str_contains(strtolower($name), 'schema')) {
            return false;
        }
        if ($this->xml->looksLikeDateElement($name) || $this->xml->decodeDateElementName($name) !== null) {
            return false;
        }
        if ($this->employeeId($node) === null) {
            return false;
        }

        foreach (['Name', 'Username', 'User', 'Group', 'Designation', 'Desig', 'AccessType', 'PC'] as $field) {
            if ($this->text($node, $field) !== null) {
                return true;
            }
        }

        return false;
    }

    private function employeeId(SimpleXMLElement $node): ?string
    {
        foreach (['ID', 'Id', 'EmployeeID', 'EmployeeId', 'employee_id', 'fldEmployeeNum'] as $field) {
            $value = $this->text($node, $field);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    private function emptyFileMessage(SimpleXMLElement $xml): string
    {
        if ($this->looksLikeManhourFile($xml)) {
            return 'That file looks like a man-hour export. Use Upload on the Import page for those files. Employee details need a directory XML with Employee rows (ID, Name, Username, Group, Designation).';
        }

        $names = $this->sampleRowNames($xml);
        if ($names === []) {
            return 'No employee rows were found in that file.';
        }

        return 'No employee rows were found in that file. Found row types: '
            .implode(', ', $names)
            .'. Expected directory rows with an ID and Name/Username.';
    }

    private function looksLikeManhourFile(SimpleXMLElement $xml): bool
    {
        $checked = 0;
        $dateish = 0;
        foreach ($this->candidateDataRows($xml) as $node) {
            $checked++;
            $name = $node->getName();
            if ($this->xml->looksLikeDateElement($name) || $this->xml->decodeDateElementName($name) !== null) {
                $dateish++;
            }
            if ($checked >= 30) {
                break;
            }
        }

        return $checked > 0 && $dateish >= max(1, (int) ceil($checked * 0.5));
    }

    /** @return list<string> */
    private function sampleRowNames(SimpleXMLElement $xml): array
    {
        $counts = [];
        foreach ($this->candidateDataRows($xml) as $node) {
            $name = $node->getName();
            if ($name === '' || str_contains(strtolower($name), 'schema')) {
                continue;
            }
            $counts[$name] = ($counts[$name] ?? 0) + 1;
            if (count($counts) >= 8) {
                break;
            }
        }

        arsort($counts);
        $labels = [];
        foreach (array_slice($counts, 0, 5, true) as $name => $count) {
            $labels[] = $name.' ('.$count.')';
        }

        return $labels;
    }

    /** @return list<SimpleXMLElement> */
    private function candidateDataRows(SimpleXMLElement $xml): array
    {
        $rows = [];
        $diffgram = $xml->xpath("//*[local-name()='diffgram']/*");
        if (is_array($diffgram) && $diffgram !== []) {
            foreach ($diffgram as $dataset) {
                foreach ($dataset->children() as $child) {
                    $rows[] = $child;
                    if (count($rows) >= 40) {
                        return $rows;
                    }
                }
            }
        }

        foreach ($xml->children() as $child) {
            $name = strtolower($child->getName());
            if (str_contains($name, 'schema') || $name === 'diffgram') {
                continue;
            }
            $rows[] = $child;
            if (count($rows) >= 40) {
                break;
            }
        }

        return $rows;
    }

    private function text(SimpleXMLElement $node, string $name): ?string
    {
        if (isset($node->{$name})) {
            $value = trim((string) $node->{$name});

            return $value === '' ? null : $value;
        }

        foreach ($node->children() as $child) {
            if (strcasecmp($child->getName(), $name) === 0) {
                $value = trim((string) $child);

                return $value === '' ? null : $value;
            }
        }

        foreach ($node->getNamespaces(true) as $namespace) {
            foreach ($node->children($namespace) as $child) {
                if (strcasecmp($child->getName(), $name) === 0) {
                    $value = trim((string) $child);

                    return $value === '' ? null : $value;
                }
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $employees
     * @param  array<string, array{username:?string}>  $directory
     * @return array<string, true>
     */
    private function skippedIds(array $employees, array $directory): array
    {
        $wantedBy = [];
        foreach ($employees as $employee) {
            $employeeId = (string) $employee['employee_id'];
            $username = $directory[$employeeId]['username'] ?? null;
            if ($username === null) {
                continue;
            }
            $wantedBy[mb_strtolower($username)][] = $employeeId;
        }

        $skipped = [];
        foreach ($wantedBy as $ids) {
            if (count($ids) < 2) {
                continue;
            }
            foreach ($ids as $employeeId) {
                $skipped[$employeeId] = true;
            }
        }

        $heldBy = [];
        foreach ($employees as $employee) {
            $username = $this->clean($employee['username']);
            if ($username === null) {
                continue;
            }
            $heldBy[mb_strtolower($username)] = (string) $employee['employee_id'];
        }

        foreach ($directory as $employeeId => $profile) {
            $employeeId = (string) $employeeId;
            if ($profile['username'] === null || isset($skipped[$employeeId])) {
                continue;
            }
            $holder = $heldBy[mb_strtolower($profile['username'])] ?? null;
            if ($holder === null || $holder === $employeeId) {
                continue;
            }
            $holderProfile = $directory[$holder] ?? null;
            $holderReleases = $holderProfile !== null
                && ! isset($skipped[$holder])
                && mb_strtolower((string) $holderProfile['username']) !== mb_strtolower($profile['username']);
            if (! $holderReleases) {
                $skipped[$employeeId] = true;
            }
        }

        return $skipped;
    }

    private function clean(mixed $value): ?string
    {
        $text = trim((string) $value);
        if ($text === '' || $text === '-') {
            return null;
        }

        return $text;
    }

    private function hasResigned(?string $resignedOn): bool
    {
        return $resignedOn !== null && $resignedOn <= self::RESIGNED_ON_OR_BEFORE;
    }

    private function date(mixed $value): ?string
    {
        if ($value === null || $value === false) {
            return null;
        }
        $text = trim((string) $value);
        if ($text === '' || $text === '-' || $text === '0' || strcasecmp($text, 'false') === 0 || str_starts_with($text, '0000-00-00')) {
            return null;
        }
        $date = substr($text, 0, 10);
        $parsed = \DateTime::createFromFormat('!Y-m-d', $date);
        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            return null;
        }

        return $date;
    }
}
