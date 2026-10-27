import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContainer from '@/Components/PageContainer';
import Pagination from '@/Components/Pagination';
import StatusBadge from '@/Components/StatusBadge';
import { Button } from '@/Components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/Components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { Head, Link, usePage } from '@/inertia';
import { ArrowLeft, Upload } from 'lucide-react';

function Stat({ label, value }) {
    return (
        <Card>
            <CardHeader className="pb-2">
                <CardDescription className="text-[11px] font-semibold uppercase tracking-[0.14em]">
                    {label}
                </CardDescription>
                <CardTitle className="text-2xl text-primary">{value}</CardTitle>
            </CardHeader>
        </Card>
    );
}

export default function History({ year, batches }) {
    const canUpload = Boolean(usePage().props.auth?.can?.upload);
    const rows = batches.data ?? batches;

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <nav className="mb-0.5 flex flex-wrap items-center gap-1.5 text-xs font-medium text-muted-foreground">
                            <Link
                                href={route('years.index')}
                                className="hover:text-foreground"
                            >
                                Years
                            </Link>
                            <span className="text-border">/</span>
                            <Link
                                href={route('records.index', { year: year.year })}
                                className="hover:text-foreground"
                            >
                                {year.year}
                            </Link>
                            <span className="text-border">/</span>
                            <span className="text-foreground">Upload history</span>
                        </nav>
                        <h2 className="text-xl font-semibold leading-tight text-foreground">
                            Upload history · {year.year}
                        </h2>
                    </div>
                    {canUpload && (
                        <Button asChild>
                            <Link href={route('imports.create', { year: year.year })}>
                                <Upload className="h-4 w-4" />
                                Upload files
                            </Link>
                        </Button>
                    )}
                </div>
            }
        >
            <Head title={`Upload history · ${year.year}`} />

            <PageContainer>
                <div className="grid gap-4 sm:grid-cols-3">
                    <Stat label="Entries" value={year.records_count} />
                    <Stat label="Uploads" value={year.uploads_count ?? rows.length} />
                    <Stat
                        label="Last upload"
                        value={year.last_imported_at ?? '—'}
                    />
                </div>

                {rows.length === 0 ? (
                    <Card className="border-dashed">
                        <CardContent className="flex flex-col items-center px-6 py-14 text-center">
                            <CardTitle className="text-base">No uploads yet</CardTitle>
                            {canUpload && (
                                <Button asChild className="mt-6">
                                    <Link
                                        href={route('imports.create', {
                                            year: year.year,
                                        })}
                                    >
                                        <Upload className="h-4 w-4" />
                                        Upload files
                                    </Link>
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                ) : (
                    <Card>
                        <CardContent className="p-0 pt-0">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>File</TableHead>
                                        <TableHead>Employee</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>New</TableHead>
                                        <TableHead>Updated</TableHead>
                                        <TableHead>By</TableHead>
                                        <TableHead>When</TableHead>
                                        <TableHead className="text-right" />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {rows.map((batch) => (
                                        <TableRow key={batch.id}>
                                            <TableCell className="font-medium">
                                                {batch.original_filename}
                                            </TableCell>
                                            <TableCell className="tabular-nums">
                                                {batch.employee_id || '—'}
                                            </TableCell>
                                            <TableCell>
                                                <StatusBadge status={batch.status} />
                                                {batch.status === 'failed' &&
                                                    batch.error_message && (
                                                        <p
                                                            className="mt-1 max-w-xs truncate text-xs text-destructive"
                                                            title={batch.error_message}
                                                        >
                                                            {batch.error_message}
                                                        </p>
                                                    )}
                                            </TableCell>
                                            <TableCell className="tabular-nums text-muted-foreground">
                                                {batch.inserted_count}
                                            </TableCell>
                                            <TableCell className="tabular-nums text-muted-foreground">
                                                {batch.updated_count}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {batch.imported_by ?? '—'}
                                            </TableCell>
                                            <TableCell className="whitespace-nowrap text-muted-foreground">
                                                {batch.created_at}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Button
                                                    variant="link"
                                                    className="h-auto p-0"
                                                    asChild
                                                >
                                                    <Link
                                                        href={route('imports.show', [
                                                            year.id,
                                                            batch.id,
                                                        ])}
                                                    >
                                                        View
                                                    </Link>
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                            <Pagination paginator={batches} />
                        </CardContent>
                    </Card>
                )}

                <Button variant="ghost" className="w-fit px-0" asChild>
                    <Link href={route('records.index', { year: year.year })}>
                        <ArrowLeft className="h-4 w-4" />
                        Back to {year.year} records
                    </Link>
                </Button>
            </PageContainer>
        </AuthenticatedLayout>
    );
}
