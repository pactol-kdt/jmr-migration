const WIDTHS = {
    '3xl': 'max-w-3xl',
    '4xl': 'max-w-4xl',
    '7xl': 'max-w-7xl',
};

export default function PageContainer({ width = '7xl', children }) {
    return (
        <div className="py-8">
            <div className={`mx-auto space-y-6 px-4 sm:px-6 lg:px-8 ${WIDTHS[width] ?? WIDTHS['7xl']}`}>
                {children}
            </div>
        </div>
    );
}
