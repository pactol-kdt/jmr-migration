import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContainer from '@/Components/PageContainer';
import Pagination from '@/Components/Pagination';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import { Switch } from '@/Components/ui/switch';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { Head, router } from '@/inertia';
import { Search } from 'lucide-react';
import { useState } from 'react';

const NO_GROUP = 'none';

export default function Index({
    people,
    filters = { search: '' },
    assignable_roles = [],
    groups = [],
    can_assign_role = false,
    can_assign_group = false,
    scope = '',
}) {
    const rows = people?.data ?? [];
    const [search, setSearch] = useState(filters.search ?? '');
    const [savingId, setSavingId] = useState(null);

    const visit = (nextSearch) => {
        router.get(
            route('control.index'),
            { search: nextSearch },
            { preserveState: true, replace: true },
        );
    };

    const save = (person, changes) => {
        setSavingId(person.id);

        const payload = can_assign_role
            ? {
                  role: changes.role ?? person.role,
                  group_name:
                      changes.group_name !== undefined
                          ? changes.group_name
                          : person.group_name,
                  can_edit: changes.can_edit ?? person.can_edit,
              }
            : {
                  can_edit: changes.can_edit ?? person.can_edit,
              };

        router.patch(route('control.update', person.id), payload, {
            preserveScroll: true,
            onFinish: () => setSavingId(null),
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h2 className="text-xl font-semibold leading-tight text-foreground">
                        Control
                    </h2>
                    {scope && (
                        <p className="mt-1 text-sm text-muted-foreground">{scope}</p>
                    )}
                </div>
            }
        >
            <Head title="Control" />

            <PageContainer>
                <form
                    className="flex flex-wrap items-center gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        visit(search);
                    }}
                >
                    <div className="relative min-w-[16rem] flex-1">
                        <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search name, employee, username, or group"
                            className="pl-9"
                            aria-label="Search people"
                        />
                    </div>
                    <Button type="submit">Search</Button>
                </form>

                <div className="rounded-lg border bg-card text-card-foreground shadow-sm">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Employee</TableHead>
                                <TableHead>Name</TableHead>
                                <TableHead>Group</TableHead>
                                <TableHead>Role</TableHead>
                                <TableHead>Edit access</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {rows.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={5}
                                        className="h-24 text-center text-muted-foreground"
                                    >
                                        No people to control.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                rows.map((person) => {
                                    const saving = savingId === person.id;
                                    const groupValue = person.group_name || NO_GROUP;
                                    const groupOptions = [
                                        ...new Set(
                                            [person.group_name, ...groups].filter(Boolean),
                                        ),
                                    ];

                                    return (
                                        <TableRow key={person.id}>
                                            <TableCell className="font-medium">
                                                {person.employee_id || '—'}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    <span>{person.name || '—'}</span>
                                                    {person.is_self && (
                                                        <Badge variant="secondary">You</Badge>
                                                    )}
                                                </div>
                                                {person.username && (
                                                    <div className="text-xs text-muted-foreground">
                                                        {person.username}
                                                    </div>
                                                )}
                                            </TableCell>
                                            <TableCell className="min-w-40">
                                                <Select
                                                    value={groupValue}
                                                    disabled={
                                                        saving ||
                                                        person.is_self ||
                                                        !can_assign_group
                                                    }
                                                    onValueChange={(value) =>
                                                        save(person, {
                                                            group_name:
                                                                value === NO_GROUP ? '' : value,
                                                        })
                                                    }
                                                >
                                                    <SelectTrigger aria-label={`Group for ${person.name || person.employee_id}`}>
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value={NO_GROUP}>
                                                            No group
                                                        </SelectItem>
                                                        {groupOptions.map((group) => (
                                                            <SelectItem key={group} value={group}>
                                                                {group}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            </TableCell>
                                            <TableCell className="min-w-40">
                                                {person.directory_management ? (
                                                    <Badge variant="outline">Management</Badge>
                                                ) : can_assign_role && !person.is_self ? (
                                                    <Select
                                                        value={person.role}
                                                        disabled={saving}
                                                        onValueChange={(value) =>
                                                            save(person, { role: value })
                                                        }
                                                    >
                                                        <SelectTrigger aria-label={`Role for ${person.name || person.employee_id}`}>
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {assignable_roles.map((role) => (
                                                                <SelectItem
                                                                    key={role.name}
                                                                    value={role.name}
                                                                >
                                                                    {role.label}
                                                                </SelectItem>
                                                            ))}
                                                            {person.role &&
                                                                !assignable_roles.some(
                                                                    (role) => role.name === person.role,
                                                                ) && (
                                                                    <SelectItem value={person.role}>
                                                                        {person.role_label}
                                                                    </SelectItem>
                                                                )}
                                                        </SelectContent>
                                                    </Select>
                                                ) : (
                                                    <Badge variant="outline">
                                                        {person.role_label || '—'}
                                                    </Badge>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {person.effective_role === 'member' && !person.is_self ? (
                                                    <div className="flex items-center gap-2">
                                                        <Switch
                                                            checked={person.can_edit}
                                                            disabled={saving}
                                                            onCheckedChange={(checked) =>
                                                                save(person, { can_edit: checked })
                                                            }
                                                            aria-label={`Edit access for ${person.name || person.employee_id}`}
                                                        />
                                                        <Label className="text-xs font-normal text-muted-foreground">
                                                            {person.can_edit ? 'Allowed' : 'Off'}
                                                        </Label>
                                                    </div>
                                                ) : (
                                                    <span className="text-sm text-muted-foreground">
                                                        Included
                                                    </span>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    );
                                })
                            )}
                        </TableBody>
                    </Table>
                </div>

                <Pagination paginator={people} />
            </PageContainer>
        </AuthenticatedLayout>
    );
}
