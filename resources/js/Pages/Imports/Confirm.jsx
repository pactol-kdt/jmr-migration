import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputError from '@/Components/InputError';
import PageContainer from '@/Components/PageContainer';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/Components/ui/card';
import { cn } from '@/lib/utils';
import { Head, Link, useForm } from '@/inertia';
import { Save } from 'lucide-react';

function InlineAlert({ children, className }) {
    return (
        <div
            className={cn(
                'rounded-lg border border-amber-200 bg-warning-soft px-4 py-3 text-sm text-warning',
                className,
            )}
        >
            {children}
        </div>
    );
}

export default function Confirm({
    files,
    defaults,
    file_count,
    skipped = [],
    unsupported_summary = [],
    supported_fields = [],
}) {
    const { data, setData, post, processing, errors, delete: destroy } = useForm({
        selections: { ...defaults },
    });

    const toggleYear = (fileId, year) => {
        const current = Array.isArray(data.selections[fileId])
            ? [...data.selections[fileId]]
            : [];
        const idx = current.findIndex((y) => Number(y) === Number(year));
        if (idx >= 0) {
            current.splice(idx, 1);
        } else {
            current.push(year);
        }
        setData('selections', {
            ...data.selections,
            [fileId]: current,
        });
    };

    const selectedCount = files.filter((file) => {
        const years = data.selections?.[file.id];

        return Array.isArray(years) && years.length > 0;
    }).length;

    const submit = (e) => {
        e.preventDefault();
        post(route('imports.store'));
    };

    const cancel = (e) => {
        e.preventDefault();
        destroy(route('imports.cancel'));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-foreground">
                    Review before saving
                </h2>
            }
        >
            <Head title="Review upload" />

            <PageContainer width="4xl">
                {skipped.length > 0 && (
                    <InlineAlert>
                        <p className="font-medium">
                            The system could not use {skipped.length} file
                            {skipped.length === 1 ? '' : 's'}
                        </p>
                        <ul className="mt-2 list-disc space-y-1 pl-5">
                            {skipped.map((item) => (
                                <li key={item.filename}>
                                    <span className="font-medium">
                                        {item.filename}
                                    </span>
                                    {' — '}
                                    {item.reason}
                                </li>
                            ))}
                        </ul>
                    </InlineAlert>
                )}

                {unsupported_summary.length > 0 && (
                    <InlineAlert>
                        <p className="font-medium">
                            {unsupported_summary.length} file
                            {unsupported_summary.length === 1 ? '' : 's'} contain
                            column
                            {unsupported_summary.length === 1 ? '' : 's'} the
                            system does not import
                        </p>
                        <p className="mt-1">
                            Unsupported values will be ignored. Supported
                            columns: {supported_fields.join(', ')}.
                        </p>
                        <ul className="mt-2 list-disc space-y-1 pl-5">
                            {unsupported_summary.map((item) => (
                                <li key={item.id}>
                                    <span className="font-medium">
                                        {item.original_filename}
                                    </span>
                                    {' — '}
                                    {(item.unsupported_fields || []).join(', ')}
                                </li>
                            ))}
                        </ul>
                    </InlineAlert>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>
                            Confirm · {file_count} file
                            {file_count === 1 ? '' : 's'}
                        </CardTitle>
                    </CardHeader>
                </Card>

                <form onSubmit={submit} className="space-y-6">
                    {files.map((file) => (
                        <Card key={file.id}>
                            <CardHeader className="flex flex-row flex-wrap items-start justify-between gap-2 space-y-0">
                                <div className="space-y-1">
                                    <CardTitle className="text-base">
                                        {file.original_filename}
                                    </CardTitle>
                                    <CardDescription>
                                        {file.employee_id
                                            ? `Employee ${file.employee_id} · `
                                            : ''}
                                        {file.total_rows} record
                                        {file.total_rows === 1 ? '' : 's'} found
                                    </CardDescription>
                                </div>
                                {!file.is_mixed && file.years[0] && (
                                    <Badge
                                        variant="outline"
                                        className="border-transparent bg-success-soft text-success"
                                    >
                                        Year {file.years[0].year}
                                    </Badge>
                                )}
                                {file.is_mixed && (
                                    <Badge
                                        variant="outline"
                                        className="border-transparent bg-warning-soft text-warning"
                                    >
                                        More than one year
                                    </Badge>
                                )}
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {(file.unsupported_fields || []).length > 0 && (
                                    <InlineAlert>
                                        <p className="font-medium">
                                            Unsupported or new columns (will be
                                            ignored)
                                        </p>
                                        <p className="mt-1">
                                            {(file.unsupported_fields || []).join(
                                                ', ',
                                            )}
                                        </p>
                                    </InlineAlert>
                                )}

                                <fieldset className="space-y-3">
                                    <legend className="text-sm font-medium text-foreground">
                                        {file.is_mixed
                                            ? 'Which years should the system save from this file?'
                                            : 'Year for this file'}
                                    </legend>
                                    {file.years.map((item) => {
                                        const checked = (
                                            data.selections[file.id] || []
                                        )
                                            .map(Number)
                                            .includes(item.year);

                                        return (
                                            <label
                                                key={item.year}
                                                className="flex cursor-pointer items-start gap-3 rounded-lg border border-input p-3 transition hover:bg-muted/40"
                                            >
                                                <input
                                                    type="checkbox"
                                                    className="mt-1 size-4 rounded border-input text-primary focus:ring-ring"
                                                    value={item.year}
                                                    checked={checked}
                                                    onChange={() =>
                                                        toggleYear(
                                                            file.id,
                                                            item.year,
                                                        )
                                                    }
                                                />
                                                <span className="text-sm text-foreground">
                                                    <span className="font-semibold">
                                                        {item.year}
                                                    </span>
                                                    {' — '}
                                                    {item.count} entr
                                                    {item.count === 1
                                                        ? 'y'
                                                        : 'ies'}
                                                    {item.date_from
                                                        ? ` (${item.date_from} to ${item.date_to})`
                                                        : ''}
                                                    {item.exists
                                                        ? ` · this year already has ${item.records_count} saved entries (the system will update matching ones)`
                                                        : ' · new year will be created'}
                                                </span>
                                            </label>
                                        );
                                    })}
                                </fieldset>

                                <InputError
                                    message={errors[`selections.${file.id}`]}
                                />
                            </CardContent>
                        </Card>
                    ))}

                    <InputError message={errors.selections} />

                    <div className="flex flex-wrap items-center gap-3">
                        <Button
                            type="submit"
                            disabled={processing || selectedCount === 0}
                        >
                            <Save className="h-4 w-4" />
                            Start saving
                            {selectedCount > 0
                                ? ` (${selectedCount} employee${selectedCount === 1 ? '' : 's'})`
                                : ''}
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={cancel}
                            disabled={processing}
                        >
                            Cancel and discard files
                        </Button>
                        <Button variant="link" className="px-0" asChild>
                            <Link href={route('years.index')}>
                                Back to years
                            </Link>
                        </Button>
                    </div>
                </form>
            </PageContainer>
        </AuthenticatedLayout>
    );
}
