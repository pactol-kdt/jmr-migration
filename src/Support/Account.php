<?php

declare(strict_types=1);

namespace Jmr\Support;

final class Account
{
    public const DEVELOPER = 'developer';

    public const MANAGEMENT = 'management';

    public const LEADER = 'leader';

    public const MEMBER = 'member';

    /** @var list<string> */
    public const MANAGEMENT_DIRECTORY = ['MNG', 'IT', 'SYS'];

    public function __construct(public array $row) {}

    public static function find(int $id): ?self
    {
        $row = Db::one(
            'SELECT users.*, roles.name AS role_name
             FROM users
             LEFT JOIN roles ON roles.id = users.role_id
             WHERE users.id = ?',
            [$id],
        );

        return $row ? new self($row) : null;
    }

    public static function findByEmail(string $email): ?self
    {
        $row = Db::one(
            'SELECT users.*, roles.name AS role_name
             FROM users
             LEFT JOIN roles ON roles.id = users.role_id
             WHERE users.email = ?',
            [$email],
        );

        return $row ? new self($row) : null;
    }

    public static function findByEmployeeId(string $employeeId): ?self
    {
        $row = Db::one(
            'SELECT users.*, roles.name AS role_name
             FROM users
             LEFT JOIN roles ON roles.id = users.role_id
             WHERE users.employee_id = ?',
            [$employeeId],
        );

        return $row ? new self($row) : null;
    }

    public static function roleId(string $name): int
    {
        static $cache = [];
        if (! isset($cache[$name])) {
            $id = Db::value('SELECT id FROM roles WHERE name = ?', [$name]);
            if ($id === null) {
                throw new \RuntimeException("Role [{$name}] is not seeded.");
            }
            $cache[$name] = (int) $id;
        }

        return $cache[$name];
    }

    public function id(): int
    {
        return (int) $this->row['id'];
    }

    public function roleName(): ?string
    {
        $name = $this->row['role_name'] ?? null;

        return $name === null || $name === '' ? null : (string) $name;
    }

    public function directoryGrantsManagement(): bool
    {
        foreach ([$this->row['group_name'] ?? null, $this->row['designation'] ?? null] as $value) {
            $code = mb_strtoupper(trim((string) $value));
            if (in_array($code, self::MANAGEMENT_DIRECTORY, true)) {
                return true;
            }
        }

        return false;
    }

    public function effectiveRoleName(): ?string
    {
        $stored = $this->roleName();

        if ($stored === self::DEVELOPER) {
            return self::DEVELOPER;
        }

        if ($stored === self::MANAGEMENT || $this->directoryGrantsManagement()) {
            return self::MANAGEMENT;
        }

        return $stored;
    }

    public function canUpload(): bool
    {
        return $this->effectiveRoleName() === self::DEVELOPER;
    }

    public function seesAllGroups(): bool
    {
        return in_array($this->effectiveRoleName(), [self::DEVELOPER, self::MANAGEMENT], true);
    }

    public function hasControl(): bool
    {
        return in_array($this->effectiveRoleName(), [self::DEVELOPER, self::MANAGEMENT, self::LEADER], true);
    }

    public function canAssignGroup(): bool
    {
        return $this->effectiveRoleName() === self::DEVELOPER;
    }

    /** @return list<string> */
    public function assignableRoleNames(): array
    {
        return match ($this->effectiveRoleName()) {
            self::DEVELOPER => [self::DEVELOPER, self::MANAGEMENT, self::LEADER, self::MEMBER],
            self::MANAGEMENT => [self::LEADER, self::MEMBER],
            default => [],
        };
    }

    public function ownsGroup(?string $group): bool
    {
        if ($this->seesAllGroups() || $this->effectiveRoleName() !== self::LEADER) {
            return true;
        }

        $own = trim((string) ($this->row['group_name'] ?? ''));

        return $own !== '' && mb_strtoupper($own) === mb_strtoupper(trim((string) $group));
    }

    /**
     * @return array{0:string,1:list<mixed>}
     */
    public function groupClause(string $column): array
    {
        if ($this->seesAllGroups() || $this->effectiveRoleName() !== self::LEADER) {
            return ['1 = 1', []];
        }

        $own = trim((string) ($this->row['group_name'] ?? ''));
        if ($own === '') {
            return ['0 = 1', []];
        }

        $quoted = $column === 'group' ? '`group`' : $column;

        return ["UPPER({$quoted}) = ?", [mb_strtoupper($own)]];
    }

    public function manages(self $target): bool
    {
        if (! $this->hasControl() || $this->id() === $target->id()) {
            return false;
        }

        return match ($this->effectiveRoleName()) {
            self::DEVELOPER => true,
            self::MANAGEMENT => in_array($target->effectiveRoleName(), [self::LEADER, self::MEMBER], true),
            self::LEADER => $target->effectiveRoleName() === self::MEMBER
                && trim((string) ($this->row['group_name'] ?? '')) !== ''
                && ($this->row['group_name'] ?? null) === ($target->row['group_name'] ?? null),
            default => false,
        };
    }

    public function viewerKey(): string
    {
        if ($this->seesAllGroups()) {
            return 'all';
        }

        if ($this->effectiveRoleName() === self::LEADER) {
            return 'leader:'.mb_strtoupper(trim((string) ($this->row['group_name'] ?? '')));
        }

        return 'user:'.$this->id();
    }

    /** @return array<string, mixed> */
    public function toPublic(): array
    {
        return [
            'id' => $this->id(),
            'employee_id' => $this->row['employee_id'],
            'name' => $this->row['name'],
            'email' => $this->row['email'],
            'username' => $this->row['username'],
            'group_name' => $this->row['group_name'],
            'designation' => $this->row['designation'],
            'can_edit' => jmr_bool($this->row['can_edit'] ?? false),
            'email_verified_at' => $this->row['email_verified_at'] ?? null,
        ];
    }
}
