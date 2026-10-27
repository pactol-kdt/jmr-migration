import { Info } from 'lucide-react';
import { useRef, useState } from 'react';
import { createPortal } from 'react-dom';

export default function InfoTip({ label }) {
    const buttonRef = useRef(null);
    const [tip, setTip] = useState(null);

    const show = () => {
        const rect = buttonRef.current?.getBoundingClientRect();

        if (!rect) {
            return;
        }

        const above = rect.top > 48;

        setTip({
            top: above ? rect.top - 8 : rect.bottom + 8,
            left: rect.left + rect.width / 2,
            above,
        });
    };

    return (
        <>
            <button
                ref={buttonRef}
                type="button"
                className="inline-flex rounded-sm text-muted-foreground hover:text-foreground"
                aria-label={label}
                onMouseEnter={show}
                onMouseLeave={() => setTip(null)}
                onFocus={show}
                onBlur={() => setTip(null)}
            >
                <Info className="h-3.5 w-3.5" />
            </button>
            {tip &&
                createPortal(
                    <span
                        role="tooltip"
                        className={`pointer-events-none fixed z-[80] -translate-x-1/2 whitespace-nowrap rounded-md bg-foreground px-2 py-1 text-xs font-medium text-background shadow-md ${
                            tip.above ? '-translate-y-full' : ''
                        }`}
                        style={{ top: tip.top, left: tip.left }}
                    >
                        {label}
                    </span>,
                    document.body,
                )}
        </>
    );
}
