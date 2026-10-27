import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputError from '@/Components/InputError';
import PageContainer from '@/Components/PageContainer';
import { Button } from '@/Components/ui/button';
import {
    Card,
    CardContent,
} from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { cn } from '@/lib/utils';
import { Head, Link, router } from '@/inertia';
import { Upload } from 'lucide-react';
import { useState } from 'react';

function chunkFiles(files, size) {
    const chunks = [];
    for (let i = 0; i < files.length; i += size) {
        chunks.push(files.slice(i, i + size));
    }
    return chunks;
}

function validationMessage(error) {
    const data = error?.response?.data;
    if (!data) {
        return error?.message || 'Upload failed. Please try again.';
    }

    if (typeof data.message === 'string' && data.message && !data.errors) {
        return data.message;
    }

    const errors = data.errors || {};
    const messages = Object.values(errors).flat().filter(Boolean);
    if (messages.length > 0) {
        return messages[0];
    }

    return data.message || 'Upload failed. Please try again.';
}

function InlineAlert({ tone = 'info', children }) {
    return (
        <div
            className={cn(
                'rounded-lg border px-4 py-3 text-sm',
                tone === 'error' &&
                    'border-destructive/30 bg-destructive/10 text-destructive',
                tone === 'info' &&
                    'border-primary/20 bg-primary/5 text-foreground',
            )}
        >
            {children}
        </div>
    );
}

export default function Create({
    locked_year = null,
    upload_limits = { batch_size: 20, max_pending: 500, max_file_mb: 50 },
}) {
    const batchSize = upload_limits.batch_size ?? 20;
    const maxPending = upload_limits.max_pending ?? 500;
    const maxFileMb = upload_limits.max_file_mb ?? 50;

    const [files, setFiles] = useState([]);
    const [processing, setProcessing] = useState(false);
    const [progress, setProgress] = useState(null);
    const [error, setError] = useState(null);
    const [fieldErrors, setFieldErrors] = useState({});

    const fileCount = files.length;
    const batchCount = fileCount > 0 ? Math.ceil(fileCount / batchSize) : 0;

    const onFilesChosen = (e) => {
        const selected = Array.from(e.target.files || []);
        setError(null);
        setFieldErrors({});

        if (selected.length > maxPending) {
            setFiles([]);
            setError(
                `You can select at most ${maxPending} files at once. Split the upload into smaller sets.`,
            );
            e.target.value = '';
            return;
        }

        setFiles(selected);
    };

    const submit = async (e) => {
        e.preventDefault();
        if (fileCount === 0 || processing) {
            return;
        }

        setProcessing(true);
        setError(null);
        setFieldErrors({});
        setProgress({ batch: 0, totalBatches: batchCount, percentage: 0 });

        const batches = chunkFiles(files, batchSize);

        try {
            for (let i = 0; i < batches.length; i++) {
                const formData = new FormData();
                batches[i].forEach((file) => {
                    formData.append('xml_files[]', file);
                });
                if (i > 0) {
                    formData.append('append', '1');
                }

                setProgress({
                    batch: i + 1,
                    totalBatches: batches.length,
                    percentage: Math.round((i / batches.length) * 100),
                });

                await window.axios.post(route('imports.analyze'), formData, {
                    headers: { Accept: 'application/json' },
                    onUploadProgress: (event) => {
                        if (!event.total) {
                            return;
                        }
                        const withinBatch = event.loaded / event.total;
                        const overall =
                            ((i + withinBatch) / batches.length) * 100;
                        setProgress({
                            batch: i + 1,
                            totalBatches: batches.length,
                            percentage: Math.min(99, Math.round(overall)),
                        });
                    },
                });
            }

            setProgress({
                batch: batches.length,
                totalBatches: batches.length,
                percentage: 100,
            });
            router.visit(route('imports.confirm'));
        } catch (err) {
            const data = err?.response?.data;
            const nextFieldErrors = {};
            if (data?.errors) {
                Object.entries(data.errors).forEach(([key, messages]) => {
                    nextFieldErrors[key] = Array.isArray(messages)
                        ? messages[0]
                        : String(messages);
                });
            }
            setFieldErrors(nextFieldErrors);
            setError(validationMessage(err));
            setProcessing(false);
            setProgress(null);
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-foreground">
                    Upload XML files
                </h2>
            }
        >
            <Head title="Upload files" />

            <PageContainer width="3xl">
                {error && <InlineAlert tone="error">{error}</InlineAlert>}

                {locked_year && (
                    <InlineAlert tone="info">
                        Year context: <strong>{locked_year}</strong>
                    </InlineAlert>
                )}

                <Card>
                    <CardContent className="pt-6">
                        <form onSubmit={submit} className="space-y-6">
                            <div className="space-y-2">
                                <Label htmlFor="xml_files">XML files</Label>
                                <div className="rounded-lg border border-dashed border-input bg-muted/30 px-4 py-8 text-center transition hover:border-primary/40 hover:bg-muted/50">
                                    <Input
                                        id="xml_files"
                                        type="file"
                                        multiple
                                        accept=".xml,.txt,text/xml,application/xml,text/plain"
                                        className="h-auto cursor-pointer border-0 bg-transparent p-0 shadow-none file:mr-4 file:rounded-md file:border-0 file:bg-primary file:px-4 file:py-2 file:text-sm file:font-medium file:text-primary-foreground hover:file:bg-primary/90"
                                        onChange={onFilesChosen}
                                        required
                                        disabled={processing}
                                    />
                                    <p className="mt-3 text-xs text-muted-foreground">
                                        Up to {maxPending} files · {maxFileMb} MB each ·
                                        name each file year_employeeId, such as
                                        2017_1042.xml
                                    </p>
                                </div>
                                {fileCount > 0 && (
                                    <p className="text-sm font-medium text-foreground">
                                        {fileCount} file
                                        {fileCount === 1 ? '' : 's'} selected
                                        {batchCount > 1
                                            ? ` · ${batchCount} batches`
                                            : ''}
                                    </p>
                                )}
                                <InputError
                                    message={fieldErrors.xml_files}
                                    className="mt-2"
                                />
                            </div>

                            {Object.keys(fieldErrors)
                                .filter((key) => key.startsWith('xml_files.'))
                                .map((key) => (
                                    <InputError
                                        key={key}
                                        message={fieldErrors[key]}
                                    />
                                ))}

                            {progress && (
                                <div className="space-y-2 text-sm text-muted-foreground">
                                    <p>
                                        Uploading batch {progress.batch} of{' '}
                                        {progress.totalBatches}…{' '}
                                        {progress.percentage}%
                                    </p>
                                    <div className="h-2 overflow-hidden rounded-full bg-muted">
                                        <div
                                            className="h-full rounded-full bg-primary transition-all"
                                            style={{
                                                width: `${progress.percentage}%`,
                                            }}
                                        />
                                    </div>
                                </div>
                            )}

                            <div className="flex flex-wrap items-center gap-3">
                                <Button
                                    type="submit"
                                    disabled={processing || fileCount === 0}
                                >
                                    <Upload className="h-4 w-4" />
                                    {processing ? 'Uploading…' : 'Continue'}
                                </Button>
                                <Button variant="ghost" asChild>
                                    <Link href={route('years.index')}>
                                        Cancel
                                    </Link>
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </PageContainer>
        </AuthenticatedLayout>
    );
}
