import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContainer from '@/Components/PageContainer';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { Head, Link } from '@/inertia';
import { ArrowLeft } from 'lucide-react';

export default function Summary({ batches }) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-foreground">
                    Upload started
                </h2>
            }
        >
            <Head title="Upload started" />

            <PageContainer width="4xl">
                <Card>
                    <CardContent className="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>File</TableHead>
                                    <TableHead>Employee</TableHead>
                                    <TableHead>Year</TableHead>
                                    <TableHead className="text-right">
                                        Progress
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {batches.map((item) => (
                                    <TableRow
                                        key={`${item.batch_id}-${item.year_id}`}
                                    >
                                        <TableCell className="font-medium">
                                            {item.original_filename}
                                        </TableCell>
                                        <TableCell className="tabular-nums">
                                            {item.employee_id || '—'}
                                        </TableCell>
                                        <TableCell>{item.year}</TableCell>
                                        <TableCell className="text-right">
                                            <Button
                                                variant="link"
                                                className="h-auto p-0"
                                                asChild
                                            >
                                                <Link
                                                    href={route('imports.show', [
                                                        item.year_id,
                                                        item.batch_id,
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
                    </CardContent>
                </Card>

                <Button variant="ghost" className="w-fit px-0" asChild>
                    <Link href={route('records.index')}>
                        <ArrowLeft className="h-4 w-4" />
                        Back to records
                    </Link>
                </Button>
            </PageContainer>
        </AuthenticatedLayout>
    );
}
