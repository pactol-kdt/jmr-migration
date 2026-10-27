import { Link } from '@/inertia';

export default function Pagination({ paginator, total }) {
    if (!paginator) {
        return null;
    }

    const from = paginator.from ?? 0;
    const to = paginator.to ?? 0;
    const resolvedTotal = total ?? paginator.total ?? null;
    const hasNavigation = Boolean(paginator.prev_page_url || paginator.next_page_url);

    if (!hasNavigation && (resolvedTotal === null || resolvedTotal === 0) && from === 0) {
        return null;
    }

    return (
        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200/80 px-4 py-3 text-sm text-muted sm:px-6">
            <p>
                Showing <span className="font-medium text-ink">{from}</span>–
                <span className="font-medium text-ink">{to}</span>
                {resolvedTotal != null && (
                    <>
                        {' '}
                        of{' '}
                        <span className="font-medium text-ink">
                            {Number(resolvedTotal).toLocaleString()}
                        </span>
                    </>
                )}
            </p>
            <div className="flex gap-2">
                {paginator.prev_page_url ? (
                    <Link
                        href={paginator.prev_page_url}
                        className="rounded-lg border border-slate-300 bg-panel px-3 py-1.5 font-medium text-ink hover:bg-surface"
                        preserveScroll
                    >
                        Previous
                    </Link>
                ) : (
                    <span className="rounded-lg border border-slate-200 px-3 py-1.5 text-slate-300">
                        Previous
                    </span>
                )}
                {paginator.next_page_url ? (
                    <Link
                        href={paginator.next_page_url}
                        className="rounded-lg border border-slate-300 bg-panel px-3 py-1.5 font-medium text-ink hover:bg-surface"
                        preserveScroll
                    >
                        Next
                    </Link>
                ) : (
                    <span className="rounded-lg border border-slate-200 px-3 py-1.5 text-slate-300">
                        Next
                    </span>
                )}
            </div>
        </div>
    );
}
