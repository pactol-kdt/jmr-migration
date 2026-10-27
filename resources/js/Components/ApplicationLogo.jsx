export default function ApplicationLogo({
    className = '',
    variant = 'dark',
    ...props
}) {
    const light = variant === 'light';

    return (
        <span
            {...props}
            className={`inline-flex items-center gap-2.5 ${className}`}
        >
            <span
                className={`flex h-9 w-9 items-center justify-center rounded-xl shadow-sm ${
                    light
                        ? 'bg-white/15 text-white ring-1 ring-white/20'
                        : 'bg-brand text-white'
                }`}
            >
                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    className="h-5 w-5"
                    aria-hidden="true"
                >
                    <path
                        d="M6 16.5V12.2M12 16.5V8.5M18 16.5v-3.1"
                        stroke="currentColor"
                        strokeWidth="1.8"
                        strokeLinecap="round"
                    />
                    <rect
                        x="3.5"
                        y="4.5"
                        width="17"
                        height="15"
                        rx="3"
                        stroke="currentColor"
                        strokeWidth="1.6"
                    />
                </svg>
            </span>
            <span className="leading-tight">
                <span
                    className={`block text-[15px] font-semibold tracking-tight ${
                        light ? 'text-white' : 'text-brand'
                    }`}
                >
                    JMR
                </span>
                <span
                    className={`block text-[11px] font-medium uppercase tracking-[0.16em] ${
                        light ? 'text-white/60' : 'text-muted'
                    }`}
                >
                    Migration
                </span>
            </span>
        </span>
    );
}
