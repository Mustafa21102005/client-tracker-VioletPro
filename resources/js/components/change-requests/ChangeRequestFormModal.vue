<script setup lang="ts">
import RecordPicker from '@/components/RecordPicker.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { priorityLabels, statusLabels } from '@/lib/changeRequest';
import type { ChangeRequest } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    open: boolean;
    projectId?: number | null;
    changeRequest: ChangeRequest | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

const form = useForm({
    title: '',
    description: '',
    priority: 'medium',
    status: 'pending',
    price: '',
    paid_at: '',
});

const pickedProject = ref<number | null>(null);
const projectError = ref<string | null>(null);
const pickerKey = ref(0);

// Only needed when adding from a page that doesn't already know the project.
const needsProject = computed(() => !props.changeRequest && !props.projectId);

watch(
    () => props.open,
    (open) => {
        if (!open) return;
        form.clearErrors();
        form.title = props.changeRequest?.title ?? '';
        form.description = props.changeRequest?.description ?? '';
        form.priority = props.changeRequest?.priority ?? 'medium';
        form.status = props.changeRequest?.status ?? 'pending';
        form.price = props.changeRequest?.price ?? '';
        form.paid_at = props.changeRequest?.paid_at ?? '';
        pickedProject.value = null;
        projectError.value = null;
        pickerKey.value++;
    },
);

// A request without a price can't be paid, so clear the date if the price is emptied.
watch(
    () => form.price,
    (price) => {
        if (!price) form.paid_at = '';
    },
);

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => emit('update:open', false),
    };

    if (props.changeRequest) {
        form.put(`/change-requests/${props.changeRequest.id}`, options);
        return;
    }

    const projectId = props.projectId ?? pickedProject.value;

    if (!projectId) {
        projectError.value = 'Choose a project first.';
        return;
    }

    form.post(`/projects/${projectId}/change-requests`, options);
}

const today = new Date().toLocaleDateString('en-CA');
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ changeRequest ? 'Edit an edit' : 'Add a new edit' }}</DialogTitle>
                <DialogDescription>A new feature or edit the client asked for on this project.</DialogDescription>
            </DialogHeader>

            <form class="grid gap-4" @submit.prevent="submit">
                <div v-if="needsProject" class="grid gap-2">
                    <Label>Project</Label>
                    <RecordPicker
                        :key="pickerKey"
                        v-model="pickedProject"
                        endpoint="/projects/search"
                        placeholder="Search by project, company or client..."
                        @update:model-value="projectError = null"
                    />
                    <p v-if="projectError" class="text-sm text-destructive">{{ projectError }}</p>
                </div>

                <div class="grid gap-2">
                    <Label for="cr-title">Title</Label>
                    <Input id="cr-title" v-model="form.title" />
                    <p v-if="form.errors.title" class="text-sm text-destructive">{{ form.errors.title }}</p>
                </div>

                <div class="grid gap-2">
                    <Label for="cr-description">Description (optional)</Label>
                    <Textarea id="cr-description" v-model="form.description" :rows="3" />
                    <p v-if="form.errors.description" class="text-sm text-destructive">{{ form.errors.description }}</p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-2">
                        <Label>Priority</Label>
                        <Select v-model="form.priority">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="(label, value) in priorityLabels" :key="value" :value="value">
                                    {{ label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.priority" class="text-sm text-destructive">{{ form.errors.priority }}</p>
                    </div>

                    <div class="grid gap-2">
                        <Label>Status</Label>
                        <Select v-model="form.status">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="(label, value) in statusLabels" :key="value" :value="value">
                                    {{ label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.status" class="text-sm text-destructive">{{ form.errors.status }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-2">
                        <Label for="cr-price">Price in USD (optional)</Label>
                        <Input id="cr-price" v-model="form.price" type="number" min="0" step="0.01" />
                        <p v-if="form.errors.price" class="text-sm text-destructive">{{ form.errors.price }}</p>
                    </div>

                    <div class="grid gap-2">
                        <Label for="cr-paid">Paid on</Label>
                        <Input id="cr-paid" v-model="form.paid_at" type="date" :max="today" :disabled="!form.price" />
                        <p v-if="form.errors.paid_at" class="text-sm text-destructive">{{ form.errors.paid_at }}</p>
                    </div>
                </div>
                <p class="-mt-2 text-xs text-muted-foreground">Leave "Paid on" empty while the request is unpaid.</p>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="emit('update:open', false)">Cancel</Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Saving...' : 'Save' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
