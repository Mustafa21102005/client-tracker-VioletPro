<script setup lang="ts">
import FileDropzone from '@/components/FileDropzone.vue';
import RecordPicker from '@/components/RecordPicker.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { attachmentContext, fileKind, fileKindClasses, fileKindIcons, formatFileSize } from '@/lib/files';
import type { AttachmentWithContext } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps<{
    open: boolean;
    attachment: AttachmentWithContext | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

const form = useForm<{ note: string; file: File | null }>({
    note: '',
    file: null,
});

const pickedEdit = ref<number | null>(null);
const pickerKey = ref(0);
const editError = ref<string | null>(null);
const fileError = ref<string | null>(null);

watch(
    () => props.open,
    (open) => {
        if (!open) return;
        form.clearErrors();
        form.file = null;
        form.note = props.attachment?.note ?? '';
        pickedEdit.value = null;
        pickerKey.value++;
        editError.value = null;
        fileError.value = null;
    },
);

function submit() {
    editError.value = null;
    fileError.value = null;

    // When adding, both an edit and a file are required. When editing, neither is.
    if (!props.attachment) {
        if (!pickedEdit.value) editError.value = 'Choose the edit this file belongs to.';
        if (!form.file) fileError.value = 'Choose a file to upload.';
        if (editError.value || fileError.value) return;
    }

    const url = props.attachment ? `/attachments/${props.attachment.id}` : `/change-requests/${pickedEdit.value}/attachments`;

    // Browsers can't send a file with a real PUT request, so an edit is sent as a POST that
    // carries "_method: put". Laravel reads that field and treats it as a PUT.
    form.transform((data) => (props.attachment ? { ...data, _method: 'put' } : data)).post(url, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => emit('update:open', false),
    });
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg" @dragover.prevent @drop.prevent>
            <DialogHeader>
                <DialogTitle>{{ attachment ? 'Edit attachment' : 'Add attachment' }}</DialogTitle>
                <DialogDescription>
                    {{ attachment ? 'Change the note or replace the file.' : 'Upload a file and attach it to an edit.' }}
                </DialogDescription>
            </DialogHeader>

            <form class="grid gap-4" @submit.prevent="submit">
                <div v-if="attachment" class="grid gap-2">
                    <Label>Belongs to</Label>
                    <div class="rounded-lg border bg-muted/30 p-3">
                        <p class="truncate text-sm font-medium">{{ attachment.change_request?.title }}</p>
                        <p class="truncate text-xs text-muted-foreground">{{ attachmentContext(attachment) }}</p>
                    </div>
                </div>

                <div v-else class="grid gap-2">
                    <Label>Edit</Label>
                    <RecordPicker
                        :key="pickerKey"
                        v-model="pickedEdit"
                        endpoint="/edits/search"
                        placeholder="Search by edit, project, company or client..."
                        @update:model-value="editError = null"
                    />
                    <p v-if="editError" class="text-sm text-destructive">{{ editError }}</p>
                </div>

                <div class="grid gap-2">
                    <Label>{{ attachment ? 'Replace file (optional)' : 'File' }}</Label>

                    <div v-if="attachment?.file && !form.file" class="flex items-center gap-3 rounded-lg border bg-muted/30 p-3">
                        <div
                            class="flex size-10 shrink-0 items-center justify-center rounded-lg"
                            :class="fileKindClasses[fileKind(attachment.file.name)]"
                        >
                            <component :is="fileKindIcons[fileKind(attachment.file.name)]" class="size-5" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">{{ attachment.file.name }}</p>
                            <p class="text-xs text-muted-foreground">Current file · {{ formatFileSize(attachment.file.size) }}</p>
                        </div>
                    </div>

                    <FileDropzone v-model="form.file" :error="fileError ?? form.errors.file" />
                </div>

                <div class="grid gap-2">
                    <Label for="attachment-note-field">Note (optional)</Label>
                    <Input id="attachment-note-field" v-model="form.note" placeholder="e.g. Signed contract, screenshot of the bug" />
                    <p v-if="form.errors.note" class="text-sm text-destructive">{{ form.errors.note }}</p>
                </div>

                <div v-if="form.progress" class="space-y-1">
                    <div class="h-2 overflow-hidden rounded-full bg-muted">
                        <div
                            class="h-full rounded-full bg-primary transition-all duration-200"
                            :style="{ width: `${form.progress.percentage}%` }"
                        ></div>
                    </div>
                    <p class="text-right text-xs text-muted-foreground">{{ form.progress.percentage }}%</p>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" :disabled="form.processing" @click="emit('update:open', false)">Cancel</Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Saving...' : 'Save' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
