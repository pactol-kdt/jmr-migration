<?php

declare(strict_types=1);

namespace Jmr\Http;

use Jmr\Domain\DirectorySync;
use Jmr\Domain\ImportWorker;
use Jmr\Domain\ProjectReport;
use Jmr\Domain\RecordBrowser;
use Jmr\Domain\XmlImporter;
use Jmr\Support\Account;
use Jmr\Support\Cache;
use Jmr\Support\Db;
use Jmr\Support\Spreadsheet;
use Throwable;

final class Actions
{
    private const MAX_FILES_PER_BATCH = 20;

    private const MAX_PENDING_FILES = 500;

    public function __construct(
        private Request $request,
        private RecordBrowser $records,
        private ProjectReport $projects,
        private XmlImporter $xml,
        private DirectorySync $directory,
    ) {}

    public function dispatch(): void
    {
        $method = $this->request->method;
        $path = $this->request->path;

        $public = [
            'GET /login',
            'POST /login',
            'GET /forgot-password',
            'POST /forgot-password',
            'POST /reset-password',
        ];
        $isPublic = in_array($method.' '.$path, $public, true)
            || ($method === 'GET' && preg_match('#^/reset-password/#', $path));

        if (! $isPublic && $this->request->user === null) {
            Response::error('Unauthenticated.', 401);
        }

        if ($method !== 'GET' && ! $this->csrfOk()) {
            Response::error('Page expired. Refresh and try again.', 419);
        }

        $routes = [
            ['GET', '#^/$#', fn () => Response::redirect('/records')],
            ['GET', '#^/dashboard$#', fn () => Response::redirect('/records')],
            ['GET', '#^/login$#', fn () => $this->loginForm()],
            ['POST', '#^/login$#', fn () => $this->login()],
            ['POST', '#^/logout$#', fn () => $this->logout()],
            ['GET', '#^/forgot-password$#', fn () => Response::page($this->request, [])],
            ['POST', '#^/forgot-password$#', fn () => $this->forgotPassword()],
            ['GET', '#^/reset-password/([^/]+)$#', fn (array $m) => $this->resetForm($m[1])],
            ['POST', '#^/reset-password$#', fn () => $this->resetPassword()],
            ['GET', '#^/confirm-password$#', fn () => Response::page($this->request, [])],
            ['POST', '#^/confirm-password$#', fn () => $this->confirmPassword()],
            ['GET', '#^/verify-email$#', fn () => Response::page($this->request, [])],
            ['POST', '#^/email/verification-notification$#', fn () => Response::redirect('/profile', [], 'Email verification is not used for this app.')],
            ['GET', '#^/profile$#', fn () => Response::page($this->request, ['mustVerifyEmail' => false])],
            ['PATCH', '#^/profile$#', fn () => $this->updateProfile()],
            ['DELETE', '#^/profile$#', fn () => $this->deleteProfile()],
            ['PUT', '#^/password$#', fn () => $this->updatePassword()],
            ['GET', '#^/records/export$#', fn () => $this->exportRecords()],
            ['GET', '#^/records/(\d+)$#', fn (array $m) => $this->showRecord((int) $m[1])],
            ['GET', '#^/records$#', fn () => $this->recordIndex()],
            ['GET', '#^/projects/export$#', fn () => $this->exportProjects()],
            ['GET', '#^/projects/breakdown$#', fn () => $this->projectBreakdown()],
            ['GET', '#^/projects$#', fn () => $this->projectIndex()],
            ['POST', '#^/employees/import$#', fn () => $this->importEmployees()],
            ['PATCH', '#^/employees/([^/]+)/records/(\d+)$#', fn (array $m) => $this->updateEmployeeRecord($m[1], (int) $m[2])],
            ['GET', '#^/employees/([^/]+)$#', fn (array $m) => $this->showEmployee($m[1])],
            ['GET', '#^/employees$#', fn () => $this->employeeIndex()],
            ['PATCH', '#^/control/(\d+)$#', fn (array $m) => $this->updateControl((int) $m[1])],
            ['GET', '#^/control$#', fn () => $this->controlIndex()],
            ['GET', '#^/years/(\d+)/uploads$#', fn (array $m) => $this->yearHistory((int) $m[1])],
            ['GET', '#^/years/(\d+)/imports/(\d+)$#', fn (array $m) => $this->showImport((int) $m[1], (int) $m[2])],
            ['GET', '#^/years/(\d+)/records/export$#', fn (array $m) => $this->legacyYearRedirect((int) $m[1], '/records/export')],
            ['GET', '#^/years/(\d+)/records/(\d+)$#', fn (array $m) => $this->legacyRecord((int) $m[1], (int) $m[2])],
            ['GET', '#^/years/(\d+)/records$#', fn (array $m) => $this->legacyYearRedirect((int) $m[1], '/records')],
            ['GET', '#^/years/(\d+)$#', fn (array $m) => $this->legacyYearRedirect((int) $m[1], '/records')],
            ['GET', '#^/years$#', fn () => $this->yearIndex()],
            ['GET', '#^/import$#', fn () => $this->importCreate()],
            ['POST', '#^/import/analyze$#', fn () => $this->analyze()],
            ['GET', '#^/import/confirm$#', fn () => $this->confirmImport()],
            ['POST', '#^/import/confirm$#', fn () => $this->storeImport()],
            ['DELETE', '#^/import/confirm$#', fn () => $this->cancelImport()],
            ['GET', '#^/import/summary$#', fn () => $this->importSummary()],
        ];

        foreach ($routes as [$routeMethod, $pattern, $handler]) {
            if ($routeMethod !== $method || ! preg_match($pattern, $path, $matches)) {
                continue;
            }
            $handler($matches);

            return;
        }

        Response::error('Not found.', 404);
    }

    private function loginForm(): void
    {
        if ($this->request->user) {
            Response::redirect('/records');
        }

        Response::page($this->request, [
            'canResetPassword' => true,
        ]);
    }

    private function login(): void
    {
        $email = trim((string) $this->request->input('email', ''));
        $password = (string) $this->request->input('password', '');
        $account = $email !== '' ? Account::findByEmail($email) : null;

        if ($account === null || ! is_string($account->row['password']) || ! password_verify($password, $account->row['password'])) {
            Response::invalid(['email' => 'These credentials do not match our records.']);
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = $account->id();
        $this->request->user = $account;
        Response::redirect('/records');
    }

    private function logout(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
        Response::redirect('/login');
    }

    private function forgotPassword(): void
    {
        $email = trim((string) $this->request->input('email', ''));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::invalid(['email' => 'Enter a valid email address.']);
        }

        $account = Account::findByEmail($email);
        if ($account !== null) {
            $token = bin2hex(random_bytes(32));
            Db::exec('DELETE FROM password_reset_tokens WHERE email = ?', [$email]);
            Db::exec(
                'INSERT INTO password_reset_tokens (email, token, created_at) VALUES (?, ?, ?)',
                [$email, hash('sha256', $token), jmr_now()],
            );
            $link = '/reset-password/'.$token.'?email='.rawurlencode($email);
            jmr_log('info', 'Password reset link', ['email' => $email, 'link' => $link]);
        }

        Response::redirect('/forgot-password', [], 'If that email is registered, a reset link has been written to storage/logs/app.log.');
    }

    private function resetForm(string $token): void
    {
        Response::page($this->request, [
            'token' => $token,
            'email' => (string) $this->request->input('email', ''),
        ]);
    }

    private function resetPassword(): void
    {
        $email = trim((string) $this->request->input('email', ''));
        $token = (string) $this->request->input('token', '');
        $password = (string) $this->request->input('password', '');
        $confirmation = (string) $this->request->input('password_confirmation', '');
        $errors = [];
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid email address.';
        }
        if (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        } elseif ($password !== $confirmation) {
            $errors['password'] = 'Password confirmation does not match.';
        }
        if ($errors !== []) {
            Response::invalid($errors);
        }

        $row = Db::one('SELECT * FROM password_reset_tokens WHERE email = ?', [$email]);
        $fresh = $row && hash_equals((string) $row['token'], hash('sha256', $token))
            && strtotime((string) $row['created_at']) >= time() - 3600;
        if (! $fresh) {
            Response::invalid(['email' => 'This password reset link is invalid.']);
        }

        Db::exec(
            'UPDATE users SET password = ?, updated_at = ? WHERE email = ?',
            [password_hash($password, PASSWORD_BCRYPT), jmr_now(), $email],
        );
        Db::exec('DELETE FROM password_reset_tokens WHERE email = ?', [$email]);
        Response::redirect('/login', [], 'Your password has been reset.');
    }

    private function confirmPassword(): void
    {
        $user = $this->request->user;
        $password = (string) $this->request->input('password', '');
        if ($user === null || ! password_verify($password, (string) $user->row['password'])) {
            Response::invalid(['password' => 'The password is incorrect.']);
        }
        $_SESSION['password_confirmed_at'] = time();
        Response::redirect('/records');
    }

    private function updateProfile(): void
    {
        $user = $this->request->user;
        $name = trim((string) $this->request->input('name', ''));
        $email = trim((string) $this->request->input('email', ''));
        $errors = [];
        if ($name === '') {
            $errors['name'] = 'Name is required.';
        }
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid email address.';
        } elseif (Db::value('SELECT id FROM users WHERE email = ? AND id != ?', [$email, $user->id()]) !== null) {
            $errors['email'] = 'That email is already in use.';
        }
        if ($errors !== []) {
            Response::invalid($errors);
        }

        $verified = $email === (string) $user->row['email'] ? $user->row['email_verified_at'] : null;
        Db::exec(
            'UPDATE users SET name = ?, email = ?, email_verified_at = ?, updated_at = ? WHERE id = ?',
            [$name, $email, $verified, jmr_now(), $user->id()],
        );
        Response::redirect('/profile', ['success' => 'Profile saved.']);
    }

    private function updatePassword(): void
    {
        $user = $this->request->user;
        $current = (string) $this->request->input('current_password', '');
        $password = (string) $this->request->input('password', '');
        $confirmation = (string) $this->request->input('password_confirmation', '');
        $errors = [];
        if (! password_verify($current, (string) $user->row['password'])) {
            $errors['current_password'] = 'The password is incorrect.';
        }
        if (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        } elseif ($password !== $confirmation) {
            $errors['password'] = 'Password confirmation does not match.';
        }
        if ($errors !== []) {
            Response::invalid($errors);
        }

        Db::exec(
            'UPDATE users SET password = ?, updated_at = ? WHERE id = ?',
            [password_hash($password, PASSWORD_BCRYPT), jmr_now(), $user->id()],
        );
        $this->back(['success' => 'Password updated.']);
    }

    private function deleteProfile(): void
    {
        $user = $this->request->user;
        $password = (string) $this->request->input('password', '');
        if (! password_verify($password, (string) $user->row['password'])) {
            Response::invalid(['password' => 'The password is incorrect.']);
        }

        Db::exec('DELETE FROM users WHERE id = ?', [$user->id()]);
        $_SESSION = [];
        session_regenerate_id(true);
        Response::redirect('/login');
    }

    private function recordIndex(?string $employeeId = null): void
    {
        $input = $this->request->query;
        $filters = $this->records->filters($input);
        if ($employeeId !== null) {
            $filters['employee_id'] = '';
            $filters['employee_id_exact'] = $employeeId;
        }
        $sort = $filters['sort'];
        $direction = $filters['direction'];
        $partial = $this->request->partial();
        $props = [
            'filters' => $this->records->present($filters, $sort, $direction),
        ];

        if ($partial === null || in_array('records', $partial, true)) {
            $props['records'] = $this->records->page($filters, $this->request->user, $this->request->path, $this->request->query);
        }
        if ($partial === null || in_array('years', $partial, true)) {
            $props['years'] = $this->records->years($employeeId);
        }
        if ($partial === null || in_array('hour_total', $partial, true)) {
            $props['hour_total'] = $this->records->hourTotal($filters, $this->request->user);
        }
        if ($partial === null || in_array('records_total', $partial, true)) {
            $props['records_total'] = $this->records->matchingCount($filters, $this->request->user);
        }
        if ($partial === null || in_array('filter_options', $partial, true)) {
            $props['filter_options'] = $this->records->filterOptions($filters, $this->request->user);
        }

        Response::page($this->request, $props);
    }

    private function showRecord(int $id): void
    {
        $record = $this->records->show($id);
        if ($record === null) {
            Response::error('Not found.', 404);
        }
        if (! $this->request->user?->ownsGroup($record['group'])) {
            Response::error('Forbidden.', 403);
        }

        Response::page($this->request, [
            'year' => [
                'id' => $record['year_row_id'] !== null ? (int) $record['year_row_id'] : null,
                'year' => $record['year_number'] !== null ? (int) $record['year_number'] : null,
            ],
            'record' => [
                'id' => (int) $record['id'],
                'employee_id' => $record['employee_id'],
                'work_date' => $record['work_date'] ? substr((string) $record['work_date'], 0, 10) : null,
                'location' => $record['location'],
                'project' => $record['project'],
                'item' => $record['item'],
                'description' => $record['description'],
                'type' => $record['type'],
                'hour' => $record['hour'] !== null ? number_format((float) $record['hour'], 2, '.', '') : null,
                'remarks' => $record['remarks'],
                'category' => $record['category'],
                'group' => $record['group'],
                'person_in_charge' => $record['person_in_charge'],
                'dimension' => $record['dimension'],
                'change_log' => $record['change_log'],
                'member' => $record['member'],
                'sheet_count' => $record['sheet_count'],
                'paper_size' => $record['paper_size'],
                'progress' => $record['progress'],
                'revision' => $record['revision'],
                'is_active' => jmr_bool($record['is_active']),
                'content_hash' => $record['content_hash'],
                'created_at' => jmr_display_time($record['created_at']),
                'updated_at' => jmr_display_time($record['updated_at']),
                'import_batch' => $record['batch_id'] ? [
                    'id' => (int) $record['batch_id'],
                    'original_filename' => $record['original_filename'],
                    'status' => $record['batch_status'],
                    'created_at' => jmr_display_time($record['batch_created_at']),
                    'imported_by' => $record['imported_by'],
                ] : null,
            ],
        ]);
    }

    private function exportRecords(): void
    {
        $filters = $this->records->filters($this->request->query);
        $yearLabel = $filters['year'] ?? 'all';
        Response::download(
            'records-'.($yearLabel ?: 'all').'.csv',
            'text/csv',
            $this->records->csv($filters, $this->request->user),
        );
    }

    private function projectIndex(): void
    {
        $filters = $this->projectFilters();
        $presented = $this->records->present($filters);
        $years = $this->records->years();
        $partial = $this->request->partial();
        $props = ['filters' => $presented];

        if ($partial === null || in_array('years', $partial, true)) {
            $props['years'] = $years;
        }
        if ($partial === null || in_array('filter_options', $partial, true)) {
            $props['filter_options'] = $this->projectOptions($filters);
        }
        if ($partial === null || in_array('projects', $partial, true)) {
            $report = $years === []
                ? ['projects' => [], 'totals' => $this->projects->emptyTotals()]
                : $this->projects->cachedReport($presented, $filters, $this->request->user);
            $props['projects'] = $report['projects'];
            $props['totals'] = $report['totals'];
        }

        Response::page($this->request, $props);
    }

    private function projectBreakdown(): void
    {
        $filters = $this->projectFilters();
        $project = trim((string) ($filters['project'] ?? ''));
        if ($project === '') {
            Response::redirect('/projects'.($filters['year'] ? '?year='.$filters['year'] : ''));
        }

        $presented = $this->records->present($filters);
        $presented['project'] = $project;
        $years = $this->records->years();
        $partial = $this->request->partial();
        $props = [
            'project' => $project,
            'filters' => $presented,
        ];
        if ($partial === null || in_array('years', $partial, true)) {
            $props['years'] = $years;
        }
        if ($partial === null || in_array('filter_options', $partial, true)) {
            $props['filter_options'] = $this->projectOptions($filters);
        }
        if ($partial === null || in_array('lines', $partial, true)) {
            $scope = $presented;
            $scope['__viewer'] = $this->request->user?->viewerKey() ?? 'all';
            $report = $years === []
                ? ['lines' => [], 'subtotal' => $this->projects->emptyTotals()]
                : Cache::remember('project-breakdown', $scope, fn () => $this->projects->breakdown($filters, $project, $this->request->user));
            $props['lines'] = $report['lines'];
            $props['subtotal'] = $report['subtotal'];
        }

        Response::page($this->request, $props);
    }

    private function exportProjects(): void
    {
        $filters = $this->projectFilters();
        $presented = $this->records->present($filters);
        $report = $this->projects->cachedReport($presented, $filters, $this->request->user);
        $rows = [];
        foreach ($report['projects'] as $project) {
            $rows[] = [$project['project'], $project['total'], ...$project['months']];
        }
        $rows[] = ['Total', $report['totals']['total'], ...$report['totals']['months']];
        $yearLabel = $presented['year'] !== '' ? $presented['year'] : 'all';
        Response::download(
            "projects-{$yearLabel}.xlsx",
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            Spreadsheet::xlsx('Projects', ['Project', 'Total', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'], $rows),
        );
    }

    private function employeeIndex(): void
    {
        $search = trim((string) ($this->request->query['search'] ?? ''));
        $year = $this->request->integer('year');
        $sort = (string) ($this->request->query['sort'] ?? 'employee_id');
        $direction = ($this->request->query['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        if (! in_array($sort, ['employee_id', 'name', 'records_count', 'hours_total'], true)) {
            $sort = 'employee_id';
        }

        $yearId = $year ? $this->records->yearId($year) : null;
        [$groupSql, $groupParams] = $this->request->user->groupClause('group');
        $yearSql = $yearId ? ' AND year_id = ?' : '';
        $statParams = $yearId ? [...$groupParams, $yearId] : $groupParams;
        $stats = ($year !== null && $yearId === null) ? [] : Db::all(
            "SELECT employee_id, COUNT(*) AS records_count,
                    COALESCE(SUM(CASE WHEN is_active = 1 THEN hour ELSE 0 END), 0) AS hours_total
             FROM records
             WHERE {$groupSql} AND employee_id IS NOT NULL {$yearSql}
             GROUP BY employee_id",
            $statParams,
        );
        $byEmployee = [];
        foreach ($stats as $stat) {
            $byEmployee[(string) $stat['employee_id']] = $stat;
        }

        [$userGroup, $userGroupParams] = $this->request->user->groupClause('group_name');
        $sql = "SELECT employee_id, name, username, group_name, designation, directory_active
                FROM users WHERE employee_id IS NOT NULL AND {$userGroup}";
        $params = $userGroupParams;
        if ($search !== '') {
            $like = jmr_like($search);
            $sql .= ' AND (employee_id LIKE ? OR name LIKE ? OR username LIKE ? OR group_name LIKE ? OR designation LIKE ?)';
            $params = [...$params, $like, $like, $like, $like, $like];
        }
        $rows = Db::all($sql, $params);
        $employees = [];
        foreach ($rows as $row) {
            if ($year !== null && ! isset($byEmployee[(string) $row['employee_id']])) {
                continue;
            }
            $stat = $byEmployee[(string) $row['employee_id']] ?? null;
            $employees[] = [
                'employee_id' => $row['employee_id'],
                'name' => $row['name'],
                'username' => $row['username'],
                'group_name' => $row['group_name'],
                'designation' => $row['designation'],
                'directory_active' => jmr_bool_or_null($row['directory_active']),
                'records_count' => (int) ($stat['records_count'] ?? 0),
                'hours_total' => (float) ($stat['hours_total'] ?? 0),
            ];
        }

        usort($employees, function (array $a, array $b) use ($sort, $direction) {
            $result = match ($sort) {
                'name' => strnatcasecmp((string) $a['name'], (string) $b['name']),
                'records_count' => $a['records_count'] <=> $b['records_count'],
                'hours_total' => $a['hours_total'] <=> $b['hours_total'],
                default => ((int) $a['employee_id']) <=> ((int) $b['employee_id']),
            };

            return $direction === 'desc' ? -$result : $result;
        });

        $page = max(1, (int) ($this->request->query['page'] ?? 1));
        $slice = array_slice($employees, ($page - 1) * 25, 25);
        $data = array_map(function (array $employee) {
            $employee['hours_total'] = number_format($employee['hours_total'], 2, '.', '');

            return $employee;
        }, $slice);

        Response::page($this->request, [
            'filters' => [
                'search' => $search,
                'year' => $year ? (string) $year : '',
                'sort' => $sort,
                'direction' => $direction,
            ],
            'years' => $this->records->years(),
            'employees' => $this->lengthPage($data, count($employees), $page, 25, '/employees', $this->request->query),
        ]);
    }

    private function showEmployee(string $employeeId): void
    {
        $employee = Db::one(
            'SELECT users.*, roles.name AS role_name
             FROM users LEFT JOIN roles ON roles.id = users.role_id
             WHERE users.employee_id = ?',
            [$employeeId],
        );
        if ($employee === null) {
            Response::error('Not found.', 404);
        }
        if (! $this->request->user?->ownsGroup($employee['group_name'])) {
            Response::error('Forbidden.', 403);
        }

        $input = $this->request->query;
        $filters = $this->records->filters($input);
        $filters['employee_id'] = '';
        $filters['employee_id_exact'] = $employeeId;
        $partial = $this->request->partial();
        $props = [
            'employee' => [
                'employee_id' => $employee['employee_id'],
                'name' => $employee['name'],
                'username' => $employee['username'],
                'group_name' => $employee['group_name'],
                'designation' => $employee['designation'],
                'directory_active' => jmr_bool_or_null($employee['directory_active']),
                'resigned_on' => $employee['resigned_on'] ? substr((string) $employee['resigned_on'], 0, 10) : null,
                'role' => $employee['role_name'] ? mb_convert_case($employee['role_name'], MB_CASE_TITLE, 'UTF-8') : null,
            ],
            'filters' => $this->records->present($filters, $filters['sort'], $filters['direction']),
        ];
        if ($partial === null || in_array('records', $partial, true)) {
            $props['records'] = $this->records->page($filters, $this->request->user, $this->request->path, $this->request->query);
        }
        if ($partial === null || in_array('years', $partial, true)) {
            $props['years'] = $this->records->years($employeeId);
        }
        if ($partial === null || in_array('hour_total', $partial, true)) {
            $props['hour_total'] = $this->records->hourTotal($filters, $this->request->user);
        }
        if ($partial === null || in_array('records_total', $partial, true)) {
            $props['records_total'] = $this->records->matchingCount($filters, $this->request->user);
        }
        if ($partial === null || in_array('filter_options', $partial, true)) {
            $props['filter_options'] = $this->records->filterOptions($filters, $this->request->user);
        }

        Response::page($this->request, $props);
    }

    private function updateEmployeeRecord(string $employeeId, int $recordId): void
    {
        $employee = Db::one('SELECT group_name FROM users WHERE employee_id = ?', [$employeeId]);
        $record = Db::one('SELECT id, employee_id FROM records WHERE id = ?', [$recordId]);
        if ($employee === null || $record === null || (string) $record['employee_id'] !== (string) $employeeId) {
            Response::error('Not found.', 404);
        }
        if (! $this->request->user?->ownsGroup($employee['group_name'])) {
            Response::error('Forbidden.', 403);
        }

        $active = $this->request->input('is_active');
        Db::exec(
            'UPDATE records SET is_active = ?, updated_at = ? WHERE id = ?',
            [filter_var($active, FILTER_VALIDATE_BOOLEAN) ? 1 : 0, jmr_now(), $recordId],
        );
        Cache::bump();
        $this->back();
    }

    private function importEmployees(): void
    {
        $this->requireUpload();
        $files = $this->request->files('xml_file');
        $file = $files[0] ?? null;
        if ($file === null || $file['error'] !== UPLOAD_ERR_OK) {
            Response::invalid(['xml_file' => 'Upload an XML file.']);
        }
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (! in_array($extension, ['xml', 'txt'], true)) {
            Response::invalid(['xml_file' => 'The file must be .xml or .txt.']);
        }

        $tmp = $file['tmp_name'];
        $this->keepEmployeeUpload($tmp, (string) $file['name']);

        try {
            $counts = $this->directory->importFile($tmp);
        } catch (Throwable $e) {
            Response::redirect('/employees', ['error' => $e->getMessage()]);
        }

        Response::redirect('/employees', [
            'success' => "Employee details saved. {$counts['updated']} updated, {$counts['created']} added.",
        ]);
    }

    private function keepEmployeeUpload(string $tmp, string $originalName): void
    {
        $dir = jmr_storage_path('app/imports');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $target = $dir.'/last-employee-upload.xml';
        if (! @copy($tmp, $target)) {
            return;
        }

        @file_put_contents(
            $dir.'/last-employee-upload.name.txt',
            $originalName."\n".jmr_now()."\n",
        );
    }

    private function controlIndex(): void
    {
        $actor = $this->request->user;
        if (! $actor?->hasControl()) {
            Response::error('Forbidden.', 403);
        }

        $search = trim((string) ($this->request->query['search'] ?? ''));
        [$sql, $params] = $this->visibleUserSql($actor);
        if ($search !== '') {
            $like = jmr_like($search);
            $sql .= ' AND (users.name LIKE ? OR users.employee_id LIKE ? OR users.username LIKE ? OR users.group_name LIKE ?)';
            $params = [...$params, $like, $like, $like, $like];
        }
        $cast = Db::driver() === 'sqlite' ? 'INTEGER' : 'UNSIGNED';
        $count = (int) Db::value("SELECT COUNT(*) FROM users LEFT JOIN roles ON roles.id = users.role_id WHERE {$sql}", $params);
        $page = max(1, (int) ($this->request->query['page'] ?? 1));
        $rows = Db::all(
            "SELECT users.*, roles.name AS role_name
             FROM users LEFT JOIN roles ON roles.id = users.role_id
             WHERE {$sql}
             ORDER BY users.employee_id IS NULL, CAST(users.employee_id AS {$cast}), users.name
             LIMIT 25 OFFSET ".(($page - 1) * 25),
            $params,
        );

        $people = array_map(function (array $row) use ($actor) {
            $person = new Account($row);

            return [
                'id' => $person->id(),
                'employee_id' => $row['employee_id'],
                'name' => $row['name'],
                'username' => $row['username'],
                'group_name' => $row['group_name'] ?? '',
                'role' => $row['role_name'],
                'role_label' => $row['role_name'] ? mb_convert_case($row['role_name'], MB_CASE_TITLE, 'UTF-8') : null,
                'effective_role' => $person->effectiveRoleName(),
                'directory_management' => $person->directoryGrantsManagement(),
                'can_edit' => jmr_bool($row['can_edit']),
                'is_self' => $person->id() === $actor->id(),
            ];
        }, $rows);

        $assignable = $actor->assignableRoleNames();
        $roles = Db::all('SELECT name FROM roles');
        $roleRows = [];
        foreach ($assignable as $name) {
            foreach ($roles as $role) {
                if ($role['name'] === $name) {
                    $roleRows[] = [
                        'name' => $name,
                        'label' => mb_convert_case($name, MB_CASE_TITLE, 'UTF-8'),
                    ];
                }
            }
        }

        $groups = array_map(
            fn (array $row) => $row['group_name'],
            Db::all("SELECT DISTINCT group_name FROM users WHERE group_name IS NOT NULL AND group_name != '' ORDER BY group_name"),
        );

        Response::page($this->request, [
            'people' => $this->lengthPage($people, $count, $page, 25, '/control', $this->request->query),
            'filters' => ['search' => $search],
            'assignable_roles' => $roleRows,
            'groups' => $groups,
            'can_assign_role' => $assignable !== [],
            'can_assign_group' => $actor->canAssignGroup(),
            'scope' => match ($actor->effectiveRoleName()) {
                Account::DEVELOPER => 'You can control every account.',
                Account::MANAGEMENT => 'You can control leaders and members in every group.',
                Account::LEADER => trim((string) ($actor->row['group_name'] ?? '')) !== ''
                    ? 'You can grant edit access to members in '.$actor->row['group_name'].'.'
                    : 'Your account has no group, so there is no one to control.',
                default => '',
            },
        ]);
    }

    private function updateControl(int $id): void
    {
        $actor = $this->request->user;
        $person = Account::find($id);
        if ($actor === null || $person === null || ! $actor->manages($person)) {
            Response::error('Forbidden.', 403);
        }

        if ($actor->assignableRoleNames() === []) {
            if ($person->roleName() !== Account::MEMBER) {
                Response::error('Forbidden.', 403);
            }
            Db::exec(
                'UPDATE users SET can_edit = ?, updated_at = ? WHERE id = ?',
                [filter_var($this->request->input('can_edit'), FILTER_VALIDATE_BOOLEAN) ? 1 : 0, jmr_now(), $id],
            );
            $this->back();
        }

        $role = (string) $this->request->input('role', '');
        if (! in_array($role, $actor->assignableRoleNames(), true)) {
            Response::invalid(['role' => 'Choose a role you can assign.']);
        }

        $group = $person->row['group_name'];
        if ($actor->canAssignGroup()) {
            $group = trim((string) $this->request->input('group_name', ''));
            $group = $group === '' ? null : $group;
        }
        if (in_array($role, [Account::LEADER, Account::MEMBER], true) && $group === null) {
            $this->back(['error' => 'A leader or member needs a group.']);
        }

        Db::exec(
            'UPDATE users SET role_id = ?, group_name = ?, can_edit = ?, updated_at = ? WHERE id = ?',
            [
                Account::roleId($role),
                $group,
                $role === Account::MEMBER && filter_var($this->request->input('can_edit'), FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
                jmr_now(),
                $id,
            ],
        );
        $this->back();
    }

    /** @param  array<string, string>  $flash */
    private function back(array $flash = []): never
    {
        foreach ($flash as $key => $message) {
            $_SESSION['flash'][$key] = $message;
        }

        Response::json([
            'ok' => true,
            'csrf' => $_SESSION['csrf'] ?? null,
        ]);
    }

    private function yearIndex(): void
    {
        $rows = Db::all(
            'SELECT years.id, years.year,
                    (SELECT COUNT(*) FROM records WHERE records.year_id = years.id) AS records_count,
                    (SELECT MAX(created_at) FROM import_batches WHERE import_batches.year_id = years.id) AS last_imported_at
             FROM years
             ORDER BY years.year DESC',
        );

        Response::page($this->request, [
            'years' => array_map(fn (array $year) => [
                'id' => (int) $year['id'],
                'year' => (int) $year['year'],
                'records_count' => (int) $year['records_count'],
                'last_imported_at' => jmr_display_time($year['last_imported_at']),
            ], $rows),
        ]);
    }

    private function yearHistory(int $yearId): void
    {
        $year = Db::one('SELECT * FROM years WHERE id = ?', [$yearId]);
        if ($year === null) {
            Response::error('Not found.', 404);
        }

        $recordsCount = (int) Db::value('SELECT COUNT(*) FROM records WHERE year_id = ?', [$yearId]);
        $uploadsCount = (int) Db::value('SELECT COUNT(*) FROM import_batches WHERE year_id = ?', [$yearId]);
        $last = Db::value('SELECT created_at FROM import_batches WHERE year_id = ? ORDER BY created_at DESC LIMIT 1', [$yearId]);
        $page = max(1, (int) ($this->request->query['page'] ?? 1));
        $rows = Db::all(
            'SELECT import_batches.*, users.name AS imported_by
             FROM import_batches
             LEFT JOIN users ON users.id = import_batches.user_id
             WHERE import_batches.year_id = ?
             ORDER BY import_batches.id DESC
             LIMIT 15 OFFSET '.(($page - 1) * 15),
            [$yearId],
        );

        Response::page($this->request, [
            'year' => [
                'id' => (int) $year['id'],
                'year' => (int) $year['year'],
                'records_count' => $recordsCount,
                'uploads_count' => $uploadsCount,
                'last_imported_at' => jmr_display_time($last),
            ],
            'batches' => $this->lengthPage(array_map(fn (array $batch) => [
                'id' => (int) $batch['id'],
                'original_filename' => $batch['original_filename'],
                'employee_id' => $batch['employee_id'],
                'status' => $batch['status'],
                'inserted_count' => (int) $batch['inserted_count'],
                'updated_count' => (int) $batch['updated_count'],
                'skipped_count' => (int) $batch['skipped_count'],
                'failed_count' => (int) $batch['failed_count'],
                'error_message' => $batch['error_message'],
                'imported_by' => $batch['imported_by'],
                'created_at' => jmr_display_time($batch['created_at']),
                'finished_at' => jmr_display_time($batch['finished_at']),
            ], $rows), $uploadsCount, $page, 15, '/years/'.$yearId.'/uploads', $this->request->query),
        ]);
    }

    private function importCreate(): void
    {
        $this->requireUpload();
        Response::page($this->request, [
            'locked_year' => $this->request->integer('year'),
            'upload_limits' => [
                'batch_size' => self::MAX_FILES_PER_BATCH,
                'max_pending' => self::MAX_PENDING_FILES,
                'max_file_mb' => 50,
            ],
        ]);
    }

    private function analyze(): void
    {
        $this->requireUpload();
        $files = $this->request->files('xml_files');
        if ($files === []) {
            Response::invalid(['xml_files' => 'Upload at least one XML file.']);
        }
        if (count($files) > self::MAX_FILES_PER_BATCH) {
            Response::invalid(['xml_files' => 'Upload at most '.self::MAX_FILES_PER_BATCH.' files per batch.']);
        }

        $append = filter_var($this->request->input('append'), FILTER_VALIDATE_BOOLEAN);
        $existing = $append ? ($_SESSION['pending_imports'] ?? []) : [];
        if (count($existing) + count($files) > self::MAX_PENDING_FILES) {
            $remaining = max(0, self::MAX_PENDING_FILES - count($existing));
            Response::invalid([
                'xml_files' => $remaining === 0
                    ? 'You already have '.self::MAX_PENDING_FILES.' files ready to review. Confirm or cancel them before uploading more.'
                    : 'Too many files. You can add at most '.$remaining.' more.',
            ]);
        }

        $pending = [];
        $skipped = [];
        foreach ($files as $index => $file) {
            $name = $file['name'];
            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if ($file['error'] !== UPLOAD_ERR_OK || ! in_array($extension, ['xml', 'txt'], true)) {
                $skipped[] = ['filename' => $name, 'reason' => 'Each file must have a .xml (or .txt) extension.'];
                continue;
            }
            if ($file['size'] > 50 * 1024 * 1024) {
                $skipped[] = ['filename' => $name, 'reason' => 'Each file must be 50 MB or smaller.'];
                continue;
            }

            $parsed = $this->xml->parseUploadFilename($name);
            $relative = 'imports/pending/'.jmr_uuid().'.'.$extension;
            $absolute = jmr_storage_path($relative);
            if (! is_dir(dirname($absolute))) {
                mkdir(dirname($absolute), 0777, true);
            }
            if (! move_uploaded_file($file['tmp_name'], $absolute)) {
                $skipped[] = ['filename' => $name, 'reason' => 'This file could not be opened or read'];
                continue;
            }

            if ($parsed === null) {
                unlink($absolute);
                $skipped[] = ['filename' => $name, 'reason' => 'File name must look like 2017_1042.xml (year_employeeId)'];
                continue;
            }

            try {
                $detection = $this->xml->detectYears($absolute);
            } catch (Throwable) {
                unlink($absolute);
                $skipped[] = ['filename' => $name, 'reason' => 'This file could not be opened or read'];
                continue;
            }

            if ($detection['total_rows'] === 0) {
                unlink($absolute);
                $skipped[] = ['filename' => $name, 'reason' => 'No records were found in this file'];
                continue;
            }

            $detectedYears = array_map(fn (array $year) => (int) $year['year'], $detection['years']);
            if (! in_array($parsed['year'], $detectedYears, true)) {
                unlink($absolute);
                $skipped[] = [
                    'filename' => $name,
                    'reason' => "The year in the file name ({$parsed['year']}) does not match the dates in this file",
                ];
                continue;
            }

            $pending[] = [
                'id' => jmr_uuid(),
                'stored_path' => $relative,
                'original_filename' => $name,
                'employee_id' => $parsed['employee_id'],
                'filename_year' => $parsed['year'],
                'detection' => $detection,
            ];
            unset($index);
        }

        if ($append) {
            if ($pending === [] && $existing === []) {
                $this->rejectSkipped($skipped);
            }
            $_SESSION['pending_imports'] = array_merge($existing, $pending);
            $_SESSION['pending_import_skipped'] = array_merge($_SESSION['pending_import_skipped'] ?? [], $skipped);
        } else {
            if ($pending === []) {
                $this->rejectSkipped($skipped);
            }
            $this->deletePendingFiles();
            $_SESSION['pending_imports'] = $pending;
            $_SESSION['pending_import_skipped'] = $skipped;
        }

        Response::json([
            'ok' => true,
            'pending_count' => count($_SESSION['pending_imports']),
            'skipped_count' => count($_SESSION['pending_import_skipped']),
            'batch_accepted' => count($pending),
            'batch_skipped' => count($skipped),
            'csrf' => $_SESSION['csrf'] ?? null,
        ]);
    }

    private function confirmImport(): void
    {
        $this->requireUpload();
        $pending = $_SESSION['pending_imports'] ?? [];
        if ($pending === []) {
            Response::redirect('/import', ['error' => 'Please upload your files first.']);
        }

        $yearNumbers = [];
        foreach ($pending as $item) {
            foreach ($item['detection']['years'] as $year) {
                $yearNumbers[(int) $year['year']] = true;
            }
        }
        $existingYears = [];
        if ($yearNumbers !== []) {
            $placeholders = implode(',', array_fill(0, count($yearNumbers), '?'));
            foreach (Db::all(
                "SELECT years.*, (SELECT COUNT(*) FROM records WHERE records.year_id = years.id) AS records_count
                 FROM years WHERE year IN ({$placeholders})",
                array_keys($yearNumbers),
            ) as $year) {
                $existingYears[(int) $year['year']] = $year;
            }
        }

        $files = [];
        $defaults = [];
        foreach ($pending as $item) {
            $years = [];
            foreach ($item['detection']['years'] as $yearItem) {
                $existing = $existingYears[(int) $yearItem['year']] ?? null;
                $years[] = [
                    ...$yearItem,
                    'exists' => $existing !== null,
                    'year_id' => $existing ? (int) $existing['id'] : null,
                    'records_count' => $existing ? (int) $existing['records_count'] : 0,
                ];
            }
            $suggested = array_map(fn (array $year) => $year['year'], $years);
            $files[] = [
                'id' => $item['id'],
                'original_filename' => $item['original_filename'],
                'employee_id' => $item['employee_id'] ?? null,
                'filename_year' => $item['filename_year'] ?? null,
                'total_rows' => $item['detection']['total_rows'],
                'years' => $years,
                'is_mixed' => count($years) > 1,
                'suggested_years' => $suggested,
                'unsupported_fields' => array_values($item['detection']['unsupported_fields'] ?? []),
            ];
            $defaults[$item['id']] = $suggested;
        }

        Response::page($this->request, [
            'files' => $files,
            'defaults' => $defaults,
            'file_count' => count($files),
            'skipped' => $_SESSION['pending_import_skipped'] ?? [],
            'unsupported_summary' => array_values(array_filter(
                $files,
                fn (array $file) => ($file['unsupported_fields'] ?? []) !== [],
            )),
            'supported_fields' => XmlImporter::SUPPORTED_ROW_FIELDS,
        ]);
    }

    private function storeImport(): void
    {
        $this->requireUpload();
        $pending = $_SESSION['pending_imports'] ?? [];
        if ($pending === []) {
            Response::redirect('/import', ['error' => 'Please upload your files first.']);
        }

        $selections = $this->request->input('selections', []);
        if (! is_array($selections)) {
            Response::invalid(['selections' => 'Select at least one year to import.']);
        }

        $errors = [];
        foreach ($pending as $item) {
            $chosen = $selections[$item['id']] ?? null;
            $allowed = array_map(fn (array $year) => (int) $year['year'], $item['detection']['years'] ?? []);
            if (! is_array($chosen) || $chosen === []) {
                $errors['selections.'.$item['id']] = 'Select at least one year for each file.';
                continue;
            }
            foreach ($chosen as $year) {
                if (! in_array((int) $year, $allowed, true)) {
                    $errors['selections.'.$item['id']] = 'Select a year that was found in the file.';
                }
            }
        }
        if ($errors !== []) {
            Response::invalid($errors);
        }

        $queued = [];
        foreach ($pending as $item) {
            if (empty($item['employee_id'])) {
                continue;
            }
            $employeeId = (string) $item['employee_id'];
            DirectorySync::ensureEmployee($employeeId);
            $years = array_values(array_unique(array_map('intval', (array) $selections[$item['id']])));
            $paths = $this->pathsForYears($item['stored_path'], $years);
            foreach ($years as $yearNumber) {
                $year = DirectorySync::ensureYear($yearNumber);
                $now = jmr_now();
                $batchId = (int) Db::insert(
                    'INSERT INTO import_batches
                     (year_id, employee_id, user_id, original_filename, stored_path, status, inserted_count, updated_count, skipped_count, failed_count, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, 0, 0, 0, 0, ?, ?)',
                    [$year['id'], $employeeId, $this->request->user->id(), $item['original_filename'], $paths[$yearNumber], 'pending', $now, $now],
                );
                $queued[] = [
                    'batch_id' => $batchId,
                    'year_id' => (int) $year['id'],
                    'year' => (int) $year['year'],
                    'employee_id' => $employeeId,
                    'original_filename' => $item['original_filename'],
                ];
            }
        }

        unset($_SESSION['pending_imports'], $_SESSION['pending_import_skipped']);
        if ($queued === []) {
            Response::redirect('/import', ['error' => 'Nothing was selected to save. Please choose at least one year.']);
        }

        ImportWorker::kick();

        if (count($queued) === 1) {
            $first = $queued[0];
            Response::redirect(
                "/years/{$first['year_id']}/imports/{$first['batch_id']}",
                ['success' => "Saving started for {$first['year']}. This page will update as it finishes."],
            );
        }

        $_SESSION['queued_imports'] = $queued;
        Response::redirect('/import/summary', [
            'success' => 'Saving started for '.count($queued).' file/year combination(s). You can check progress below.',
        ]);
    }

    private function importSummary(): void
    {
        $this->requireUpload();
        $queued = $_SESSION['queued_imports'] ?? [];
        unset($_SESSION['queued_imports']);
        if ($queued === []) {
            Response::redirect('/records');
        }

        Response::page($this->request, ['batches' => $queued]);
    }

    private function cancelImport(): void
    {
        $this->requireUpload();
        $this->deletePendingFiles();
        unset($_SESSION['pending_imports'], $_SESSION['pending_import_skipped']);
        Response::redirect('/import');
    }

    private function showImport(int $yearId, int $batchId): void
    {
        $this->requireUpload();
        $year = Db::one('SELECT * FROM years WHERE id = ?', [$yearId]);
        $batch = Db::one(
            'SELECT import_batches.*, users.id AS importer_id, users.name AS importer_name, users.email AS importer_email
             FROM import_batches
             LEFT JOIN users ON users.id = import_batches.user_id
             WHERE import_batches.id = ?',
            [$batchId],
        );
        if ($year === null || $batch === null || (int) $batch['year_id'] !== $yearId) {
            Response::error('Not found.', 404);
        }

        $errors = $batch['row_errors'] ? json_decode((string) $batch['row_errors'], true) : [];
        Response::page($this->request, [
            'year' => ['id' => (int) $year['id'], 'year' => (int) $year['year']],
            'batch' => [
                'id' => (int) $batch['id'],
                'original_filename' => $batch['original_filename'],
                'employee_id' => $batch['employee_id'],
                'status' => $batch['status'],
                'inserted_count' => (int) $batch['inserted_count'],
                'updated_count' => (int) $batch['updated_count'],
                'skipped_count' => (int) $batch['skipped_count'],
                'failed_count' => (int) $batch['failed_count'],
                'error_message' => $batch['error_message'],
                'row_errors' => is_array($errors) ? $errors : [],
                'imported_by' => $batch['importer_id'] ? [
                    'id' => (int) $batch['importer_id'],
                    'name' => $batch['importer_name'],
                    'email' => $batch['importer_email'],
                ] : null,
                'created_at' => jmr_display_time($batch['created_at']),
                'started_at' => jmr_display_time($batch['started_at']),
                'finished_at' => jmr_display_time($batch['finished_at']),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function projectFilters(): array
    {
        $filters = $this->records->filters($this->request->query, true);
        if (! array_key_exists('year', $this->request->query)) {
            $latest = Db::value('SELECT year FROM years ORDER BY year DESC LIMIT 1');
            if ($latest !== null) {
                $filters['year'] = (int) $latest;
            }
        }

        return $filters;
    }

    /** @param  array<string, mixed>  $filters */
    private function projectOptions(array $filters): array
    {
        $scope = [
            'year' => (string) ($filters['year'] ?? ''),
            '__viewer' => $this->request->user?->viewerKey() ?? 'all',
        ];

        return Cache::remember('project-filter-options', $scope, function () use ($filters) {
            $options = $this->records->filterOptions($filters, $this->request->user);
            [$where, $params] = $this->records->where(['year' => $filters['year'] ?? null], $this->request->user);
            $options['members'] = array_map(
                fn (array $row) => $row['member'],
                Db::all(
                    "SELECT DISTINCT member FROM records
                     WHERE {$where} AND member IS NOT NULL AND member != ''
                     ORDER BY member",
                    $params,
                ),
            );

            return $options;
        });
    }

    /** @return array{0:string,1:list<mixed>} */
    private function visibleUserSql(Account $actor): array
    {
        $codes = Account::MANAGEMENT_DIRECTORY;

        return match ($actor->effectiveRoleName()) {
            Account::DEVELOPER => ['1 = 1', []],
            Account::MANAGEMENT => [
                'users.role_id IN (?, ?)
                 AND UPPER(COALESCE(users.group_name, \'\')) NOT IN (?, ?, ?)
                 AND UPPER(COALESCE(users.designation, \'\')) NOT IN (?, ?, ?)',
                [
                    Account::roleId(Account::LEADER),
                    Account::roleId(Account::MEMBER),
                    ...$codes,
                    ...$codes,
                ],
            ],
            Account::LEADER => trim((string) ($actor->row['group_name'] ?? '')) === ''
                ? ['0 = 1', []]
                : [
                    'users.role_id = ? AND users.group_name = ?
                     AND UPPER(COALESCE(users.group_name, \'\')) NOT IN (?, ?, ?)
                     AND UPPER(COALESCE(users.designation, \'\')) NOT IN (?, ?, ?)',
                    [
                        Account::roleId(Account::MEMBER),
                        $actor->row['group_name'],
                        ...$codes,
                        ...$codes,
                    ],
                ],
            default => ['0 = 1', []],
        };
    }

    private function lengthPage(array $data, int $total, int $page, int $perPage, string $path, array $query): array
    {
        $from = $data === [] ? null : (($page - 1) * $perPage) + 1;
        $to = $data === [] ? null : $from + count($data) - 1;
        $last = (int) max(1, ceil($total / $perPage));

        return [
            'data' => array_values($data),
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => $last,
            'from' => $from,
            'to' => $to,
            'prev_page_url' => $page > 1 ? $this->pageUrl($path, $query, $page - 1) : null,
            'next_page_url' => $page < $last ? $this->pageUrl($path, $query, $page + 1) : null,
        ];
    }

    private function pageUrl(string $path, array $query, int $page): string
    {
        $query['page'] = $page;

        return $path.'?'.http_build_query($query);
    }

    private function legacyYearRedirect(int $yearId, string $path): void
    {
        $year = Db::value('SELECT year FROM years WHERE id = ?', [$yearId]);
        if ($year === null) {
            Response::error('Not found.', 404);
        }
        Response::redirect($path.'?year='.$year);
    }

    private function legacyRecord(int $yearId, int $recordId): void
    {
        $record = Db::one('SELECT id, year_id FROM records WHERE id = ?', [$recordId]);
        if ($record === null || (int) $record['year_id'] !== $yearId) {
            Response::error('Not found.', 404);
        }
        Response::redirect('/records/'.$recordId);
    }

    /** @param  list<int>  $years
     *  @return array<int, string>
     */
    private function pathsForYears(string $source, array $years): array
    {
        $paths = [];
        $first = true;
        foreach ($years as $year) {
            if ($first) {
                $paths[$year] = $source;
                $first = false;
                continue;
            }
            $extension = pathinfo($source, PATHINFO_EXTENSION) ?: 'xml';
            $copy = 'imports/pending/'.jmr_uuid().'.'.$extension;
            copy(jmr_storage_path($source), jmr_storage_path($copy));
            $paths[$year] = $copy;
        }

        return $paths;
    }

    private function deletePendingFiles(): void
    {
        foreach ($_SESSION['pending_imports'] ?? [] as $item) {
            $path = jmr_storage_path((string) ($item['stored_path'] ?? ''));
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    /** @param  list<array{filename:string,reason:string}>  $skipped */
    private function rejectSkipped(array $skipped): void
    {
        $errors = [];
        foreach ($skipped as $index => $item) {
            $errors['xml_files.'.$index] = $item['filename'].': '.$item['reason'];
        }
        Response::invalid($errors !== [] ? $errors : ['xml_files' => 'None of the selected files could be used.']);
    }

    private function requireUpload(): void
    {
        if (! $this->request->user?->canUpload()) {
            Response::error('Forbidden.', 403);
        }
    }

    private function csrfOk(): bool
    {
        $token = $this->request->csrfToken();
        $session = $_SESSION['csrf'] ?? '';

        return is_string($token) && $token !== '' && is_string($session) && hash_equals($session, $token);
    }
}
