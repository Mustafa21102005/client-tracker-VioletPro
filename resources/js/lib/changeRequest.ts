import type { ChangeRequestStatus, Priority } from '@/types';

export const priorityLabels: Record<Priority, string> = {
    low: 'Low',
    medium: 'Medium',
    high: 'High',
    urgent: 'Urgent',
};

export const statusLabels: Record<ChangeRequestStatus, string> = {
    pending: 'Pending',
    done: 'Done',
    cancelled: 'Cancelled',
};

export const priorityClasses: Record<Priority, string> = {
    low: 'bg-muted text-muted-foreground',
    medium: 'bg-secondary text-secondary-foreground',
    high: 'bg-primary/15 text-primary',
    urgent: 'bg-destructive/10 text-destructive',
};

export const statusClasses: Record<ChangeRequestStatus, string> = {
    pending: 'bg-secondary text-secondary-foreground',
    done: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400',
    cancelled: 'bg-muted text-muted-foreground line-through',
};

const CURRENCY = 'USD';

export function formatAmount(value: number): string {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: CURRENCY }).format(value);
}

export function formatPrice(value: string | null): string {
    return value === null ? '—' : formatAmount(Number(value));
}
