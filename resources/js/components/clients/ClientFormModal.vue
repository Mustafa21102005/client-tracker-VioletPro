<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Client } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

const props = defineProps<{
    open: boolean;
    client: Client | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

const form = useForm({
    name: '',
    phone: '',
    email: '',
    last_follow_up: '',
});

// Every time the modal opens, reload the fields from the selected client
// (or empty them for "Add"), and clear any old validation errors.
watch(
    () => props.open,
    (open) => {
        if (!open) return;
        form.clearErrors();
        form.name = props.client?.name ?? '';
        form.phone = props.client?.phone ?? '';
        form.email = props.client?.email ?? '';
        form.last_follow_up = props.client?.last_follow_up ?? '';
    },
);

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => emit('update:open', false),
    };

    if (props.client) {
        form.put(`/clients/${props.client.id}`, options);
    } else {
        form.post('/clients', options);
    }
}

const today = new Date().toLocaleDateString('en-CA');
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ client ? 'Edit client' : 'Add client' }}</DialogTitle>
                <DialogDescription>
                    {{ client ? 'Update the client details.' : "Save a new client's contact details." }}
                </DialogDescription>
            </DialogHeader>

            <form class="grid gap-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="name">Name</Label>
                    <Input id="name" v-model="form.name" />
                    <p v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</p>
                </div>

                <div class="grid gap-2">
                    <Label for="phone">Phone</Label>
                    <Input id="phone" v-model="form.phone" />
                    <p v-if="form.errors.phone" class="text-sm text-destructive">{{ form.errors.phone }}</p>
                </div>

                <div class="grid gap-2">
                    <Label for="email">Email (optional)</Label>
                    <Input id="email" v-model="form.email" type="email" />
                    <p v-if="form.errors.email" class="text-sm text-destructive">{{ form.errors.email }}</p>
                </div>

                <div class="grid gap-2">
                    <Label for="last_follow_up">Last follow-up (optional)</Label>
                    <Input id="last_follow_up" v-model="form.last_follow_up" type="date" :max="today" />
                    <p v-if="form.errors.last_follow_up" class="text-sm text-destructive">
                        {{ form.errors.last_follow_up }}
                    </p>
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
