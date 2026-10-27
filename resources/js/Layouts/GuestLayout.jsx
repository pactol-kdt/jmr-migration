import ApplicationLogo from '@/Components/ApplicationLogo';
import { Database, FolderOpen, Upload } from 'lucide-react';

const highlights = [
    { icon: Upload, label: 'Import XML man-hour files' },
    { icon: FolderOpen, label: 'Organize records by year' },
    { icon: Database, label: 'Search, filter, and export' },
];

export default function GuestLayout({ children }) {
    return (
        <div className="grid min-h-screen bg-background lg:grid-cols-[minmax(22rem,40%)_1fr]">
            <aside className="sidebar-sheen relative hidden overflow-hidden bg-primary px-10 py-12 text-primary-foreground lg:flex lg:flex-col">
                <div className="pointer-events-none absolute -right-20 top-10 h-72 w-72 rounded-full bg-accent/25 blur-3xl" />
                <div className="pointer-events-none absolute -bottom-24 -left-10 h-80 w-80 rounded-full bg-black/20 blur-3xl" />

                <div className="relative">
                    <ApplicationLogo variant="light" />
                </div>

                <div className="relative my-auto max-w-md py-16">
                    <p className="text-xs font-semibold uppercase tracking-[0.2em] text-white/45">
                        Internal system
                    </p>
                    <h1 className="mt-4 text-4xl font-semibold leading-[1.15] tracking-tight">
                        Man-hour records,
                        <br />
                        ready to browse.
                    </h1>
                    <p className="mt-5 text-base leading-relaxed text-white/70">
                        Bring XML exports into one place, keep years separate, and
                        find what you need without digging through files.
                    </p>

                    <ul className="mt-10 space-y-3">
                        {highlights.map(({ icon: Icon, label }) => (
                            <li
                                key={label}
                                className="flex items-center gap-3 rounded-lg border border-white/10 bg-white/5 px-3.5 py-2.5 text-sm text-white/85 backdrop-blur-sm"
                            >
                                <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-white/10">
                                    <Icon className="h-4 w-4" />
                                </span>
                                {label}
                            </li>
                        ))}
                    </ul>
                </div>

                <p className="relative text-xs text-white/40">
                    JMR Migration · authorized staff only
                </p>
            </aside>

            <div className="relative flex flex-col justify-center px-6 py-12 sm:px-10">
                <div
                    className="pointer-events-none absolute inset-0 opacity-[0.35]"
                    style={{
                        backgroundImage:
                            'radial-gradient(circle at 1px 1px, hsl(var(--border)) 1px, transparent 0)',
                        backgroundSize: '24px 24px',
                    }}
                />
                <div className="relative mb-10 lg:hidden">
                    <ApplicationLogo />
                </div>
                <div className="relative mx-auto w-full max-w-md">{children}</div>
            </div>
        </div>
    );
}
