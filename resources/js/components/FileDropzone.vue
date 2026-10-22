<script setup lang="ts">
import { allowedExtensions, fileKind, fileKindClasses, fileKindIcons, fileLimitsHint, formatFileSize, validateFile } from '@/lib/files';
import { Upload, X } from 'lucide-vue-next';
import { ref } from 'vue';

defineProps<{
    modelValue: File | null;
    error?: string | null;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: File | null): void;
}>();

const inputRef = ref<HTMLInputElement | null>(null);
const dragging = ref(false);
const localError = ref<string | null>(null);

const accept = allowedExtensions.map((extension) => `.${extension}`).join(',');

function setFile(file: File | undefined) {
    localError.value = null;

    if (!file) return;

    const problem = validateFile(file);

    if (problem) {
        localError.value = problem;
        return;
    }

    emit('update:modelValue', file);
}

function onChange(event: Event) {
    const input = event.target as HTMLInputElement;
    setFile(input.files?.[0]);
    // Empty the input so choosing the same file again still triggers a change.
    input.value = '';
}

function onDrop(event: DragEvent) {
    dragging.value = false;
    setFile(event.dataTransfer?.files?.[0]);
}
</script>

<template>
    <div class="space-y-2">
        <div v-if="modelValue" class="flex items-center gap-3 rounded-lg border bg-card p-3">
            <div class="flex size-10 shrink-0 items-center justify-center rounded-lg" :class="fileKindClasses[fileKind(modelValue.name)]">
                <component :is="fileKindIcons[fileKind(modelValue.name)]" class="size-5" />
            </div>
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium">{{ modelValue.name }}</p>
                <p class="text-xs text-muted-foreground">{{ formatFileSize(modelValue.size) }}</p>
            </div>
            <button
                type="button"
                class="rounded-md p-1 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                aria-label="Remove file"
                @click="emit('update:modelValue', null)"
            >
                <X class="h-4 w-4" />
            </button>
        </div>

        <div
            v-else
            role="button"
            tabindex="0"
            class="group rounded-xl border-2 border-dashed p-6 text-center transition-all duration-200"
            :class="dragging ? 'scale-[1.02] border-primary bg-primary/10' : 'border-border hover:border-primary/60 hover:bg-primary/5'"
            @click="inputRef?.click()"
            @keydown.enter.prevent="inputRef?.click()"
            @keydown.space.prevent="inputRef?.click()"
            @dragover.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop.prevent="onDrop"
        >
            <div class="pointer-events-none flex flex-col items-center gap-2">
                <div
                    class="flex size-12 items-center justify-center rounded-full bg-primary/10 text-primary transition-transform duration-200 group-hover:-translate-y-1"
                    :class="{ '-translate-y-1 scale-110': dragging }"
                >
                    <Upload class="size-6" />
                </div>
                <p class="text-sm font-medium">{{ dragging ? 'Drop it here' : 'Drag a file here, or click to browse' }}</p>
                <p class="text-xs text-muted-foreground">{{ fileLimitsHint }}</p>
            </div>
        </div>

        <input ref="inputRef" type="file" class="sr-only" tabindex="-1" aria-hidden="true" :accept="accept" @change="onChange" />

        <Transition
            enter-active-class="transition-all duration-200"
            enter-from-class="-translate-y-1 opacity-0"
            leave-active-class="transition-all duration-200"
            leave-to-class="-translate-y-1 opacity-0"
        >
            <p v-if="localError || error" class="text-sm text-destructive">{{ localError ?? error }}</p>
        </Transition>
    </div>
</template>
