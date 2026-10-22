<script setup lang="ts">
import AttachmentFormModal from '@/components/attachments/AttachmentFormModal.vue';
import DeleteConfirmDialog from '@/components/DeleteConfirmDialog.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import { attachmentContext, fileExtension, fileKind, fileKindClasses, fileKindIcons, formatFileSize } from '@/lib/files';
import type { AttachmentFilters, AttachmentWithContext, BreadcrumbItem, Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ExternalLink, Pencil, Plus, Search, Trash2, X } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps<{
    attachments: Paginated<AttachmentWithContext>;
    filters: AttachmentFilters;
    summary: { size: number };
    typeCounts: { all: number; pdf: number; word: number; image: number };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Attachments', href: '/attachments' }];

// ---- Filters ----
const ALL = 'all';

const typeTabs = [
    { value: 'all', label: 'All' },
    { value: 'pdf', label: 'PDF' },
    { value: 'word', label: 'Word' },
    { value: 'image', label: 'Images' },
] as const;

const state = reactive({
    search: props.filters.search,
    type: props.filters.type ?? ALL,
    sort: props.filters.sort,
});

const hasFilters = computed(() => state.search !== '' || state.type !== ALL || state.sort !== 'newest');

function applyFilters() {
    router.get(
        '/attachments',
        {
            search: state.search || undefined,
            type: state.type === ALL ? undefined : state.type,
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

watch(() => [state.type, state.sort], applyFilters);

function clearFilters() {
    state.search = '';
    state.type = ALL;
    state.sort = 'newest';
}

// ---- Add / edit / delete ----
const formOpen = ref(false);
const editingAttachment = ref<AttachmentWithContext | null>(null);

const deleteOpen = ref(false);
const deletingAttachment = ref<AttachmentWithContext | null>(null);

function openCreate() {
    editingAttachment.value = null;
    formOpen.value = true;
}

function openEdit(attachment: AttachmentWithContext) {
    editingAttachment.value = attachment;
    formOpen.value = true;
}

function askDelete(attachment: AttachmentWithContext) {
    deletingAttachment.value = attachment;
    deleteOpen.value = true;
}

function confirmDelete() {
    if (!deletingAttachment.value) return;

    router.delete(`/attachments/${deletingAttachment.value.id}`, {
        preserveScroll: true,
        onFinish: () => (deleteOpen.value = false),
    });
}

const kindOf = (attachment: AttachmentWithContext) => fileKind(attachment.file?.name ?? '');
const formatDate = (value: string) => new Date(value).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
</script>

<template>
    <Head title="Attachments" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Attachments</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ attachments.total }} {{ attachments.total === 1 ? 'file' : 'files' }} · {{ formatFileSize(summary.size) }}
                        <span v-if="hasFilters">(matching your filters)</span>
                    </p>
                </div>
                <Button @click="openCreate"><Plus class="h-4 w-4" /> Add attachment</Button>
            </div>

            <!-- Type tabs -->
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="tab in typeTabs"
                    :key="tab.value"
                    type="button"
                    class="flex items-center gap-2 rounded-full border px-3.5 py-1.5 text-sm transition-colors"
                    :class="
                        state.type === tab.value ? 'border-primary bg-primary text-primary-foreground' : 'hover:border-primary/50 hover:bg-primary/5'
                    "
                    @click="state.type = tab.value"
                >
                    {{ tab.label }}
                    <span
                        class="rounded-full px-1.5 text-xs"
                        :class="state.type === tab.value ? 'bg-primary-foreground/20' : 'bg-muted text-muted-foreground'"
                    >
                        {{ typeCounts[tab.value] }}
                    </span>
                </button>
            </div>

            <!-- Search and sort -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="relative sm:max-w-sm sm:flex-1">
                    <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="state.search" class="pl-9" placeholder="Search files, notes, edits, projects, clients..." />
                </div>

                <Select v-model="state.sort">
                    <SelectTrigger class="sm:w-48"><SelectValue placeholder="Sort" /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="newest">Newest first</SelectItem>
                        <SelectItem value="oldest">Oldest first</SelectItem>
                        <SelectItem value="name">Name (A to Z)</SelectItem>
                        <SelectItem value="size">Largest first</SelectItem>
                    </SelectContent>
                </Select>

                <Button v-if="hasFilters" variant="ghost" size="sm" @click="clearFilters"><X class="h-4 w-4" /> Clear</Button>
            </div>

            <!-- Grid -->
            <div v-if="attachments.data.length" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                <div
                    v-for="attachment in attachments.data"
                    :key="attachment.id"
                    class="group flex flex-col overflow-hidden rounded-xl border bg-card transition-all duration-200 animate-in fade-in slide-in-from-bottom-2 hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-md"
                >
                    <a
                        :href="`/attachments/${attachment.id}/file`"
                        target="_blank"
                        rel="noopener"
                        class="relative block h-36 overflow-hidden bg-muted"
                    >
                        <img
                            v-if="kindOf(attachment) === 'image'"
                            :src="`/attachments/${attachment.id}/file`"
                            loading="lazy"
                            alt=""
                            class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                        />
                        <div
                            v-else
                            class="flex h-full flex-col items-center justify-center gap-2 transition-transform duration-300 group-hover:scale-105"
                            :class="fileKindClasses[kindOf(attachment)]"
                        >
                            <component :is="fileKindIcons[kindOf(attachment)]" class="size-10" />
                            <span class="text-xs font-semibold uppercase tracking-wide">{{ fileExtension(attachment.file?.name ?? '') }}</span>
                        </div>
                    </a>

                    <div class="flex flex-1 flex-col gap-2 p-4">
                        <a
                            :href="`/attachments/${attachment.id}/file`"
                            target="_blank"
                            rel="noopener"
                            class="truncate text-sm font-medium hover:text-primary hover:underline"
                        >
                            {{ attachment.file?.name ?? 'Missing file' }}
                        </a>
                        <p v-if="attachment.note" class="line-clamp-2 text-sm text-muted-foreground">{{ attachment.note }}</p>

                        <Link
                            v-if="attachment.change_request?.project"
                            :href="`/projects/${attachment.change_request.project.id}`"
                            class="mt-auto block rounded-md bg-muted/50 px-2.5 py-1.5 text-xs transition-colors hover:bg-primary/10"
                        >
                            <span class="block truncate font-medium text-foreground">{{ attachment.change_request.title }}</span>
                            <span class="block truncate text-muted-foreground">{{ attachmentContext(attachment) }}</span>
                        </Link>
                    </div>

                    <div class="flex items-center justify-between border-t px-4 py-2">
                        <span class="text-xs text-muted-foreground">
                            {{ attachment.file ? formatFileSize(attachment.file.size) : '—' }} · {{ formatDate(attachment.created_at) }}
                        </span>
                        <div class="flex">
                            <Button as-child variant="ghost" size="icon" class="h-8 w-8">
                                <a
                                    :href="`/attachments/${attachment.id}/file`"
                                    target="_blank"
                                    rel="noopener"
                                    aria-label="Open file"
                                    title="Open file"
                                >
                                    <ExternalLink class="h-4 w-4" />
                                </a>
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="h-8 w-8"
                                title="Edit"
                                aria-label="Edit attachment"
                                @click="openEdit(attachment)"
                            >
                                <Pencil class="h-4 w-4" />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="h-8 w-8"
                                title="Delete"
                                aria-label="Delete attachment"
                                @click="askDelete(attachment)"
                            >
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty states -->
            <div v-else class="flex flex-col items-center gap-3 rounded-xl border border-dashed py-16 text-center">
                <template v-if="hasFilters">
                    <p class="text-muted-foreground">No attachments match your filters.</p>
                    <Button variant="outline" size="sm" @click="clearFilters">Clear filters</Button>
                </template>
                <template v-else>
                    <p class="text-muted-foreground">No attachments yet. Upload the first one.</p>
                    <Button size="sm" @click="openCreate"><Plus class="h-4 w-4" /> Add attachment</Button>
                </template>
            </div>

            <Pagination :links="attachments.links" />
        </div>

        <AttachmentFormModal v-model:open="formOpen" :attachment="editingAttachment" />

        <DeleteConfirmDialog
            v-model:open="deleteOpen"
            title="Delete attachment?"
            :description="`${deletingAttachment?.file?.name ?? 'This file'} will be permanently removed from the server. This can't be undone.`"
            @confirm="confirmDelete"
        />
    </AppLayout>
</template>
