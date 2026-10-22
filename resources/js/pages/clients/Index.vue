<script setup lang="ts">
import ClientFormModal from '@/components/clients/ClientFormModal.vue';
import DeleteConfirmDialog from '@/components/DeleteConfirmDialog.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Client, Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, PhoneCall, Plus, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const formOpen = ref(false);
const editingClient = ref<Client | null>(null);

const deleteOpen = ref(false);
const deletingClient = ref<Client | null>(null);

function openCreate() {
    editingClient.value = null;
    formOpen.value = true;
}

function openEdit(client: Client) {
    editingClient.value = client;
    formOpen.value = true;
}

function askDelete(client: Client) {
    deletingClient.value = client;
    deleteOpen.value = true;
}

function confirmDelete() {
    if (!deletingClient.value) return;

    router.delete(`/clients/${deletingClient.value.id}`, {
        preserveScroll: true,
        onFinish: () => (deleteOpen.value = false),
    });
}

// Only used to grey out the button for clients already marked today.
// The server decides the real date, this is just a convenience.
const today = new Date().toLocaleDateString('en-CA');
const followingUpId = ref<number | null>(null);

function markFollowedUp(client: Client) {
    followingUpId.value = client.id;

    router.post(
        `/clients/${client.id}/follow-up`,
        {},
        {
            preserveScroll: true,
            onFinish: () => (followingUpId.value = null),
        },
    );
}

const props = defineProps<{
    clients: Paginated<Client>;
    filters: { search: string };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Clients', href: '/clients' }];

const search = ref(props.filters.search);
let timer: ReturnType<typeof setTimeout>;

watch(search, (value) => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get('/clients', { search: value || undefined }, { preserveState: true, replace: true });
    }, 300);
});

function resetSearch() {
    search.value = '';
}
</script>

<template>
    <Head title="Clients" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div class="flex items-center justify-between gap-4">
                <Input v-model="search" placeholder="Search by name, phone or email..." class="max-w-sm" />
                <Button @click="openCreate"><Plus class="h-4 w-4" /> Add client</Button>
            </div>

            <div class="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead class="text-center">Phone</TableHead>
                            <TableHead class="text-center">Email</TableHead>
                            <TableHead class="text-center">Last follow-up</TableHead>
                            <TableHead class="text-center">Companies</TableHead>
                            <TableHead class="text-center">Projects</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="client in clients.data" :key="client.id">
                            <TableCell class="font-medium">
                                <Link :href="`/clients/${client.id}`" class="hover:text-primary hover:underline">{{ client.name }}</Link>
                            </TableCell>
                            <TableCell class="text-center">{{ client.phone }}</TableCell>
                            <TableCell class="text-center">{{ client.email ?? '—' }}</TableCell>
                            <TableCell class="text-center">{{ client.last_follow_up ?? 'Never' }}</TableCell>
                            <TableCell class="text-center">{{ client.companies_count }}</TableCell>
                            <TableCell class="text-center">{{ client.projects_count }}</TableCell>
                            <TableCell class="text-right">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    :title="client.last_follow_up === today ? 'Already followed up today' : 'Mark as followed up today'"
                                    :aria-label="`Mark ${client.name} as followed up today`"
                                    :disabled="client.last_follow_up === today || followingUpId === client.id"
                                    @click="markFollowedUp(client)"
                                >
                                    <PhoneCall class="h-4 w-4" />
                                </Button>
                                <Button variant="ghost" size="icon" @click="openEdit(client)">
                                    <Pencil class="h-4 w-4" />
                                </Button>
                                <Button variant="ghost" size="icon" @click="askDelete(client)">
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="clients.data.length === 0">
                            <TableCell colspan="7" class="py-10 text-center">
                                <template v-if="filters.search">
                                    <p class="text-muted-foreground">No clients found for "{{ filters.search }}".</p>
                                    <Button variant="outline" size="sm" class="mt-3" @click="resetSearch">Clear search</Button>
                                </template>
                                <p v-else class="text-muted-foreground">No clients yet. Add your first client to get started.</p>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <Pagination :links="clients.links" />
        </div>

        <ClientFormModal v-model:open="formOpen" :client="editingClient" />

        <DeleteConfirmDialog
            v-model:open="deleteOpen"
            title="Delete client?"
            :description="`${deletingClient?.name ?? 'This client'} will be removed from the list. Clients that still have companies can't be deleted.`"
            @confirm="confirmDelete"
        />
    </AppLayout>
</template>
