<script setup lang="ts">
import AttachmentsDialog from '@/components/change-requests/AttachmentsDialog.vue';
import ChangeRequestFormModal from '@/components/change-requests/ChangeRequestFormModal.vue';
import DeleteConfirmDialog from '@/components/DeleteConfirmDialog.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatAmount, formatPrice, priorityClasses, priorityLabels, statusClasses, statusLabels } from '@/lib/changeRequest';
import type { BreadcrumbItem, ChangeRequest, Paginated, ProjectStats, ProjectWithCompany } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Building2, CalendarDays, FolderKanban, Paperclip, Pencil, Plus, Trash2, User } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    project: ProjectWithCompany;
    changeRequests: Paginated<ChangeRequest>;
    stats: ProjectStats;
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Clients', href: '/clients' },
    { title: props.project.company.client.name, href: `/clients/${props.project.company.client.id}` },
    { title: props.project.company.name, href: `/companies/${props.project.company.id}` },
    { title: props.project.name, href: `/projects/${props.project.id}` },
]);

const formOpen = ref(false);
const editingRequest = ref<ChangeRequest | null>(null);

const deleteOpen = ref(false);
const deletingRequest = ref<ChangeRequest | null>(null);

function openCreate() {
    editingRequest.value = null;
    formOpen.value = true;
}

function openEdit(changeRequest: ChangeRequest) {
    editingRequest.value = changeRequest;
    formOpen.value = true;
}

function askDelete(changeRequest: ChangeRequest) {
    deletingRequest.value = changeRequest;
    deleteOpen.value = true;
}

function confirmDelete() {
    if (!deletingRequest.value) return;

    router.delete(`/change-requests/${deletingRequest.value.id}`, {
        preserveScroll: true,
        onFinish: () => (deleteOpen.value = false),
    });
}

const attachmentsOpen = ref(false);
const attachmentsRequestId = ref<number | null>(null);

// Always look the request up in the *current* props. After an upload the page reloads with fresh
// data, and this keeps the dialog in sync instead of showing an old copy.
const attachmentsRequest = computed(() => props.changeRequests.data.find((request) => request.id === attachmentsRequestId.value) ?? null);

function openAttachments(changeRequest: ChangeRequest) {
    attachmentsRequestId.value = changeRequest.id;
    attachmentsOpen.value = true;
}

const progress = computed(() => (props.stats.active === 0 ? 0 : Math.round((props.stats.done / props.stats.active) * 100)));

const createdAt = computed(() => new Date(props.project.created_at).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }));
</script>

<template>
    <Head :title="project.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4">
            <div class="overflow-hidden rounded-xl border bg-card">
                <div class="h-1.5 bg-primary"></div>

                <div class="flex items-start gap-4 p-6">
                    <div class="flex size-14 shrink-0 items-center justify-center rounded-xl bg-primary text-primary-foreground">
                        <FolderKanban class="size-7" />
                    </div>

                    <div class="space-y-1">
                        <h1 class="text-2xl font-semibold tracking-tight">{{ project.name }}</h1>
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted-foreground">
                            <Link :href="`/companies/${project.company.id}`" class="flex items-center gap-1.5 hover:text-foreground">
                                <Building2 class="size-4" />
                                {{ project.company.name }}
                            </Link>
                            <Link :href="`/clients/${project.company.client.id}`" class="flex items-center gap-1.5 hover:text-foreground">
                                <User class="size-4" />
                                {{ project.company.client.name }}
                            </Link>
                            <span class="flex items-center gap-1.5">
                                <CalendarDays class="size-4" />
                                Created {{ createdAt }}
                            </span>
                        </div>
                        <p v-if="project.description" class="max-w-2xl pt-2 text-sm text-muted-foreground">{{ project.description }}</p>
                    </div>
                </div>

                <div class="px-6 pb-5">
                    <div class="mb-1.5 flex justify-between text-xs text-muted-foreground">
                        <span>Progress</span>
                        <span>{{ stats.done }} of {{ stats.active }} done</span>
                    </div>
                    <div class="h-2 overflow-hidden rounded-full bg-muted">
                        <div class="h-full rounded-full bg-primary transition-all" :style="{ width: `${progress}%` }"></div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 border-t p-4 sm:grid-cols-4">
                    <div class="rounded-lg bg-muted/50 p-3">
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Requests</p>
                        <p class="mt-1 text-lg font-semibold">{{ stats.total }}</p>
                    </div>

                    <div class="rounded-lg bg-muted/50 p-3">
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Pending</p>
                        <p class="mt-1 text-lg font-semibold">{{ stats.pending }}</p>
                    </div>

                    <div class="rounded-lg bg-muted/50 p-3">
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Total value</p>
                        <p class="mt-1 text-lg font-semibold">{{ formatAmount(stats.total_value) }}</p>
                    </div>

                    <div class="rounded-lg bg-muted/50 p-3">
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Unpaid</p>
                        <p class="mt-1 text-lg font-semibold" :class="stats.unpaid_value > 0 ? 'text-destructive' : 'text-muted-foreground'">
                            {{ formatAmount(stats.unpaid_value) }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between">
                <h2 class="text-lg font-medium">Edits</h2>
                <Button @click="openCreate"><Plus class="h-4 w-4" /> Add a new edit</Button>
            </div>

            <div class="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Title</TableHead>
                            <TableHead class="text-center">Priority</TableHead>
                            <TableHead class="text-center">Status</TableHead>
                            <TableHead class="text-center">Price</TableHead>
                            <TableHead class="text-center">Payment</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="changeRequest in changeRequests.data" :key="changeRequest.id">
                            <TableCell>
                                <p class="font-medium">{{ changeRequest.title }}</p>
                                <p v-if="changeRequest.description" class="max-w-sm truncate text-sm text-muted-foreground">
                                    {{ changeRequest.description }}
                                </p>
                            </TableCell>
                            <TableCell class="text-center">
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="priorityClasses[changeRequest.priority]">
                                    {{ priorityLabels[changeRequest.priority] }}
                                </span>
                            </TableCell>
                            <TableCell class="text-center">
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="statusClasses[changeRequest.status]">
                                    {{ statusLabels[changeRequest.status] }}
                                </span>
                            </TableCell>
                            <TableCell class="text-center">{{ formatPrice(changeRequest.price) }}</TableCell>
                            <TableCell class="text-center text-sm">
                                <span v-if="changeRequest.price === null" class="text-muted-foreground">—</span>
                                <span v-else-if="changeRequest.paid_at">Paid on {{ changeRequest.paid_at }}</span>
                                <span v-else class="font-medium text-destructive">Unpaid</span>
                            </TableCell>
                            <TableCell class="text-right">
                                <Button variant="ghost" size="icon" class="relative" @click="openAttachments(changeRequest)">
                                    <Paperclip class="h-4 w-4" />
                                    <span
                                        v-if="changeRequest.attachments?.length"
                                        class="absolute -right-0.5 -top-0.5 flex size-4 items-center justify-center rounded-full bg-primary text-[10px] font-medium text-primary-foreground"
                                    >
                                        {{ changeRequest.attachments.length }}
                                    </span>
                                </Button>
                                <Button variant="ghost" size="icon" @click="openEdit(changeRequest)">
                                    <Pencil class="h-4 w-4" />
                                </Button>
                                <Button variant="ghost" size="icon" @click="askDelete(changeRequest)">
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="changeRequests.data.length === 0">
                            <TableCell colspan="6" class="py-8 text-center text-muted-foreground">
                                No edits yet. Add the first one for this project.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
            <Pagination :links="changeRequests.links" />
        </div>

        <ChangeRequestFormModal v-model:open="formOpen" :project-id="project.id" :change-request="editingRequest" />

        <DeleteConfirmDialog
            v-model:open="deleteOpen"
            title="Delete edit?"
            :description="`${deletingRequest?.title ?? 'This request'} will be removed from the project.`"
            @confirm="confirmDelete"
        />

        <AttachmentsDialog v-model:open="attachmentsOpen" :change-request="attachmentsRequest" />
    </AppLayout>
</template>
