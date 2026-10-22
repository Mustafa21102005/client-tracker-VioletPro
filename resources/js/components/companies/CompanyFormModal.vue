<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { Company } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

// The modal only needs these three fields, so both a list row and a detail page can use it.
type EditableCompany = Pick<Company, 'id' | 'name' | 'description'>;

const props = defineProps<{
    open: boolean;
    clientId: number;
    company: EditableCompany | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

const form = useForm({
    name: '',
    description: '',
});

watch(
    () => props.open,
    (open) => {
        if (!open) return;
        form.clearErrors();
        form.name = props.company?.name ?? '';
        form.description = props.company?.description ?? '';
    },
);

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => emit('update:open', false),
    };

    if (props.company) {
        form.put(`/companies/${props.company.id}`, options);
    } else {
        form.post(`/clients/${props.clientId}/companies`, options);
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ company ? 'Edit company' : 'Add company' }}</DialogTitle>
                <DialogDescription>
                    {{ company ? 'Update the company details.' : 'A company this client works with.' }}
                </DialogDescription>
            </DialogHeader>

            <form class="grid gap-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="company-name">Name</Label>
                    <Input id="company-name" v-model="form.name" />
                    <p v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</p>
                </div>

                <div class="grid gap-2">
                    <Label for="company-description">Description (optional)</Label>
                    <Textarea id="company-description" v-model="form.description" :rows="4" />
                    <p v-if="form.errors.description" class="text-sm text-destructive">{{ form.errors.description }}</p>
                </div>

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
