import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContainer from '@/Components/PageContainer';
import Pagination from '@/Components/Pagination';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import {
    Card,
    CardContent,
    CardTitle,
} from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetFooter,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/Components/ui/sheet';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { cn } from '@/lib/utils';
import { Head, Link, router, usePage } from '@/inertia';
import {
    flexRender,
    getCoreRowModel,
    useReactTable,
} from '@tanstack/react-table';
import { ArrowDown, ArrowUp, ArrowUpDown, Filter, Search, Upload, X } from 'lucide-react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';

const emptyFilters = {
    search: '',
    year: '',
    sort: 'employee_id',
    direction: 'asc',
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
    employees,
    filters = emptyFilters,
    years = [],
}) {
    const canUpload = Boolean(usePage().props.auth?.can?.upload);
    const rows = employees?.data ?? [];
    const [local, setLocal] = useState({ ...emptyFilters, ...filters });
    const [sheetOpen, setSheetOpen] = useState(false);
    const [uploading, setUploading] = useState(false);
    const detailsInput = useRef(null);

    const uploadDetails = (event) => {
        const file = event.target.files?.[0];
        event.target.value = '';

        if (!file) {
            return;
        }

        setUploading(true);
        router.post(
            route('employees.import'),
            { xml_file: file },
            {
                forceFormData: true,
                onError: (errors) => {
                    const message =
                        errors?.xml_file ||
                        errors?.message ||
                        Object.values(errors || {}).find(Boolean) ||
                        'Upload failed. Please try again.';
                    toast.error(String(message));
                },
                onFinish: () => setUploading(false),
            },
        );
    };

    const visit = (next) => {
        router.get(route('employees.index'), next, {
            preserveState: true,
            replace: true,
        });
    };

    const applyFilters = (e) => {
        e?.preventDefault?.();
        setSheetOpen(false);
        visit(local);
    };

    const clearFilters = () => {
        setLocal(emptyFilters);
        setSheetOpen(false);
        visit(emptyFilters);
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
            id: 'employee_id',
            header: () => sortableHeader('employee_id', 'Employee'),
            cell: ({ row }) => row.original.employee_id,
        },
        {
            id: 'name',
            header: () => sortableHeader('name', 'Name'),
            cell: ({ row }) => (
                <span className="inline-flex items-center gap-2">
                    <span>{row.original.name || '—'}</span>
                    {row.original.directory_active === false && (
                        <Badge variant="secondary">Resigned</Badge>
                    )}
                    {row.original.directory_active === null && (
                        <Badge
                            variant="outline"
                            title="Information was not updated because this employee is not in the current directory. The 2017 details were kept."
                        >
                            Not updated
                        </Badge>
                    )}
                </span>
            ),
        },
        {
            id: 'group_name',
            header: 'Group',
            cell: ({ row }) => row.original.group_name || '—',
        },
        {
            id: 'designation',
            header: 'Designation',
            cell: ({ row }) => row.original.designation || '—',
        },
        {
            id: 'records_count',
            header: () => sortableHeader('records_count', 'Entries'),
            cell: ({ row }) => (
                <span className="tabular-nums text-muted-foreground">
                    {row.original.records_count}
                </span>
            ),
        },
        {
            id: 'hours_total',
            header: () => sortableHeader('hours_total', 'Hours', 'right'),
            cell: ({ row }) => (
                <span className="block text-right font-medium tabular-nums">
                    {row.original.hours_total}
                </span>
            ),
        },
        {
            id: 'actions',
            header: '',
            cell: ({ row }) => (
                <div className="text-right">
                    <Button variant="link" className="h-auto p-0" asChild>
                        <Link
                            href={route(
                                'employees.show',
                                row.original.employee_id,
                            )}
                        >
                            View hours
                        </Link>
                    </Button>
                </div>
            ),
        },
    ];

    const table = useReactTable({
        data: rows,
        columns,
        getCoreRowModel: getCoreRowModel(),
        manualSorting: true,
        manualPagination: true,
    });

    const hasEmployees = (employees?.total ?? rows.length) > 0 || local.search || local.year;

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h2 className="text-xl font-semibold leading-tight text-foreground">
                        Employees
                    </h2>
                    {canUpload && (
                        <div>
                            <input
                                ref={detailsInput}
                                type="file"
                                accept=".xml,.txt,text/xml,application/xml,text/plain"
                                className="hidden"
                                onChange={uploadDetails}
                            />
                            <Button
                                type="button"
                                size="sm"
                                disabled={uploading}
                                onClick={() => detailsInput.current?.click()}
                            >
                                <Upload className="h-4 w-4" />
                                {uploading ? 'Uploading…' : 'Upload employee details'}
                            </Button>
                        </div>
                    )}
                </div>
            }
        >
            <Head title="Employees" />

            <PageContainer>
                {!hasEmployees ? (
                    <Card className="border-dashed">
                        <CardContent className="flex flex-col items-center px-6 py-16 text-center">
                            <CardTitle className="text-base">
                                No employees yet
                            </CardTitle>
                            <p className="mt-2 text-sm text-muted-foreground">
                                Employees appear after you upload files named
                                like 2017_1042.xml.
                            </p>
                            {canUpload && (
                                <Button asChild className="mt-6">
                                    <Link href={route('imports.create')}>
                                        <Upload className="h-4 w-4" />
                                        Upload files
                                    </Link>
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                ) : (
                    <>
                        <form onSubmit={applyFilters} className="space-y-3">
                            <div className="flex items-center gap-2">
                                <div className="relative min-w-0 flex-1">
                                    <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        value={local.search}
                                        onChange={(e) =>
                                            setLocal({
                                                ...local,
                                                search: e.target.value,
                                            })
                                        }
                                        placeholder="Search employee ID or name…"
                                        className="pl-9"
                                    />
                                </div>
                                <Sheet open={sheetOpen} onOpenChange={setSheetOpen}>
                                    <SheetTrigger asChild>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            className={cn(
                                                local.year &&
                                                    'border-primary text-primary',
                                            )}
                                        >
                                            <Filter className="h-4 w-4" />
                                            Filters
                                            {local.year && (
                                                <Badge className="ml-1 h-5 min-w-5 justify-center rounded-full px-1.5">
                                                    1
                                                </Badge>
                                            )}
                                        </Button>
                                    </SheetTrigger>
                                    <SheetContent className="flex w-full flex-col sm:max-w-md">
                                        <SheetHeader>
                                            <SheetTitle>Filters</SheetTitle>
                                        </SheetHeader>
                                        <div className="mt-6 grid flex-1 gap-4 overflow-y-auto pb-4">
                                            <div className="space-y-2">
                                                <Label>Year</Label>
                                                <Select
                                                    value={local.year || 'all'}
                                                    onValueChange={(value) =>
                                                        setLocal({
                                                            ...local,
                                                            year:
                                                                value === 'all'
                                                                    ? ''
                                                                    : value,
                                                        })
                                                    }
                                                >
                                                    <SelectTrigger>
                                                        <SelectValue placeholder="All years" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="all">
                                                            All years
                                                        </SelectItem>
                                                        {years.map((entry) => (
                                                            <SelectItem
                                                                key={entry.id}
                                                                value={String(entry.year)}
                                                            >
                                                                {entry.year}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                        </div>
                                        <SheetFooter className="gap-2 border-t pt-4">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                onClick={clearFilters}
                                            >
                                                Reset
                                            </Button>
                                            <Button type="submit" onClick={applyFilters}>
                                                Apply filters
                                            </Button>
                                        </SheetFooter>
                                    </SheetContent>
                                </Sheet>
                                <Button type="submit">Search</Button>
                            </div>
                            {(local.search || local.year) && (
                                <div className="flex flex-wrap items-center gap-2">
                                    {local.search && (
                                        <Badge
                                            variant="secondary"
                                            className="gap-1 pr-1 font-normal"
                                        >
                                            Search: {local.search}
                                            <button
                                                type="button"
                                                onClick={() => {
                                                    const next = { ...local, search: '' };
                                                    setLocal(next);
                                                    visit(next);
                                                }}
                                                className="rounded-sm p-0.5 hover:bg-background/60"
                                                aria-label="Remove search"
                                            >
                                                <X className="h-3 w-3" />
                                            </button>
                                        </Badge>
                                    )}
                                    {local.year && (
                                        <Badge
                                            variant="secondary"
                                            className="gap-1 pr-1 font-normal"
                                        >
                                            Year: {local.year}
                                            <button
                                                type="button"
                                                onClick={() => {
                                                    const next = { ...local, year: '' };
                                                    setLocal(next);
                                                    visit(next);
                                                }}
                                                className="rounded-sm p-0.5 hover:bg-background/60"
                                                aria-label="Remove year"
                                            >
                                                <X className="h-3 w-3" />
                                            </button>
                                        </Badge>
                                    )}
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        className="h-7 px-2 text-xs"
                                        onClick={clearFilters}
                                    >
                                        Clear all
                                    </Button>
                                </div>
                            )}
                        </form>

                        <div className="rounded-lg border bg-card text-card-foreground shadow-sm">
                            <Table>
                                <TableHeader>
                                    {table.getHeaderGroups().map((headerGroup) => (
                                        <TableRow key={headerGroup.id}>
                                            {headerGroup.headers.map((header) => (
                                                <TableHead key={header.id}>
                                                    {flexRender(
                                                        header.column.columnDef.header,
                                                        header.getContext(),
                                                    )}
                                                </TableHead>
                                            ))}
                                        </TableRow>
                                    ))}
                                </TableHeader>
                                <TableBody>
                                    {rows.length === 0 ? (
                                        <TableRow>
                                            <TableCell
                                                colSpan={columns.length}
                                                className="h-24 text-center text-muted-foreground"
                                            >
                                                No employees match your search.
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        table.getRowModel().rows.map((row) => (
                                            <TableRow key={row.id}>
                                                {row.getVisibleCells().map((cell) => (
                                                    <TableCell key={cell.id}>
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
                            </Table>
                            <Pagination paginator={employees} />
                        </div>
                    </>
                )}
            </PageContainer>
        </AuthenticatedLayout>
    );
}
