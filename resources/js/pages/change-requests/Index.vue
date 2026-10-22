<script setup lang="ts">
import AttachmentsDialog from '@/components/change-requests/AttachmentsDialog.vue';
import ChangeRequestFormModal from '@/components/change-requests/ChangeRequestFormModal.vue';
import DeleteConfirmDialog from '@/components/DeleteConfirmDialog.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatAmount, formatPrice, priorityClasses, priorityLabels, statusClasses, statusLabels } from '@/lib/changeRequest';
import type { BreadcrumbItem, ChangeRequestWithProject, EditFilters, Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Banknote, Check, Paperclip, Pencil, Plus, Search, Trash2, X } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps<{
    edits: Paginated<ChangeRequestWithProject>;
    filters: EditFilters;
    summary: { value: number; unpaid: number };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Edits', href: '/edits' }];

// ---- Filters ----
const ALL = 'all';

const state = reactive({
    search: props.filters.search,
    status: props.filters.status ?? ALL,
    priority: props.filters.priority ?? ALL,
    payment: props.filters.payment ?? ALL,
    sort: props.filters.sort,
});

const hasFilters = computed(
    () => state.search !== '' || state.status !== ALL || state.priority !== ALL || state.payment !== ALL || state.sort !== 'newest',
);

function applyFilters() {
    router.get(
        '/edits',
        {
            search: state.search || undefined,
            status: state.status === ALL ? undefined : state.status,
            priority: state.priority === ALL ? undefined : state.priority,
            payment: state.payment === ALL ? undefined : state.payment,
            sort: state.sort === 'newest' ? undefined : state.sort,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

let searchTimer: ReturnType<typeof setTimeout>;

watch(
    () => state.search,
    () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(applyFilters, 300);
    },
);

watch(() => [state.status, state.priority, state.payment, state.sort], applyFilters);

function clearFilters() {
    state.search = '';
    state.status = ALL;
    state.priority = ALL;
    state.payment = ALL;
    state.sort = 'newest';
}

// ---- Add / edit / delete / attachments ----
const formOpen = ref(false);
const editingRequest = ref<ChangeRequestWithProject | null>(null);

const deleteOpen = ref(false);
const deletingRequest = ref<ChangeRequestWithProject | null>(null);

const attachmentsOpen = ref(false);
const attachmentsRequestId = ref<number | null>(null);

// Looked up in the current props every time, so the dialog shows fresh data after an upload.
const attachmentsRequest = computed(() => props.edits.data.find((edit) => edit.id === attachmentsRequestId.value) ?? null);

function openCreate() {
    editingRequest.value = null;
    formOpen.value = true;
}

function openEdit(edit: ChangeRequestWithProject) {
    editingRequest.value = edit;
    formOpen.value = true;
}

function askDelete(edit: ChangeRequestWithProject) {
    deletingRequest.value = edit;
    deleteOpen.value = true;
}

function confirmDelete() {
    if (!deletingRequest.value) return;

    router.delete(`/change-requests/${deletingRequest.value.id}`, {
        preserveScroll: true,
        onFinish: () => (deleteOpen.value = false),
    });
}

function openAttachments(edit: ChangeRequestWithProject) {
    attachmentsRequestId.value = edit.id;
    attachmentsOpen.value = true;
}

// ---- One-click actions ----
const busy = ref<string | null>(null);

function act(key: string, url: string) {
    busy.value = key;

    router.post(url, {}, { preserveScroll: true, onFinish: () => (busy.value = null) });
}

const canMarkDone = (edit: ChangeRequestWithProject) => edit.status === 'pending';
const canMarkPaid = (edit: ChangeRequestWithProject) => edit.price !== null && edit.paid_at === null && edit.status !== 'cancelled';

const context = (edit: ChangeRequestWithProject) =>
    [edit.project?.company?.client?.name, edit.project?.company?.name, edit.project?.name].filter(Boolean).join(' · ');

const formatDate = (value: string) => new Date(value).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
</script>

<template>
    <Head title="Edits" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Edits</h1>
                    <p class="text-sm text-muted-foreground">Every edit across all clients, companies and projects.</p>
                </div>
                <Button @click="openCreate"><Plus class="h-4 w-4" /> Add edit</Button>
            </div>

            <!-- Summary of the filtered results -->
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div class="rounded-lg border bg-card p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Results</p>
                    <p class="mt-1 text-xl font-semibold">{{ edits.total }}</p>
                </div>
                <div class="rounded-lg border bg-card p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Total value</p>
                    <p class="mt-1 text-xl font-semibold">{{ formatAmount(summary.value) }}</p>
                    <p class="text-xs text-muted-foreground">Cancelled edits excluded</p>
                </div>
                <div class="rounded-lg border bg-card p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Unpaid</p>
                    <p class="mt-1 text-xl font-semibold" :class="summary.unpaid > 0 ? 'text-destructive' : 'text-muted-foreground'">
                        {{ formatAmount(summary.unpaid) }}
                    </p>
                    <p class="text-xs text-muted-foreground">Cancelled edits excluded</p>
                </div>
            </div>

            <!-- Filters -->
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
                <div class="relative lg:max-w-sm lg:flex-1">
                    <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="state.search" class="pl-9" placeholder="Search edits, projects, companies, clients..." />
                </div>

                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:flex lg:flex-1">
                    <Select v-model="state.status">
                        <SelectTrigger class="lg:w-36"><SelectValue placeholder="Status" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All statuses</SelectItem>
                            <SelectItem v-for="(label, value) in statusLabels" :key="value" :value="value">{{ label }}</SelectItem>
                        </SelectContent>
                    </Select>

                    <Select v-model="state.priority">
                        <SelectTrigger class="lg:w-36"><SelectValue placeholder="Priority" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All priorities</SelectItem>
                            <SelectItem v-for="(label, value) in priorityLabels" :key="value" :value="value">{{ label }}</SelectItem>
                        </SelectContent>
                    </Select>

                    <Select v-model="state.payment">
                        <SelectTrigger class="lg:w-36"><SelectValue placeholder="Payment" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Any payment</SelectItem>
                            <SelectItem value="paid">Paid</SelectItem>
                            <SelectItem value="unpaid">Unpaid</SelectItem>
                            <SelectItem value="free">No price</SelectItem>
                        </SelectContent>
                    </Select>

                    <Select v-model="state.sort">
                        <SelectTrigger class="lg:w-44"><SelectValue placeholder="Sort" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="newest">Newest first</SelectItem>
                            <SelectItem value="oldest">Oldest first</SelectItem>
                            <SelectItem value="price_desc">Price: high to low</SelectItem>
                            <SelectItem value="price_asc">Price: low to high</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <Button v-if="hasFilters" variant="ghost" size="sm" @click="clearFilters"><X class="h-4 w-4" /> Clear</Button>
            </div>

            <!-- Table -->
            <div class="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Edit</TableHead>
                            <TableHead class="text-center">Priority</TableHead>
                            <TableHead class="text-center">Status</TableHead>
                            <TableHead class="text-center">Price</TableHead>
                            <TableHead class="text-center">Payment</TableHead>
                            <TableHead class="text-center">Created</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="edit in edits.data" :key="edit.id">
                            <TableCell>
                                <p class="font-medium">{{ edit.title }}</p>
                                <p v-if="edit.description" class="max-w-sm truncate text-sm text-muted-foreground">{{ edit.description }}</p>
                                <Link
                                    v-if="edit.project"
                                    :href="`/projects/${edit.project.id}`"
                                    class="text-xs text-muted-foreground hover:text-primary hover:underline"
                                >
                                    {{ context(edit) }}
                                </Link>
                            </TableCell>
                            <TableCell class="text-center">
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="priorityClasses[edit.priority]">
                                    {{ priorityLabels[edit.priority] }}
                                </span>
                            </TableCell>
                            <TableCell class="text-center">
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="statusClasses[edit.status]">
                                    {{ statusLabels[edit.status] }}
                                </span>
                            </TableCell>
                            <TableCell class="text-center">{{ formatPrice(edit.price) }}</TableCell>
                            <TableCell class="text-center text-sm">
                                <span v-if="edit.price === null" class="text-muted-foreground">—</span>
                                <span v-else-if="edit.paid_at">Paid on {{ edit.paid_at }}</span>
                                <span v-else class="font-medium text-destructive">Unpaid</span>
                            </TableCell>
                            <TableCell class="text-center text-sm text-muted-foreground">{{ formatDate(edit.created_at) }}</TableCell>
                            <TableCell class="whitespace-nowrap text-right">
                                <Button
                                    v-if="canMarkDone(edit)"
                                    variant="ghost"
                                    size="icon"
                                    title="Mark as done"
                                    :disabled="busy === `done-${edit.id}`"
                                    @click="act(`done-${edit.id}`, `/change-requests/${edit.id}/done`)"
                                >
                                    <Check class="h-4 w-4" />
                                </Button>
                                <Button
                                    v-if="canMarkPaid(edit)"
                                    variant="ghost"
                                    size="icon"
                                    title="Mark as paid"
                                    :disabled="busy === `paid-${edit.id}`"
                                    @click="act(`paid-${edit.id}`, `/change-requests/${edit.id}/paid`)"
                                >
                                    <Banknote class="h-4 w-4" />
                                </Button>
                                <Button variant="ghost" size="icon" class="relative" title="Attachments" @click="openAttachments(edit)">
                                    <Paperclip class="h-4 w-4" />
                                    <span
                                        v-if="edit.attachments?.length"
                                        class="absolute -right-0.5 -top-0.5 flex size-4 items-center justify-center rounded-full bg-primary text-[10px] font-medium text-primary-foreground"
                                    >
                                        {{ edit.attachments.length }}
                                    </span>
                                </Button>
                                <Button variant="ghost" size="icon" title="Edit" @click="openEdit(edit)">
                                    <Pencil class="h-4 w-4" />
                                </Button>
                                <Button variant="ghost" size="icon" title="Delete" @click="askDelete(edit)">
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </TableCell>
                        </TableRow>

                        <TableRow v-if="edits.data.length === 0">
                            <TableCell colspan="7" class="py-10 text-center">
                                <template v-if="hasFilters">
                                    <p class="text-muted-foreground">No edits match your filters.</p>
                                    <Button variant="outline" size="sm" class="mt-3" @click="clearFilters">Clear filters</Button>
                                </template>
                                <p v-else class="text-muted-foreground">No edits yet. Add the first one.</p>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <Pagination :links="edits.links" />
        </div>

        <ChangeRequestFormModal v-model:open="formOpen" :change-request="editingRequest" />

        <AttachmentsDialog v-model:open="attachmentsOpen" :change-request="attachmentsRequest" />

        <DeleteConfirmDialog
            v-model:open="deleteOpen"
            title="Delete edit?"
            :description="`${deletingRequest?.title ?? 'This edit'} will be removed.`"
            @confirm="confirmDelete"
        />
    </AppLayout>
</template>
