export default function PrimaryButton({
    className = '',
    disabled,
    children,
    ...props
}) {
    return (
        <button
            {...props}
            className={
                `inline-flex items-center justify-center rounded-xl bg-brand px-4 py-2.5 text-sm font-semibold text-white transition duration-150 ease-in-out hover:bg-brand-dark focus:outline-none focus:ring-2 focus:ring-brand-accent focus:ring-offset-2 active:bg-brand-dark ${
                    disabled && 'opacity-25'
                } ` + className
            }
            disabled={disabled}
        >
            {children}
        </button>
    );
}
