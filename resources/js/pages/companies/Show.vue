<script setup lang="ts">
import CompanyFormModal from '@/components/companies/CompanyFormModal.vue';
import DeleteConfirmDialog from '@/components/DeleteConfirmDialog.vue';
import Pagination from '@/components/Pagination.vue';
import ProjectFormModal from '@/components/projects/ProjectFormModal.vue';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, CompanyWithClient, Paginated, Project } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Building2, CalendarDays, Pencil, Plus, Trash2, User } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    company: CompanyWithClient;
    projects: Paginated<Project>;
    changeRequestsCount: number;
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Clients', href: '/clients' },
    { title: props.company.client.name, href: `/clients/${props.company.client.id}` },
    { title: props.company.name, href: `/companies/${props.company.id}` },
]);

const companyFormOpen = ref(false);

const projectFormOpen = ref(false);
const editingProject = ref<Project | null>(null);

const deleteOpen = ref(false);
const deletingProject = ref<Project | null>(null);

function openCreateProject() {
    editingProject.value = null;
    projectFormOpen.value = true;
}

function openEditProject(project: Project) {
    editingProject.value = project;
    projectFormOpen.value = true;
}

function askDelete(project: Project) {
    deletingProject.value = project;
    deleteOpen.value = true;
}

function confirmDelete() {
    if (!deletingProject.value) return;

    router.delete(`/projects/${deletingProject.value.id}`, {
        preserveScroll: true,
        onFinish: () => (deleteOpen.value = false),
    });
}

const createdAt = computed(() => new Date(props.company.created_at).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }));
</script>

<template>
    <Head :title="company.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4">
            <div class="overflow-hidden rounded-xl border bg-card">
                <div class="h-1.5 bg-primary"></div>

                <div class="flex flex-col gap-4 p-6 sm:flex-row sm:items-start sm:justify-between">
                    <div class="flex items-start gap-4">
                        <div class="flex size-14 shrink-0 items-center justify-center rounded-xl bg-primary text-primary-foreground">
                            <Building2 class="size-7" />
                        </div>

                        <div class="space-y-1">
                            <h1 class="text-2xl font-semibold tracking-tight">{{ company.name }}</h1>
                            <Link
                                :href="`/clients/${company.client.id}`"
                                class="flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
                            >
                                <User class="size-4" />
                                {{ company.client.name }}
                            </Link>
                            <p v-if="company.description" class="max-w-2xl pt-2 text-sm text-muted-foreground">{{ company.description }}</p>
                        </div>
                    </div>

                    <Button variant="outline" size="sm" @click="companyFormOpen = true"> <Pencil class="h-4 w-4" /> Edit company </Button>
                </div>

                <div class="grid grid-cols-1 gap-3 border-t p-4 sm:grid-cols-3">
                    <div class="rounded-lg bg-muted/50 p-3">
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Projects</p>
                        <p class="mt-1 text-lg font-semibold">{{ projects.total }}</p>
                    </div>

                    <div class="rounded-lg bg-muted/50 p-3">
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Edits</p>
                        <p class="mt-1 text-lg font-semibold">{{ changeRequestsCount }}</p>
                    </div>

                    <div class="rounded-lg bg-muted/50 p-3">
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Created</p>
                        <p class="mt-1 flex items-center gap-1.5 text-lg font-semibold">
                            <CalendarDays class="size-4 text-muted-foreground" />
                            {{ createdAt }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between">
                <h2 class="text-lg font-medium">Projects</h2>
                <Button @click="openCreateProject"><Plus class="h-4 w-4" /> Add project</Button>
            </div>

            <div class="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead class="text-center">Description</TableHead>
                            <TableHead class="text-center">Edits</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="project in projects.data" :key="project.id">
                            <TableCell class="font-medium">
                                <Link :href="`/projects/${project.id}`" class="hover:text-primary hover:underline">
                                    {{ project.name }}
                                </Link>
                            </TableCell>
                            <TableCell class="max-w-xs truncate text-center text-muted-foreground">
                                {{ project.description ?? '—' }}
                            </TableCell>
                            <TableCell class="text-center">{{ project.change_requests_count }}</TableCell>
                            <TableCell class="text-right">
                                <Button variant="ghost" size="icon" @click="openEditProject(project)">
                                    <Pencil class="h-4 w-4" />
                                </Button>
                                <Button variant="ghost" size="icon" @click="askDelete(project)">
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="projects.data.length === 0">
                            <TableCell colspan="4" class="py-8 text-center text-muted-foreground">
                                No projects yet. Add the first one for this company.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <Pagination :links="projects.links" />
        </div>

        <CompanyFormModal v-model:open="companyFormOpen" :client-id="company.client_id" :company="company" />

        <ProjectFormModal v-model:open="projectFormOpen" :company-id="company.id" :project="editingProject" />

        <DeleteConfirmDialog
            v-model:open="deleteOpen"
            title="Delete project?"
            :description="`${deletingProject?.name ?? 'This project'} will be removed. Projects that still have edits can't be deleted.`"
            @confirm="confirmDelete"
        />
    </AppLayout>
</template>
