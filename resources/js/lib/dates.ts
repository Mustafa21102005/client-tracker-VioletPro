// Whole days between a YYYY-MM-DD date and today, in local time. null when there is no date.
export function daysSince(date: string | null): number | null {
    if (!date) return null;

    const then = new Date(`${date}T00:00:00`);
    const now = new Date();
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());

    return Math.round((today.getTime() - then.getTime()) / 86_400_000);
}

export function daysAgoLabel(days: number | null): string {
    if (days === null) return 'Never contacted';
    if (days <= 0) return 'Today';
    if (days === 1) return 'Yesterday';

    return `${days} days ago`;
}
