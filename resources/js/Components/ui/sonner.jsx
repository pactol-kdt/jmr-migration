import { useEffect } from 'react';
import { usePage } from '@/inertia';
import { Toaster, toast } from 'sonner';

export function AppToaster() {
    const { flash } = usePage().props;

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
        if (flash?.warning) toast.warning(flash.warning);
        if (flash?.info) toast.message(flash.info);
    }, [flash?.success, flash?.error, flash?.warning, flash?.info]);

    return (
        <Toaster
            position="top-right"
            richColors
            closeButton
            toastOptions={{
                className: 'font-sans',
            }}
        />
    );
}
