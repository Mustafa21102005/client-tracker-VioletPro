<script setup lang="ts">
import FileDropzone from '@/components/FileDropzone.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { fileExtension, fileKind, fileKindClasses, fileKindIcons, formatFileSize, type FileKind } from '@/lib/files';
import type { Attachment, ChangeRequest } from '@/types';
import { router, useForm } from '@inertiajs/vue3';
import { ExternalLink, Paperclip, Trash2, Upload } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    open: boolean;
    changeRequest: ChangeRequest | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

const form = useForm<{ note: string; file: File | null }>({
    note: '',
    file: null,
});

const confirmingId = ref<number | null>(null);
const count = computed(() => props.changeRequest?.attachments?.length ?? 0);

function kindOf(attachment: Attachment): FileKind {
    return fileKind(attachment.file?.name ?? '');
}

watch(
    () => props.open,
    (open) => {
        if (!open) return;
        form.reset();
        form.clearErrors();
        confirmingId.value = null;
    },
);

function upload() {
    if (!props.changeRequest) return;

    form.post(`/change-requests/${props.changeRequest.id}/attachments`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function remove(id: number) {
    router.delete(`/attachments/${id}`, {
        preserveScroll: true,
        onFinish: () => (confirmingId.value = null),
    });
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <!-- dragover/drop are blocked on the whole dialog, so a file dropped slightly outside
             the zone doesn't make the browser navigate away to the file. -->
        <DialogContent class="max-h-[85vh] gap-5 overflow-y-auto sm:max-w-lg" @dragover.prevent @drop.prevent>
            <DialogHeader>
                <div class="flex items-center gap-3">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <Paperclip class="size-5" />
                    </div>
                    <div class="min-w-0 text-left">
                        <DialogTitle class="flex items-center gap-2">
                            Attachments
                            <span v-if="count" class="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary">{{ count }}</span>
                        </DialogTitle>
                        <DialogDescription class="truncate">{{ changeRequest?.title }}</DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <div v-if="changeRequest" class="space-y-5">
                <!-- Existing files -->
                <TransitionGroup v-if="count" name="file-list" tag="ul" class="relative flex flex-col gap-2">
                    <li
                        v-for="attachment in changeRequest.attachments"
                        :key="attachment.id"
                        class="group flex items-center gap-3 rounded-lg border bg-card p-3 transition-colors hover:border-primary/40 hover:bg-muted/40"
                    >
                        <a :href="`/attachments/${attachment.id}/file`" target="_blank" rel="noopener" class="shrink-0">
                            <img
                                v-if="kindOf(attachment) === 'image'"
                                :src="`/attachments/${attachment.id}/file`"
                                loading="lazy"
                                alt=""
                                class="size-11 rounded-lg object-cover ring-1 ring-border transition-transform duration-200 group-hover:scale-105"
                            />
                            <div
                                v-else
                                class="flex size-11 items-center justify-center rounded-lg transition-transform duration-200 group-hover:scale-105"
                                :class="fileKindClasses[kindOf(attachment)]"
                            >
                                <component :is="fileKindIcons[kindOf(attachment)]" class="size-5" />
                            </div>
                        </a>

                        <div class="min-w-0 flex-1">
                            <a
                                :href="`/attachments/${attachment.id}/file`"
                                target="_blank"
                                rel="noopener"
                                class="block max-w-56 truncate text-sm font-medium hover:text-primary hover:underline"
                            >
                                {{ attachment.file?.name }}
                            </a>
                            <p v-if="attachment.note" class="truncate text-sm text-muted-foreground">{{ attachment.note }}</p>
                            <p v-if="attachment.file" class="text-xs uppercase text-muted-foreground">
                                {{ fileExtension(attachment.file.name) }} · {{ formatFileSize(attachment.file.size) }}
                            </p>
                        </div>

                        <div
                            class="flex shrink-0 items-center gap-1 transition-all duration-200 ease-out"
                            :class="
                                confirmingId === attachment.id
                                    ? 'opacity-100'
                                    : 'sm:pointer-events-none sm:translate-x-2 sm:opacity-0 sm:group-hover:pointer-events-auto sm:group-hover:translate-x-0 sm:group-hover:opacity-100 sm:group-has-[:focus-visible]:pointer-events-auto sm:group-has-[:focus-visible]:translate-x-0 sm:group-has-[:focus-visible]:opacity-100'
                            "
                        >
                            <Transition
                                enter-active-class="transition-all duration-200 ease-out"
                                enter-from-class="translate-x-2 opacity-0"
                                enter-to-class="translate-x-0 opacity-100"
                                leave-active-class="transition-all duration-200 ease-in"
                                leave-from-class="translate-x-0 opacity-100"
                                leave-to-class="translate-x-2 opacity-0"
                                mode="out-in"
                            >
                                <div v-if="confirmingId === attachment.id" key="confirm" class="relative flex shrink-0 items-center gap-1">
                                    <Button size="sm" variant="destructive" class="h-8 gap-1.5 rounded-lg shadow-sm" @click="remove(attachment.id)">
                                        Delete
                                    </Button>

                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        class="h-8 rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground"
                                        @click="confirmingId = null"
                                    >
                                        Cancel
                                    </Button>
                                </div>

                                <div v-else key="actions" class="flex items-center gap-1">
                                    <Button
                                        as-child
                                        variant="ghost"
                                        size="icon"
                                        class="h-8 w-8 rounded-lg text-muted-foreground transition-all duration-200 hover:bg-primary/10 hover:text-primary active:scale-95"
                                    >
                                        <a
                                            :href="`/attachments/${attachment.id}/file`"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            aria-label="Open file"
                                            title="Open file"
                                        >
                                            <ExternalLink class="h-4 w-4" />
                                        </a>
                                    </Button>

                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="h-8 w-8 rounded-lg text-muted-foreground transition-all duration-200 hover:bg-destructive/10 hover:text-destructive active:scale-95"
                                        aria-label="Delete file"
                                        title="Delete file"
                                        @click="confirmingId = attachment.id"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </Transition>
                        </div>
                    </li>
                </TransitionGroup>
                <p v-else class="text-center text-sm text-muted-foreground">No files attached yet.</p>

                <!-- Pick or drop a file (once chosen, it shows as a card with an X to remove it) -->
                <FileDropzone v-model="form.file" :error="form.errors.file" />

                <!-- Note, progress and upload button appear once a file is chosen -->
                <Transition name="slide">
                    <form v-if="form.file" class="space-y-3 rounded-xl border bg-muted/30 p-4" @submit.prevent="upload">
                        <div class="grid gap-2">
                            <Label for="attachment-note">Note (optional)</Label>
                            <Input id="attachment-note" v-model="form.note" placeholder="e.g. Signed contract, screenshot of the bug" />
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

                        <Button type="submit" class="w-full" :disabled="form.processing">
                            <Upload class="h-4 w-4" />
                            {{ form.processing ? 'Uploading...' : 'Upload file' }}
                        </Button>
                    </form>
                </Transition>
            </div>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
.file-list-enter-active,
.file-list-leave-active {
    transition: all 0.3s ease;
}

.file-list-enter-from {
    opacity: 0;
    transform: translateY(10px) scale(0.98);
}

.file-list-leave-to {
    opacity: 0;
    transform: translateX(30px);
}

/* A leaving item is taken out of the flow, so the others glide up to fill the gap. */
.file-list-leave-active {
    position: absolute;
    left: 0;
    right: 0;
}

.file-list-move {
    transition: transform 0.3s ease;
}

.slide-enter-active,
.slide-leave-active {
    transition: all 0.25s ease;
}

.slide-enter-from,
.slide-leave-to {
    opacity: 0;
    transform: translateY(-8px);
}

@media (prefers-reduced-motion: reduce) {
    .file-list-enter-active,
    .file-list-leave-active,
    .file-list-move,
    .slide-enter-active,
    .slide-leave-active {
        transition: none;
    }
}
</style>
