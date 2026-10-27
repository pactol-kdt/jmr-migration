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
import { Head, Link } from '@/inertia';
import { ArrowLeft } from 'lucide-react';

function Field({ label, value, children }) {
    return (
        <div>
            <dt className="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted-foreground">
                {label}
            </dt>
            <dd className="mt-1.5 whitespace-pre-wrap text-sm text-foreground">
                {children ?? (value || '—')}
            </dd>
        </div>
    );
}

export default function Show({ year, record }) {
    return (
        <AuthenticatedLayout
            header={
                <div>
                    <nav className="mb-0.5 flex flex-wrap items-center gap-1.5 text-xs font-medium text-muted-foreground">
                        <Link
                            href={route('records.index')}
                            className="hover:text-foreground"
                        >
                            Records
                        </Link>
                        {year?.year && (
                            <>
                                <span className="text-border">/</span>
                                <Link
                                    href={route('records.index', {
                                        year: year.year,
                                    })}
                                    className="hover:text-foreground"
                                >
                                    {year.year}
                                </Link>
                            </>
                        )}
                        <span className="text-border">/</span>
                        <span className="text-foreground">Entry</span>
                    </nav>
                    <h2 className="text-xl font-semibold leading-tight text-foreground">
                        {record.work_date || 'Record'}
                    </h2>
                </div>
            }
        >
            <Head title={year?.year ? `Record · ${year.year}` : 'Record'} />

            <PageContainer width="4xl">
                <Card>
                    <CardHeader className="grid gap-4 sm:grid-cols-3 sm:space-y-0">
                        <div>
                            <CardDescription className="text-[11px] font-semibold uppercase tracking-[0.14em]">
                                Date
                            </CardDescription>
                            <CardTitle className="mt-1 text-lg">
                                {record.work_date || '—'}
                            </CardTitle>
                        </div>
                        <div>
                            <CardDescription className="text-[11px] font-semibold uppercase tracking-[0.14em]">
                                Hours
                            </CardDescription>
                            <CardTitle className="mt-1 text-lg tabular-nums text-primary">
                                {record.hour ?? '—'}
                            </CardTitle>
                        </div>
                        <div>
                            <CardDescription className="text-[11px] font-semibold uppercase tracking-[0.14em]">
                                Type
                            </CardDescription>
                            <CardTitle className="mt-1 text-lg">
                                {record.category || record.type || '—'}
                            </CardTitle>
                        </div>
                    </CardHeader>
                    <Separator />
                    <CardContent className="pt-6">
                        <dl className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <Field label="Employee ID" value={record.employee_id} />
                            <Field label="Location" value={record.location} />
                            <Field label="Project" value={record.project} />
                            <Field label="Item of Works" value={record.item} />
                            <Field label="Group" value={record.group} />
                            <Field
                                label="Person in charge"
                                value={record.person_in_charge}
                            />
                            <Field label="Type of Works" value={record.type} />
                            <Field label="Category" value={record.category} />
                            <Field
                                label="Status"
                                value={
                                    record.is_active === false
                                        ? 'Inactive'
                                        : 'Active'
                                }
                            />
                            <Field label="2D/3D" value={record.dimension} />
                            <Field label="Member" value={record.member} />
                            <Field
                                label="No. of Sheets"
                                value={record.sheet_count}
                            />
                            <Field label="Paper Size" value={record.paper_size} />
                            <Field label="Progress" value={record.progress} />
                            <Field label="Revision" value={record.revision} />
                            <Field
                                label="Last updated"
                                value={record.updated_at}
                            />
                            <div className="sm:col-span-2">
                                <Field
                                    label="Job Request Description"
                                    value={record.description}
                                />
                            </div>
                            <div className="sm:col-span-2">
                                <Field
                                    label="Change Log"
                                    value={record.change_log}
                                />
                            </div>
                            <div className="sm:col-span-2">
                                <Field label="Remarks" value={record.remarks} />
                            </div>
                        </dl>
                    </CardContent>
                </Card>

                {record.import_batch && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Where this came from
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                                <Field
                                    label="Uploaded file"
                                    value={
                                        record.import_batch.original_filename
                                    }
                                />
                                <Field label="Upload status">
                                    <StatusBadge
                                        status={record.import_batch.status}
                                    />
                                </Field>
                                <Field
                                    label="Uploaded on"
                                    value={record.import_batch.created_at}
                                />
                                <Field
                                    label="Uploaded by"
                                    value={record.import_batch.imported_by}
                                />
                            </dl>
                            {record.import_batch.id && year?.id && (
                                <Button
                                    variant="link"
                                    className="mt-4 h-auto px-0"
                                    asChild
                                >
                                    <Link
                                        href={route('imports.show', [
                                            year.id,
                                            record.import_batch.id,
                                        ])}
                                    >
                                        Open upload
                                    </Link>
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                )}

                <Button variant="ghost" className="w-fit px-0" asChild>
                    <Link
                        href={
                            year?.year
                                ? route('records.index', { year: year.year })
                                : route('records.index')
                        }
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Back to records
                    </Link>
                </Button>
            </PageContainer>
        </AuthenticatedLayout>
    );
}
