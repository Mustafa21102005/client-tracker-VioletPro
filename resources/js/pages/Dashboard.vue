<script setup lang="ts">
import ClientFormModal from '@/components/clients/ClientFormModal.vue';
import DashboardCard from '@/components/dashboard/DashboardCard.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatAmount, priorityClasses, priorityLabels, statusClasses, statusLabels } from '@/lib/changeRequest';
import { daysAgoLabel, daysSince } from '@/lib/dates';
import type { BreadcrumbItem, ChangeRequestStatus, DashboardRequest, FollowUpClient, SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    Banknote,
    Building2,
    Check,
    ClipboardList,
    Flame,
    FolderKanban,
    ListChecks,
    Phone,
    PhoneCall,
    Plus,
    TrendingUp,
    Users,
    Wallet,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

const props = defineProps<{
    followUpDays: number;
    totals: { clients: number; companies: number; projects: number };
    kpis: {
        outstanding: number;
        outstanding_count: number;
        collected_this_month: number;
        pending: number;
        follow_ups_due: number;
    };
    revenue: { label: string; total: number }[];
    statusCounts: Record<ChangeRequestStatus, number>;
    followUps: FollowUpClient[];
    unpaid: DashboardRequest[];
    attention: DashboardRequest[];
}>();

const page = usePage<SharedData>();
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

// ---- Header ----
const firstName = computed(() => page.props.auth.user.name.trim().split(/\s+/)[0]);

const greeting = computed(() => {
    const hour = new Date().getHours();

    if (hour < 12) return 'Good morning';
    if (hour < 18) return 'Good afternoon';

    return 'Good evening';
});

const todayLabel = new Date().toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

// ---- KPI cards ----
type Tone = 'neutral' | 'good' | 'warn' | 'bad';

const toneClasses: Record<Tone, string> = {
    neutral: 'bg-primary/10 text-primary',
    good: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
    warn: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
    bad: 'bg-destructive/10 text-destructive',
};

const plural = (count: number, word: string) => `${count} ${word}${count === 1 ? '' : 's'}`;

const kpiCards = computed(() => [
    {
        title: 'Outstanding',
        value: formatAmount(props.kpis.outstanding),
        hint: props.kpis.outstanding_count ? `${plural(props.kpis.outstanding_count, 'unpaid request')}` : 'Nothing owed',
        icon: Wallet,
        tone: (props.kpis.outstanding > 0 ? 'warn' : 'good') as Tone,
    },
    {
        title: 'Collected this month',
        value: formatAmount(props.kpis.collected_this_month),
        hint: 'Payments received',
        icon: Banknote,
        tone: 'good' as Tone,
    },
    {
        title: 'Pending requests',
        value: String(props.kpis.pending),
        hint: 'Waiting to be done',
        icon: ClipboardList,
        tone: 'neutral' as Tone,
    },
    {
        title: 'Follow-ups due',
        value: String(props.kpis.follow_ups_due),
        hint: `No contact in ${props.followUpDays}+ days`,
        icon: PhoneCall,
        tone: (props.kpis.follow_ups_due > 0 ? 'bad' : 'good') as Tone,
    },
]);

// ---- Charts: the bars start at zero and grow once the page is mounted ----
const chartReady = ref(false);
onMounted(() => requestAnimationFrame(() => (chartReady.value = true)));

const maxRevenue = computed(() => Math.max(...props.revenue.map((month) => month.total), 1));
const hasRevenue = computed(() => props.revenue.some((month) => month.total > 0));

function barHeight(total: number): string {
    if (!chartReady.value || total === 0) return '0%';

    return `${Math.max((total / maxRevenue.value) * 100, 6)}%`;
}

const compactNumber = new Intl.NumberFormat('en-US', { notation: 'compact', maximumFractionDigits: 1 });

const statusDots: Record<ChangeRequestStatus, string> = {
    done: 'bg-emerald-500',
    pending: 'bg-primary',
    cancelled: 'bg-muted-foreground/40',
};

const statusTotal = computed(() => Object.values(props.statusCounts).reduce((sum, count) => sum + count, 0));

const statusSegments = computed(() =>
    (['done', 'pending', 'cancelled'] as ChangeRequestStatus[]).map((key) => ({
        key,
        label: statusLabels[key],
        count: props.statusCounts[key],
        percent: statusTotal.value ? (props.statusCounts[key] / statusTotal.value) * 100 : 0,
    })),
);

// ---- Quick actions ----
const clientFormOpen = ref(false);
const busy = ref<string | null>(null);

function act(key: string, url: string) {
    busy.value = key;

    router.post(url, {}, { preserveScroll: true, onFinish: () => (busy.value = null) });
}

const context = (request: DashboardRequest) => [request.client, request.company, request.project].filter(Boolean).join(' · ');
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">{{ greeting }}, {{ firstName }}</h1>
                    <p class="text-sm text-muted-foreground">{{ todayLabel }}</p>
                    <div class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-sm text-muted-foreground">
                        <span class="flex items-center gap-1.5"><Users class="size-4" /> {{ plural(totals.clients, 'client') }}</span>
                        <span class="flex items-center gap-1.5"
                            ><Building2 class="size-4" /> {{ plural(totals.companies, 'company').replace('companys', 'companies') }}</span
                        >
                        <span class="flex items-center gap-1.5"><FolderKanban class="size-4" /> {{ plural(totals.projects, 'project') }}</span>
                    </div>
                </div>

                <div class="flex gap-2">
                    <Button @click="clientFormOpen = true"><Plus class="h-4 w-4" /> New client</Button>
                    <Button as-child variant="outline">
                        <Link href="/clients"><Users class="h-4 w-4" /> All clients</Link>
                    </Button>
                </div>
            </div>

            <!-- KPI cards -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div
                    v-for="card in kpiCards"
                    :key="card.title"
                    class="rounded-xl border bg-card p-5 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-sm"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm text-muted-foreground">{{ card.title }}</p>
                            <p class="mt-2 truncate text-2xl font-semibold tracking-tight">{{ card.value }}</p>
                        </div>
                        <div class="flex size-10 shrink-0 items-center justify-center rounded-lg" :class="toneClasses[card.tone]">
                            <component :is="card.icon" class="size-5" />
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-muted-foreground">{{ card.hint }}</p>
                </div>
            </div>

            <!-- Charts -->
            <div class="grid gap-4 lg:grid-cols-3">
                <DashboardCard title="Revenue" subtitle="Payments received, last 6 months" :icon="TrendingUp" class="lg:col-span-2">
                    <div class="p-5">
                        <div v-if="hasRevenue" class="flex h-48 gap-3">
                            <div v-for="month in revenue" :key="month.label" class="flex min-h-0 flex-1 flex-col">
                                <span class="h-5 text-center text-xs text-muted-foreground">
                                    {{ month.total > 0 ? compactNumber.format(month.total) : '' }}
                                </span>
                                <div class="flex min-h-0 flex-1 items-end border-b">
                                    <div
                                        class="w-full rounded-t-md bg-primary/80 transition-[height,background-color] duration-700 ease-out hover:bg-primary"
                                        :style="{ height: barHeight(month.total) }"
                                        :title="formatAmount(month.total)"
                                    ></div>
                                </div>
                                <span class="pt-2 text-center text-xs">{{ month.label }}</span>
                            </div>
                        </div>
                        <p v-else class="py-14 text-center text-sm text-muted-foreground">
                            No payments yet. Mark a request as paid and it will show up here.
                        </p>
                    </div>
                </DashboardCard>

                <DashboardCard title="Requests by status" :subtitle="`${statusTotal} in total`" :icon="ListChecks">
                    <div class="space-y-5 p-5">
                        <div class="flex h-3 overflow-hidden rounded-full bg-muted">
                            <div
                                v-for="segment in statusSegments"
                                :key="segment.key"
                                class="h-full transition-[width] duration-700 ease-out"
                                :class="statusDots[segment.key]"
                                :style="{ width: chartReady ? `${segment.percent}%` : '0%' }"
                            ></div>
                        </div>

                        <ul class="space-y-3">
                            <li v-for="segment in statusSegments" :key="segment.key" class="flex items-center gap-3 text-sm">
                                <span class="size-2.5 rounded-full" :class="statusDots[segment.key]"></span>
                                <span class="flex-1">{{ segment.label }}</span>
                                <span class="font-medium">{{ segment.count }}</span>
                                <span class="w-10 text-right text-xs text-muted-foreground">{{ Math.round(segment.percent) }}%</span>
                            </li>
                        </ul>
                    </div>
                </DashboardCard>
            </div>

            <!-- Action lists -->
            <div class="grid gap-4 lg:grid-cols-3">
                <DashboardCard title="Follow-ups due" :subtitle="`No contact in ${followUpDays}+ days`" :icon="PhoneCall">
                    <TransitionGroup v-if="followUps.length" name="row" tag="ul" class="relative divide-y">
                        <li v-for="client in followUps" :key="client.id" class="flex items-center gap-3 bg-card px-5 py-3">
                            <div class="min-w-0 flex-1">
                                <Link :href="`/clients/${client.id}`" class="block truncate text-sm font-medium hover:text-primary hover:underline">
                                    {{ client.name }}
                                </Link>
                                <p class="text-xs text-muted-foreground">{{ daysAgoLabel(daysSince(client.last_follow_up)) }}</p>
                            </div>
                            <Button as-child variant="ghost" size="icon" class="shrink-0">
                                <a :href="`tel:${client.phone}`" :aria-label="`Call ${client.name}`"><Phone class="h-4 w-4" /></a>
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                :disabled="busy === `follow-${client.id}`"
                                @click="act(`follow-${client.id}`, `/clients/${client.id}/follow-up`)"
                            >
                                Done today
                            </Button>
                        </li>
                    </TransitionGroup>
                    <div v-else class="flex flex-col items-center gap-2 px-5 py-10 text-center text-sm text-muted-foreground">
                        <Check class="size-6 text-emerald-500" />
                        Everyone has been contacted recently.
                    </div>
                </DashboardCard>

                <DashboardCard title="Unpaid work" subtitle="Done requests first" :icon="Wallet">
                    <TransitionGroup v-if="unpaid.length" name="row" tag="ul" class="relative divide-y">
                        <li v-for="request in unpaid" :key="request.id" class="flex items-center gap-3 bg-card px-5 py-3">
                            <div class="min-w-0 flex-1">
                                <Link
                                    :href="`/projects/${request.project_id}`"
                                    class="block truncate text-sm font-medium hover:text-primary hover:underline"
                                >
                                    {{ request.title }}
                                </Link>
                                <p class="truncate text-xs text-muted-foreground">{{ context(request) }}</p>
                                <p class="mt-1 flex items-center gap-2 text-xs">
                                    <span class="font-semibold">{{ formatAmount(request.price ?? 0) }}</span>
                                    <span class="rounded-full px-2 py-0.5 font-medium" :class="statusClasses[request.status]">
                                        {{ statusLabels[request.status] }}
                                    </span>
                                </p>
                            </div>
                            <Button
                                size="sm"
                                variant="outline"
                                :disabled="busy === `paid-${request.id}`"
                                @click="act(`paid-${request.id}`, `/change-requests/${request.id}/paid`)"
                            >
                                Mark paid
                            </Button>
                        </li>
                    </TransitionGroup>
                    <div v-else class="flex flex-col items-center gap-2 px-5 py-10 text-center text-sm text-muted-foreground">
                        <Check class="size-6 text-emerald-500" />
                        Nothing is waiting for payment.
                    </div>
                </DashboardCard>

                <DashboardCard title="Urgent & pending" subtitle="High and urgent priority" :icon="Flame">
                    <TransitionGroup v-if="attention.length" name="row" tag="ul" class="relative divide-y">
                        <li v-for="request in attention" :key="request.id" class="flex items-center gap-3 bg-card px-5 py-3">
                            <div class="min-w-0 flex-1">
                                <Link
                                    :href="`/projects/${request.project_id}`"
                                    class="block truncate text-sm font-medium hover:text-primary hover:underline"
                                >
                                    {{ request.title }}
                                </Link>
                                <p class="truncate text-xs text-muted-foreground">{{ context(request) }}</p>
                                <span
                                    class="mt-1 inline-block rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="priorityClasses[request.priority]"
                                >
                                    {{ priorityLabels[request.priority] }}
                                </span>
                            </div>
                            <Button
                                size="sm"
                                variant="outline"
                                :disabled="busy === `done-${request.id}`"
                                @click="act(`done-${request.id}`, `/change-requests/${request.id}/done`)"
                            >
                                Mark done
                            </Button>
                        </li>
                    </TransitionGroup>
                    <div v-else class="flex flex-col items-center gap-2 px-5 py-10 text-center text-sm text-muted-foreground">
                        <Check class="size-6 text-emerald-500" />
                        No urgent requests waiting.
                    </div>
                </DashboardCard>
            </div>
        </div>

        <ClientFormModal v-model:open="clientFormOpen" :client="null" />
    </AppLayout>
</template>

<style scoped>
.row-enter-active,
.row-leave-active {
    transition: all 0.3s ease;
}

.row-enter-from {
    opacity: 0;
    transform: translateY(8px);
}

.row-leave-to {
    opacity: 0;
    transform: translateX(24px);
}

/* A leaving row is taken out of the flow, so the rows below glide up. */
.row-leave-active {
    position: absolute;
    left: 0;
    right: 0;
}

.row-move {
    transition: transform 0.3s ease;
}

@media (prefers-reduced-motion: reduce) {
    .row-enter-active,
    .row-leave-active,
    .row-move {
        transition: none;
    }
}
</style>
