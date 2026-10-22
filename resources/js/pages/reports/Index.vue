<script setup lang="ts">
import MultiSearchSelect from '@/components/MultiSearchSelect.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatAmount, priorityClasses, priorityLabels, statusClasses, statusLabels } from '@/lib/changeRequest';
import type {
    BreadcrumbItem,
    ChangeRequestStatus,
    Paginated,
    PickerOption,
    Priority,
    ReportColumn,
    ReportFilterValues,
    ReportRow,
    ReportTab,
} from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronDown, Download, X } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps<{
    tab: ReportTab;
    tabs: { key: ReportTab; label: string; count: number }[];
    columns: ReportColumn[];
    rows: Paginated<ReportRow>;
    filters: ReportFilterValues;
    selected: {
        clients: PickerOption[];
        companies: PickerOption[];
        projects: PickerOption[];
        edits: PickerOption[];
    };
    summary: { value: number; paid: number; unpaid: number } | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Reports', href: '/reports' }];

// ---- What the user has picked on screen ----
const state = reactive({
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
    clients: [...props.selected.clients],
    companies: [...props.selected.companies],
    projects: [...props.selected.projects],
    edits: [...props.selected.edits],
});

const groups = [
    { key: 'clients', label: 'Client', endpoint: '/clients/search', placeholder: 'Search clients...' },
    { key: 'companies', label: 'Company', endpoint: '/companies/search', placeholder: 'Search companies or their client...' },
    { key: 'projects', label: 'Project', endpoint: '/projects/search', placeholder: 'Search projects...' },
    { key: 'edits', label: 'Edit', endpoint: '/edits/search', placeholder: 'Search edits...' },
] as const;

const today = new Date().toLocaleDateString('en-CA');

// ---- Loading the page with the chosen filters ----
const loading = ref(false);

function currentParams(tab: ReportTab = props.tab) {
    return {
        tab: tab === 'edits' ? undefined : tab,
        from: state.from || undefined,
        to: state.to || undefined,
        clients: state.clients.length ? state.clients.map((option) => option.id) : undefined,
        companies: state.companies.length ? state.companies.map((option) => option.id) : undefined,
        projects: state.projects.length ? state.projects.map((option) => option.id) : undefined,
        edits: state.edits.length ? state.edits.map((option) => option.id) : undefined,
    };
}

function load(tab: ReportTab = props.tab) {
    router.get('/reports', currentParams(tab), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onStart: () => (loading.value = true),
        onFinish: () => (loading.value = false),
    });
}

let timer: ReturnType<typeof setTimeout>;

// Any change waits 300 ms (in case several come together), then reloads the page data.
watch(
    state,
    () => {
        clearTimeout(timer);
        timer = setTimeout(() => load(), 300);
    },
    { deep: true },
);

// Keep the date range sensible: the end can't come before the start.
watch(
    () => state.from,
    (from) => {
        if (from && state.to && from > state.to) state.to = from;
    },
);

watch(
    () => state.to,
    (to) => {
        if (to && state.from && to < state.from) state.from = to;
    },
);

function setRange(preset: 'month' | 'days30' | 'year') {
    const now = new Date();
    const start = new Date(now);

    if (preset === 'month') start.setDate(1);
    if (preset === 'days30') start.setDate(now.getDate() - 29);

    if (preset === 'year') {
        start.setMonth(0);
        start.setDate(1);
    }

    state.from = start.toLocaleDateString('en-CA');
    state.to = now.toLocaleDateString('en-CA');
}

// ---- Chips: every chosen filter shown as a small removable tag ----
const chips = computed(() => groups.flatMap((group) => state[group.key].map((option) => ({ group: group.key, groupLabel: group.label, option }))));

const hasFilters = computed(() => chips.value.length > 0 || state.from !== '' || state.to !== '');

function removeChip(chip: { group: (typeof groups)[number]['key']; option: PickerOption }) {
    state[chip.group] = state[chip.group].filter((option) => option.id !== chip.option.id);
}

function clearAll() {
    state.from = '';
    state.to = '';
    state.clients = [];
    state.companies = [];
    state.projects = [];
    state.edits = [];
}

// ---- Export ----
// Built from what the SERVER applied to the table (props.filters), not from what is on screen
// this second. That way the file always matches the table you are looking at.
function buildQuery(params: Record<string, string | number[] | null | undefined>): string {
    const search = new URLSearchParams();

    Object.entries(params).forEach(([key, value]) => {
        if (value === null || value === undefined || value === '') return;

        if (Array.isArray(value)) {
            value.forEach((item) => search.append(`${key}[]`, String(item)));
        } else {
            search.set(key, value);
        }
    });

    return search.toString();
}

const exportUrl = (scope: ReportTab | 'all') =>
    `/reports/export?${buildQuery({
        tab: scope,
        from: props.filters.from,
        to: props.filters.to,
        clients: props.filters.clients,
        companies: props.filters.companies,
        projects: props.filters.projects,
        edits: props.filters.edits,
    })}`;

const currentTab = computed(() => props.tabs.find((tab) => tab.key === props.tab));

// ---- Table cells ----
const formatDate = (value: string) => new Date(`${value}T00:00:00`).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });

function alignClass(column: ReportColumn): string {
    if (column.type === 'money' || column.type === 'number') return 'text-right tabular-nums';
    if (column.type === 'text') return 'text-left';

    return 'text-center';
}
</script>

<template>
    <Head title="Reports" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Reports</h1>
                    <p class="text-sm text-muted-foreground">Filter anything, then export exactly what you see to Excel.</p>
                </div>

                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button><Download class="h-4 w-4" /> Export to Excel <ChevronDown class="h-4 w-4 opacity-70" /></Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-72">
                        <DropdownMenuItem as-child>
                            <a :href="exportUrl(tab)" class="flex flex-col items-start gap-0.5">
                                <span class="font-medium">This tab: {{ currentTab?.label }}</span>
                                <span class="text-xs text-muted-foreground">{{ currentTab?.count }} rows, with the current filters</span>
                            </a>
                        </DropdownMenuItem>
                        <DropdownMenuItem as-child>
                            <a :href="exportUrl('all')" class="flex flex-col items-start gap-0.5">
                                <span class="font-medium">All tabs</span>
                                <span class="text-xs text-muted-foreground">One file with 4 sheets, with the current filters</span>
                            </a>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>

            <!-- Filters -->
            <div class="space-y-4 rounded-xl border bg-card p-4">
                <div class="flex flex-wrap items-end gap-3">
                    <div class="grid gap-1.5">
                        <Label for="report-from">Created from</Label>
                        <Input id="report-from" v-model="state.from" type="date" :max="state.to || today" class="w-40" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="report-to">Created to</Label>
                        <Input id="report-to" v-model="state.to" type="date" :min="state.from || undefined" :max="today" class="w-40" />
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button variant="outline" size="sm" @click="setRange('month')">This month</Button>
                        <Button variant="outline" size="sm" @click="setRange('days30')">Last 30 days</Button>
                        <Button variant="outline" size="sm" @click="setRange('year')">This year</Button>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <MultiSearchSelect
                        v-for="group in groups"
                        :key="group.key"
                        v-model="state[group.key]"
                        :label="group.label"
                        :endpoint="group.endpoint"
                        :placeholder="group.placeholder"
                    />
                </div>

                <div v-if="hasFilters" class="flex flex-wrap items-center gap-2">
                    <span
                        v-for="chip in chips"
                        :key="`${chip.group}-${chip.option.id}`"
                        class="inline-flex items-center gap-1.5 rounded-full border bg-background py-1 pl-3 pr-1.5 text-xs"
                    >
                        <span class="text-muted-foreground">{{ chip.groupLabel }}:</span>
                        <span class="max-w-48 truncate font-medium">{{ chip.option.title }}</span>
                        <button
                            type="button"
                            class="rounded-full p-0.5 transition-colors hover:bg-muted"
                            :aria-label="`Remove ${chip.option.title}`"
                            @click="removeChip(chip)"
                        >
                            <X class="size-3" />
                        </button>
                    </span>
                    <Button variant="ghost" size="sm" @click="clearAll"><X class="h-4 w-4" /> Clear all</Button>
                </div>
            </div>

            <!-- Tabs -->
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="item in tabs"
                    :key="item.key"
                    type="button"
                    class="flex items-center gap-2 rounded-full border px-3.5 py-1.5 text-sm transition-colors"
                    :class="tab === item.key ? 'border-primary bg-primary text-primary-foreground' : 'hover:border-primary/50 hover:bg-primary/5'"
                    @click="load(item.key)"
                >
                    {{ item.label }}
                    <span
                        class="rounded-full px-1.5 text-xs"
                        :class="tab === item.key ? 'bg-primary-foreground/20' : 'bg-muted text-muted-foreground'"
                    >
                        {{ item.count }}
                    </span>
                </button>
            </div>

            <!-- Totals (Edits tab only) -->
            <div v-if="summary" class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div class="rounded-lg border bg-card p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Total value</p>
                    <p class="mt-1 text-xl font-semibold">{{ formatAmount(summary.value) }}</p>
                    <p class="text-xs text-muted-foreground">Cancelled edits excluded</p>
                </div>
                <div class="rounded-lg border bg-card p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Paid</p>
                    <p class="mt-1 text-xl font-semibold text-emerald-600 dark:text-emerald-400">{{ formatAmount(summary.paid) }}</p>
                </div>
                <div class="rounded-lg border bg-card p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Unpaid</p>
                    <p class="mt-1 text-xl font-semibold" :class="summary.unpaid > 0 ? 'text-destructive' : 'text-muted-foreground'">
                        {{ formatAmount(summary.unpaid) }}
                    </p>
                </div>
            </div>

            <!-- Table -->
            <div class="rounded-md border transition-opacity duration-200" :class="{ 'opacity-60': loading }">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead v-for="column in columns" :key="column.key" :class="alignClass(column)">{{ column.label }}</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="row in rows.data" :key="Number(row.id)">
                            <TableCell v-for="column in columns" :key="column.key" :class="alignClass(column)">
                                <span
                                    v-if="column.type === 'priority'"
                                    class="rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="priorityClasses[row[column.key] as Priority]"
                                >
                                    {{ priorityLabels[row[column.key] as Priority] }}
                                </span>
                                <span
                                    v-else-if="column.type === 'status'"
                                    class="rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="statusClasses[row[column.key] as ChangeRequestStatus]"
                                >
                                    {{ statusLabels[row[column.key] as ChangeRequestStatus] }}
                                </span>
                                <template v-else-if="column.type === 'money'">
                                    {{ row[column.key] === null ? '—' : formatAmount(Number(row[column.key])) }}
                                </template>
                                <template v-else-if="column.type === 'date'">
                                    {{ row[column.key] ? formatDate(String(row[column.key])) : '—' }}
                                </template>
                                <Link
                                    v-else-if="column.link && row._url"
                                    :href="String(row._url)"
                                    class="font-medium hover:text-primary hover:underline"
                                >
                                    {{ row[column.key] }}
                                </Link>
                                <template v-else>{{ row[column.key] ?? '—' }}</template>
                            </TableCell>
                        </TableRow>

                        <TableRow v-if="rows.data.length === 0">
                            <TableCell :colspan="columns.length" class="py-10 text-center">
                                <template v-if="hasFilters">
                                    <p class="text-muted-foreground">Nothing matches these filters.</p>
                                    <Button variant="outline" size="sm" class="mt-3" @click="clearAll">Clear filters</Button>
                                </template>
                                <p v-else class="text-muted-foreground">Nothing here yet.</p>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <Pagination :links="rows.links" />
        </div>
    </AppLayout>
</template>
