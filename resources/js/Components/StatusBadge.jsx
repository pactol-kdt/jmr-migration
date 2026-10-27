import { Badge } from '@/Components/ui/badge';
import { cn } from '@/lib/utils';

const STATUS_VARIANT = {
    pending: 'secondary',
    processing: 'default',
    completed: 'outline',
    failed: 'destructive',
};

const STATUS_LABEL = {
    pending: 'Waiting',
    processing: 'In progress',
    completed: 'Done',
    failed: 'Failed',
};

export default function StatusBadge({ status }) {
    return (
        <Badge
            variant={STATUS_VARIANT[status] ?? 'secondary'}
            className={cn(
                status === 'completed' &&
                    'border-transparent bg-success-soft text-success hover:bg-success-soft',
                status === 'processing' &&
                    'bg-accent text-accent-foreground hover:bg-accent/90',
            )}
        >
            {STATUS_LABEL[status] ?? status}
        </Badge>
    );
}
