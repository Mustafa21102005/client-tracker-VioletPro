<script setup lang="ts">
import ClientFormModal from '@/components/clients/ClientFormModal.vue';
import CompanyFormModal from '@/components/companies/CompanyFormModal.vue';
import DeleteConfirmDialog from '@/components/DeleteConfirmDialog.vue';
import Pagination from '@/components/Pagination.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Client, Company, Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Mail, Pencil, Phone, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    client: Client;
    companies: Paginated<Company>;
    changeRequestsCount: number;
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Clients', href: '/clients' },
    { title: props.client.name, href: `/clients/${props.client.id}` },
]);

const clientFormOpen = ref(false);

const companyFormOpen = ref(false);
const editingCompany = ref<Company | null>(null);

const deleteOpen = ref(false);
const deletingCompany = ref<Company | null>(null);

function openCreateCompany() {
    editingCompany.value = null;
    companyFormOpen.value = true;
}

function openEditCompany(company: Company) {
    editingCompany.value = company;
    companyFormOpen.value = true;
}

function askDelete(company: Company) {
    deletingCompany.value = company;
    deleteOpen.value = true;
}

function confirmDelete() {
    if (!deletingCompany.value) return;

    router.delete(`/companies/${deletingCompany.value.id}`, {
        preserveScroll: true,
        onFinish: () => (deleteOpen.value = false),
    });
}

// After this many days without a follow-up, the client gets a warning badge.
const FOLLOW_UP_ALERT_DAYS = 360;

const initials = computed(() =>
    props.client.name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((word) => word[0])
        .join('')
        .toUpperCase(),
);

const daysSinceFollowUp = computed<number | null>(() => {
    if (!props.client.last_follow_up) return null;

    const followUp = new Date(`${props.client.last_follow_up}T00:00:00`);
    const now = new Date();
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());

    return Math.round((today.getTime() - followUp.getTime()) / 86_400_000);
});

const followUpLabel = computed(() => {
    const days = daysSinceFollowUp.value;

    if (days === null) return 'Never';
    if (days <= 0) return 'Today';
    if (days === 1) return 'Yesterday';

    return `${days} days ago`;
});

const needsFollowUp = computed(() => daysSinceFollowUp.value === null || daysSinceFollowUp.value > FOLLOW_UP_ALERT_DAYS);
</script>

<template>
    <Head :title="client.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4">
            <div class="overflow-hidden rounded-xl border bg-card">
                <div class="h-1.5 bg-primary"></div>

                <div class="flex flex-col gap-4 p-6 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-4">
                        <Avatar class="size-16 bg-primary">
                            <AvatarFallback class="text-xl font-semibold text-primary-foreground">
                                {{ initials }}
                            </AvatarFallback>
                        </Avatar>

                        <div>
                            <h1 class="text-2xl font-semibold tracking-tight">{{ client.name }}</h1>
                            <div class="mt-2 flex flex-wrap gap-x-5 gap-y-1 text-sm text-muted-foreground">
                                <a :href="`tel:${client.phone}`" class="flex items-center gap-1.5 hover:text-foreground">
                                    <Phone class="size-4" />
                                    <span dir="ltr">{{ client.phone }}</span>
                                </a>
                                <a v-if="client.email" :href="`mailto:${client.email}`" class="flex items-center gap-1.5 hover:text-foreground">
                                    <Mail class="size-4" />
                                    {{ client.email }}
                                </a>
                                <span v-else class="flex items-center gap-1.5">
                                    <Mail class="size-4" />
                                    No email
                                </span>
                            </div>
                        </div>
                    </div>

                    <Button variant="outline" size="sm" @click="clientFormOpen = true"> <Pencil class="h-4 w-4" /> Edit client </Button>
                </div>

                <div class="grid grid-cols-2 gap-3 border-t p-4 sm:grid-cols-4">
                    <div class="rounded-lg bg-muted/50 p-3">
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Companies</p>
                        <p class="mt-1 text-lg font-semibold">{{ client.companies_count }}</p>
                    </div>

                    <div class="rounded-lg bg-muted/50 p-3">
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Projects</p>
                        <p class="mt-1 text-lg font-semibold">{{ client.projects_count }}</p>
                    </div>

                    <div class="rounded-lg bg-muted/50 p-3">
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Edits</p>
                        <p class="mt-1 text-lg font-semibold">{{ changeRequestsCount }}</p>
                    </div>

                    <div class="rounded-lg bg-muted/50 p-3">
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Last follow-up</p>
                        <div class="mt-1 flex flex-wrap items-center gap-2">
                            <p class="text-lg font-semibold">{{ followUpLabel }}</p>
                            <span v-if="needsFollowUp" class="rounded-full bg-destructive/10 px-2 py-0.5 text-xs font-medium text-destructive">
                                Needs follow-up
                            </span>
                        </div>
                        <p v-if="client.last_follow_up" class="text-xs text-muted-foreground">{{ client.last_follow_up }}</p>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between">
                <h2 class="text-lg font-medium">Companies</h2>
                <Button @click="openCreateCompany"><Plus class="h-4 w-4" /> Add company</Button>
            </div>

            <div class="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead class="text-center">Description</TableHead>
                            <TableHead class="text-center">Projects</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="company in companies.data" :key="company.id">
                            <TableCell class="font-medium">
                                <Link :href="`/companies/${company.id}`" class="hover:text-primary hover:underline">
                                    {{ company.name }}
                                </Link>
                            </TableCell>
                            <TableCell class="max-w-xs truncate text-center text-muted-foreground">
                                {{ company.description ?? '—' }}
                            </TableCell>
                            <TableCell class="text-center">{{ company.projects_count }}</TableCell>
                            <TableCell class="text-right">
                                <Button variant="ghost" size="icon" @click="openEditCompany(company)">
                                    <Pencil class="h-4 w-4" />
                                </Button>
                                <Button variant="ghost" size="icon" @click="askDelete(company)">
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="companies.data.length === 0">
                            <TableCell colspan="4" class="py-8 text-center text-muted-foreground">
                                No companies yet. Add the first one for this client.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <Pagination :links="companies.links" />
        </div>

        <ClientFormModal v-model:open="clientFormOpen" :client="client" />

        <CompanyFormModal v-model:open="companyFormOpen" :client-id="client.id" :company="editingCompany" />

        <DeleteConfirmDialog
            v-model:open="deleteOpen"
            title="Delete company?"
            :description="`${deletingCompany?.name ?? 'This company'} will be removed. Companies that still have projects can't be deleted.`"
            @confirm="confirmDelete"
        />
    </AppLayout>
</template>
