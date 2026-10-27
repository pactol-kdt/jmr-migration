import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContainer from '@/Components/PageContainer';
import StatusBadge from '@/Components/StatusBadge';
import { Button } from '@/Components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/Components/ui/card';
import { Separator } from '@/Components/ui/separator';
import { Head, Link, router } from '@/inertia';
import { ArrowLeft } from 'lucide-react';
import { useEffect } from 'react';

function Stat({ label, value }) {
    return (
        <div>
            <dt className="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted-foreground">
                {label}
            </dt>
            <dd className="mt-1 text-xl font-semibold tabular-nums text-foreground">
                {value}
            </dd>
        </div>
    );
}

function Meta({ label, value }) {
    return (
        <div>
            <dt className="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted-foreground">
                {label}
            </dt>
            <dd className="mt-1 text-sm text-foreground">{value}</dd>
        </div>
    );
}

export default function Show({ year, batch }) {
    const isBusy = batch.status === 'pending' || batch.status === 'processing';

    useEffect(() => {
        if (isBusy) {
            const id = setInterval(() => {
                router.reload({ only: ['batch'] });
            }, 2500);
            return () => clearInterval(id);
        }
    }, [batch.status, isBusy]);

    const uploadedBy =
        typeof batch.imported_by === 'string'
            ? batch.imported_by
            : (batch.imported_by?.name ?? '—');

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-foreground">
                    Upload progress · {year.year}
                </h2>
            }
        >
            <Head title={`Upload · ${year.year}`} />

            <PageContainer width="3xl">
                <Card>
                    <CardHeader className="flex flex-row flex-wrap items-start justify-between gap-3 space-y-0">
                        <div className="space-y-1">
                            <CardDescription className="text-[11px] font-semibold uppercase tracking-[0.14em]">
                                File
                            </CardDescription>
                            <CardTitle className="text-base">
                                {batch.original_filename}
                            </CardTitle>
                        </div>
                        <StatusBadge status={batch.status} />
                    </CardHeader>
                    <CardContent className="space-y-5">
                        <dl className="grid grid-cols-2 gap-5 sm:grid-cols-4">
                            <Stat label="New" value={batch.inserted_count} />
                            <Stat
                                label="Updated"
                                value={batch.updated_count}
                            />
                            <Stat
                                label="Unchanged"
                                value={batch.skipped_count}
                            />
                            <Stat label="Failed" value={batch.failed_count} />
                        </dl>

                        <Separator />

                        <dl className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <Meta
                                label="Employee ID"
                                value={batch.employee_id || '—'}
                            />
                            <Meta label="Uploaded by" value={uploadedBy} />
                            <Meta
                                label="Started"
                                value={
                                    batch.created_at ??
                                    (isBusy ? 'Just now' : '—')
                                }
                            />
                            <Meta
                                label="Finished"
                                value={
                                    batch.finished_at ??
                                    (isBusy ? 'Still working…' : '—')
                                }
                            />
                        </dl>

                        {batch.status === 'failed' && batch.error_message && (
                            <div className="rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive">
                                {batch.error_message}
                            </div>
                        )}

                        {batch.status === 'completed' &&
                            batch.failed_count > 0 && (
                                <div className="rounded-lg border border-amber-200 bg-warning-soft px-4 py-3 text-sm text-warning">
                                    {batch.failed_count} row
                                    {batch.failed_count === 1 ? '' : 's'} failed
                                </div>
                            )}

                        {isBusy && (
                            <p className="text-sm text-muted-foreground">
                                {STATUS_LABEL[batch.status] ?? batch.status}…
                            </p>
                        )}
                    </CardContent>
                </Card>

                {Array.isArray(batch.row_errors) &&
                    batch.row_errors.length > 0 && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Failed rows
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="p-0">
                                <ul className="divide-y divide-border">
                                    {batch.row_errors.map((error, index) => (
                                        <li
                                            key={`${error.element ?? 'row'}-${index}`}
                                            className="px-6 py-3 text-sm"
                                        >
                                            <p className="font-medium text-foreground">
                                                {error.work_date ||
                                                    'Unknown date'}
                                            </p>
                                            <p className="mt-1 text-destructive">
                                                {error.message}
                                            </p>
                                        </li>
                                    ))}
                                </ul>
                            </CardContent>
                        </Card>
                    )}

                <div className="flex flex-wrap items-center gap-3">
                    <Button variant="ghost" className="w-fit px-0" asChild>
                        <Link href={route('years.history', year.id)}>
                            <ArrowLeft className="h-4 w-4" />
                            Back to upload history
                        </Link>
                    </Button>
                    <Button variant="link" className="px-0" asChild>
                        <Link
                            href={route('records.index', { year: year.year })}
                        >
                            Browse records
                        </Link>
                    </Button>
                </div>
            </PageContainer>
        </AuthenticatedLayout>
    );
}
