import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InfoTip from '@/Components/InfoTip';
import PageContainer from '@/Components/PageContainer';
import RecordFilters from '@/Components/RecordFilters';
import { Button } from '@/Components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { Head, Link, router } from '@/inertia';
import {
    flexRender,
    getCoreRowModel,
    getSortedRowModel,
    useReactTable,
} from '@tanstack/react-table';
import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-react';
import { useEffect, useState } from 'react';

const MONTHS = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec',
];

const emptyFilters = {
    search: '',
    year: '',
    employee_id: '',
    location: '',
    project: '',
    type: '',
    category: '',
    group: '',
    member: '',
    person_in_charge: '',
    date_from: '',
    date_to: '',
};

function hours(value, blankZero = false) {
    const amount = Number(value ?? 0);

    if (blankZero && amount === 0) {
        return '—';
    }

    return amount.toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

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

export default function Show({
    project,
    filters,
    filter_options = {},
    years = [],
    lines = [],
    subtotal,
}) {
    const [local, setLocal] = useState({ ...emptyFilters, ...filters, project });
    const [sorting, setSorting] = useState([{ id: 'item', desc: false }]);

    useEffect(() => {
        setLocal({ ...emptyFilters, ...filters, project });
    }, [filters, project]);

    const visit = (next) => {
        router.get(
            route('projects.show'),
            { ...next, project },
            {
                preserveState: true,
                replace: true,
            },
        );
    };

    const selectedYear = years.find(
        (entry) => String(entry.year) === String(local.year),
    );
    const heading = local.year ? `${project} · ${local.year}` : project;
    const sort = sorting[0]?.id ?? 'item';
    const direction = sorting[0]?.desc ? 'desc' : 'asc';
    const indexFilters = { ...local };
    delete indexFilters.project;

    const sortableHeader = (column, label, align = 'left') => (
        <button
            type="button"
            className={`inline-flex items-center font-medium ${
                align === 'right' ? 'w-full justify-end' : ''
            }`}
            onClick={() =>
                setSorting((current) => {
                    const active = current[0]?.id === column;
                    const desc = active ? !current[0]?.desc : false;

                    return [{ id: column, desc }];
                })
            }
        >
            {label}
            <SortIcon column={column} sort={sort} direction={direction} />
        </button>
    );

    const columns = [
        {
            id: 'item',
            accessorKey: 'item',
            header: () => (
                <span className="inline-flex items-center gap-1 whitespace-nowrap">
                    {sortableHeader('item', 'IOW')}
                    <InfoTip label="Item of Works" />
                </span>
            ),
            cell: ({ row }) => row.original.item,
            meta: { className: 'sticky left-0 z-10 bg-card font-medium' },
        },
        {
            id: 'description',
            accessorKey: 'description',
            header: () => (
                <span className="inline-flex items-center gap-1 whitespace-nowrap">
                    {sortableHeader('description', 'JRD')}
                    <InfoTip label="Job Request Description" />
                </span>
            ),
            cell: ({ row }) => row.original.description,
            meta: { className: 'max-w-xs whitespace-normal' },
        },
        {
            id: 'type',
            accessorKey: 'type',
            header: () => (
                <span className="inline-flex items-center gap-1 whitespace-nowrap">
                    {sortableHeader('type', 'TOW')}
                    <InfoTip label="Type of Works" />
                </span>
            ),
            cell: ({ row }) => row.original.type,
        },
        {
            id: 'subtotal',
            accessorFn: (row) => Number(row.subtotal),
            header: () => sortableHeader('subtotal', 'Sub total', 'right'),
            cell: ({ row }) => (
                <span className="block text-right font-medium tabular-nums">
                    {hours(row.original.subtotal)}
                </span>
            ),
            meta: { className: 'text-right' },
        },
        ...MONTHS.map((month, index) => ({
            id: `month-${index}`,
            accessorFn: (row) => Number(row.months[index]),
            header: () => <span className="block text-right">{month}</span>,
            cell: ({ row }) => (
                <span className="block text-right tabular-nums text-muted-foreground">
                    {hours(row.original.months[index], true)}
                </span>
            ),
            meta: { className: 'px-2 text-right' },
            enableSorting: false,
        })),
    ];

    const table = useReactTable({
        data: lines,
        columns,
        state: { sorting },
        onSortingChange: setSorting,
        getCoreRowModel: getCoreRowModel(),
        getSortedRowModel: getSortedRowModel(),
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
                            Sub total{' '}
                            <strong className="tabular-nums text-foreground">
                                {hours(subtotal?.total)}
                            </strong>
                        </span>
                        <Button variant="ghost" size="sm" asChild>
                            <Link href={route('projects.index', indexFilters)}>
                                Back to projects
                            </Link>
                        </Button>
                        {selectedYear && (
                            <Button variant="ghost" size="sm" asChild>
                                <Link href={route('years.history', selectedYear.id)}>
                                    Upload history
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>
            }
        >
            <Head title={heading} />

            <PageContainer>
                <RecordFilters
                    local={local}
                    setLocal={setLocal}
                    onApply={visit}
                    onReset={() => {
                        const next = { ...emptyFilters, project };
                        setLocal(next);
                        visit(next);
                    }}
                    years={years}
                    filterOptions={filter_options}
                    includeMember
                    hideProject
                    allowAllYears={false}
                />

                <div className="rounded-lg border bg-card text-card-foreground shadow-sm">
                    <Table>
                        <TableHeader>
                            {table.getHeaderGroups().map((headerGroup) => (
                                <TableRow key={headerGroup.id}>
                                    {headerGroup.headers.map((header) => (
                                        <TableHead
                                            key={header.id}
                                            className={
                                                header.column.columnDef.meta
                                                    ?.className
                                            }
                                        >
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
                                        colSpan={columns.length}
                                        className="h-24 text-center text-muted-foreground"
                                    >
                                        No lines match these filters.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                table.getRowModel().rows.map((row) => (
                                    <TableRow key={row.id}>
                                        {row.getVisibleCells().map((cell) => (
                                            <TableCell
                                                key={cell.id}
                                                className={
                                                    cell.column.columnDef.meta
                                                        ?.className
                                                }
                                            >
                                                {flexRender(
                                                    cell.column.columnDef.cell,
                                                    cell.getContext(),
                                                )}
                                            </TableCell>
                                        ))}
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                        {lines.length > 0 && subtotal && (
                            <TableFooter>
                                <TableRow className="hover:bg-transparent">
                                    <TableCell className="sticky left-0 z-10 bg-muted/50 font-medium">
                                        Sub total
                                    </TableCell>
                                    <TableCell className="bg-muted/50" />
                                    <TableCell className="bg-muted/50" />
                                    <TableCell className="text-right font-medium tabular-nums">
                                        {hours(subtotal.total)}
                                    </TableCell>
                                    {subtotal.months.map((monthHours, index) => (
                                        <TableCell
                                            key={MONTHS[index]}
                                            className="px-2 text-right tabular-nums"
                                        >
                                            {hours(monthHours)}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            </TableFooter>
                        )}
                    </Table>
                </div>
            </PageContainer>
        </AuthenticatedLayout>
    );
}
