export default function Checkbox({ className = '', ...props }) {
    return (
        <input
            {...props}
            type="checkbox"
            className={
                'rounded border-slate-300 text-brand shadow-sm focus:ring-brand-accent ' +
                className
            }
        />
    );
}
