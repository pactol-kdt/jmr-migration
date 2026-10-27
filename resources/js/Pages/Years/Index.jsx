import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContainer from '@/Components/PageContainer';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/Components/ui/card';
import { Head, Link, usePage } from '@/inertia';
import { ArrowRight, Upload } from 'lucide-react';

export default function Index({ years }) {
    const canUpload = Boolean(usePage().props.auth?.can?.upload);

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h2 className="text-xl font-semibold leading-tight text-foreground">
                        Years
                    </h2>
                    {canUpload && (
                        <Button asChild size="sm">
                            <Link href={route('imports.create')}>
                                <Upload className="h-4 w-4" />
                                Upload
                            </Link>
                        </Button>
                    )}
                </div>
            }
        >
            <Head title="Years" />

            <PageContainer>
                {years.length === 0 ? (
                    <Card className="border-dashed">
                        <CardContent className="flex flex-col items-center px-6 py-16 text-center">
                            <CardTitle className="text-base">No years yet</CardTitle>
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
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {years.map((year) => (
                            <Link
                                key={year.id}
                                href={route('years.show', year.id)}
                                className="group block"
                            >
                                <Card className="h-full transition-all hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-md">
                                    <CardHeader className="flex flex-row items-start justify-between space-y-0 pb-4">
                                        <CardTitle className="text-3xl font-semibold tracking-tight text-primary">
                                            {year.year}
                                        </CardTitle>
                                        <Badge
                                            variant="secondary"
                                            className="gap-1 font-normal transition group-hover:bg-primary group-hover:text-primary-foreground"
                                        >
                                            Open
                                            <ArrowRight className="h-3 w-3" />
                                        </Badge>
                                    </CardHeader>
                                    <CardContent>
                                        <dl className="grid grid-cols-2 gap-4 text-sm">
                                            <div>
                                                <dt className="text-xs uppercase tracking-wide text-muted-foreground">
                                                    Entries
                                                </dt>
                                                <dd className="mt-1 font-medium">
                                                    {year.records_count}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt className="text-xs uppercase tracking-wide text-muted-foreground">
                                                    Last upload
                                                </dt>
                                                <dd className="mt-1 font-medium">
                                                    {year.last_imported_at ?? '—'}
                                                </dd>
                                            </div>
                                        </dl>
                                    </CardContent>
                                </Card>
                            </Link>
                        ))}
                    </div>
                )}
            </PageContainer>
        </AuthenticatedLayout>
    );
}
