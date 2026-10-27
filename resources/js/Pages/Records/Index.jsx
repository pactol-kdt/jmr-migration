import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InfoTip from '@/Components/InfoTip';
import PageContainer from '@/Components/PageContainer';
import Pagination from '@/Components/Pagination';
import RecordFilters from '@/Components/RecordFilters';
import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';
import { Switch } from '@/Components/ui/switch';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { Head, Link, router } from '@/inertia';
import {
    flexRender,
    getCoreRowModel,
    useReactTable,
} from '@tanstack/react-table';
import { ArrowDown, ArrowLeft, ArrowUp, ArrowUpDown } from 'lucide-react';
import { useState } from 'react';

const emptyFilters = {
    search: '',
    year: '',
    employee_id: '',
    location: '',
    project: '',
    type: '',
    category: '',
    group: '',
    person_in_charge: '',
    date_from: '',
    date_to: '',
    sort: 'work_date',
    direction: 'desc',
};

function SortIcon({ column, sort, direction }) {
    if (sort !== column) {
        return <ArrowUpDown className="ml-1 h-3.5 w-3.5 opacity-40" />;
    }
    return direction === 'asc' ? (
        <ArrowUp className="ml-1 h-3.5 w-3.5" />
    ) : (
        <ArrowDown className="ml-1 h-3.5 w-3.5" />
    );
}

export default function Index({
    filters,
    filter_options = {},
    years = [],
    records,
    records_total,
    hour_total,
    profile = null,
    browseRoute = 'records.index',
    browseParams = {},
    title = null,
    hideEmployeeFilter = false,
    hideEmployeeColumn = false,
    backLink = null,
    statusEmployeeId = null,
}) {
    const [local, setLocal] = useState({ ...emptyFilters, ...filters });
    const [pendingStatus, setPendingStatus] = useState({});

    const visit = (next) => {
        router.get(route(browseRoute, browseParams), next, {
            preserveState: true,
            replace: true,
        });
    };

    const updateStatus = (recordId, isActive) => {
        setPendingStatus((current) => ({ ...current, [recordId]: isActive }));
        router.patch(
            route('employees.records.update', {
                employee: statusEmployeeId,
                record: recordId,
            }),
            { is_active: isActive },
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => {
                    setPendingStatus((current) => {
                        const next = { ...current };
                        delete next[recordId];
                        return next;
                    });
                },
            },
        );
    };

    const toggleSort = (column) => {
        const next = {
            ...local,
            sort: column,
            direction:
                local.sort === column && local.direction === 'asc' ? 'desc' : 'asc',
        };
        setLocal(next);
        visit(next);
    };

    const selectedYear = years.find(
        (entry) => String(entry.year) === String(local.year),
    );
    const heading = title || (local.year ? `Records · ${local.year}` : 'All records');

    const sortableHeader = (column, label, align = 'left') => (
        <button
            type="button"
            className={`inline-flex items-center font-medium ${
                align === 'right' ? 'w-full justify-end' : ''
            }`}
            onClick={() => toggleSort(column)}
        >
            {label}
            <SortIcon
                column={column}
                sort={local.sort}
                direction={local.direction}
            />
        </button>
    );

    const columns = [
            {
                id: 'year',
                header: 'Year',
                cell: ({ row }) => row.original.year || '—',
            },
            {
                id: 'employee_id',
                header: () => sortableHeader('employee_id', 'Employee'),
                cell: ({ row }) => row.original.employee_id || '—',
            },
            {
                id: 'work_date',
                accessorKey: 'work_date',
                header: () => sortableHeader('work_date', 'Date'),
            },
            {
                id: 'location',
                header: () => sortableHeader('location', 'Location'),
                cell: ({ row }) => row.original.location || '—',
            },
            {
                id: 'project',
                header: () => sortableHeader('project', 'Project'),
                cell: ({ row }) => row.original.project || '—',
            },
            {
                id: 'item',
                header: () => (
                    <span className="inline-flex items-center gap-1 whitespace-nowrap">
                        {sortableHeader('item', 'IOW')}
                        <InfoTip label="Item of Works" />
                    </span>
                ),
                cell: ({ row }) => row.original.item || '—',
            },
            {
                id: 'group',
                header: () => sortableHeader('group', 'Group'),
                cell: ({ row }) => row.original.group || '—',
            },
            {
                id: 'type',
                header: () => (
                    <span className="inline-flex items-center gap-1 whitespace-nowrap">
                        {sortableHeader('type', 'TOW')}
                        <InfoTip label="Type of Works" />
                    </span>
                ),
                cell: ({ row }) => row.original.type || '—',
            },
            {
                id: 'hour',
                header: () => sortableHeader('hour', 'Hours', 'right'),
                cell: ({ row }) => (
                    <span className="block text-right tabular-nums font-medium">
                        {row.original.hour ?? '—'}
                        {!statusEmployeeId && row.original.is_active === false && (
                            <span className="ml-2 text-xs font-normal text-muted-foreground">
                                Inactive
                            </span>
                        )}
                    </span>
                ),
            },
            {
                id: 'category',
                header: () => sortableHeader('category', 'Category'),
                cell: ({ row }) => row.original.category || '—',
            },
            ...(statusEmployeeId
                ? [
                      {
                          id: 'status',
                          header: 'Status',
                          cell: ({ row }) => {
                              const isActive =
                                  pendingStatus[row.original.id] ??
                                  row.original.is_active !== false;

                              const inputId = `record-status-${row.original.id}`;
                              const saving =
                                  pendingStatus[row.original.id] !== undefined;

                              return (
                                  <div className="flex items-center gap-2">
                                      <Switch
                                          id={inputId}
                                          checked={isActive}
                                          disabled={saving}
                                          onCheckedChange={(checked) =>
                                              updateStatus(
                                                  row.original.id,
                                                  checked,
                                              )
                                          }
                                          aria-label={`Status for ${row.original.work_date || 'record'}`}
                                          className={
                                              saving
                                                  ? 'disabled:cursor-wait'
                                                  : undefined
                                          }
                                      />
                                      <Label
                                          htmlFor={inputId}
                                          className="text-xs font-normal"
                                      >
                                          {isActive ? 'Active' : 'Inactive'}
                                      </Label>
                                  </div>
                              );
                          },
                      },
                  ]
                : []),
            {
                id: 'actions',
                header: '',
                cell: ({ row }) => (
                    <div className="text-right">
                        <Button variant="link" className="h-auto p-0" asChild>
                            <Link href={route('records.show', row.original.id)}>
                                View
                            </Link>
                        </Button>
                    </div>
                ),
            },
        ];

    const visibleColumns = hideEmployeeColumn
        ? columns.filter((column) => column.id !== 'employee_id')
        : columns;

    const table = useReactTable({
        data: records.data,
        columns: visibleColumns,
        getCoreRowModel: getCoreRowModel(),
        manualSorting: true,
        manualPagination: true,
    });

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h2 className="text-xl font-semibold leading-tight text-foreground">
                        {heading}
                    </h2>
                    <div className="flex flex-wrap items-center gap-3">
                        <span className="text-sm text-muted-foreground">
                            {statusEmployeeId ? 'Active hours' : 'Total'}{' '}
                            <strong className="tabular-nums text-foreground">
                                {hour_total ?? '…'}
                            </strong>
                        </span>
                        {selectedYear && (
                            <Button variant="ghost" size="sm" asChild>
                                <Link href={route('years.history', selectedYear.id)}>
                                    Upload history
                                </Link>
                            </Button>
                        )}
                        <Button variant="outline" size="sm" asChild>
                            <a href={route('records.export', local)}>Export</a>
                        </Button>
                    </div>
                </div>
            }
        >
            <Head title={heading} />

            <PageContainer>
                {Array.isArray(profile) && profile.length > 0 && (
                    <dl className="grid gap-4 rounded-lg border bg-card p-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
                        {profile.map((item) => (
                            <div key={item.label}>
                                <dt className="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted-foreground">
                                    {item.label}
                                </dt>
                                <dd className="mt-1 text-foreground">
                                    {item.value || '—'}
                                </dd>
                            </div>
                        ))}
                    </dl>
                )}
                <RecordFilters
                    local={local}
                    setLocal={setLocal}
                    onApply={visit}
                    onReset={() => {
                        setLocal(emptyFilters);
                        visit(emptyFilters);
                    }}
                    years={years}
                    filterOptions={filter_options}
                    hideEmployee={hideEmployeeFilter}
                />

                <div className="rounded-lg border bg-card text-card-foreground shadow-sm">
                    <Table>
                        <TableHeader>
                            {table.getHeaderGroups().map((headerGroup) => (
                                <TableRow key={headerGroup.id}>
                                    {headerGroup.headers.map((header) => (
                                        <TableHead key={header.id}>
                                            {header.isPlaceholder
                                                ? null
                                                : flexRender(
                                                      header.column.columnDef
                                                          .header,
                                                      header.getContext(),
                                                  )}
                                        </TableHead>
                                    ))}
                                </TableRow>
                            ))}
                        </TableHeader>
                        <TableBody>
                            {table.getRowModel().rows.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={visibleColumns.length}
                                        className="h-24 text-center text-muted-foreground"
                                    >
                                        No records match your search.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                table.getRowModel().rows.map((row) => {
                                    const isActive =
                                        pendingStatus[row.original.id] ??
                                        row.original.is_active !== false;

                                    return (
                                        <TableRow
                                            key={row.id}
                                            className={
                                                isActive
                                                    ? undefined
                                                    : 'bg-muted/40'
                                            }
                                        >
                                            {row.getVisibleCells().map((cell) => (
                                                <TableCell key={cell.id}>
                                                    {flexRender(
                                                        cell.column.columnDef.cell,
                                                        cell.getContext(),
                                                    )}
                                                </TableCell>
                                            ))}
                                        </TableRow>
                                    );
                                })
                            )}
                        </TableBody>
                    </Table>
                    <Pagination paginator={records} total={records_total} />
                </div>
                {backLink && (
                    <Button variant="ghost" className="w-fit px-0" asChild>
                        <Link href={backLink.href}>
                            <ArrowLeft className="h-4 w-4" />
                            {backLink.label}
                        </Link>
                    </Button>
                )}
            </PageContainer>
        </AuthenticatedLayout>
    );
}
