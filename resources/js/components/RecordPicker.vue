<script setup lang="ts">
import { Input } from '@/components/ui/input';
import type { PickerOption } from '@/types';
import { Loader2, Search, X } from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    modelValue: number | null;
    endpoint: string;
    placeholder?: string;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: number | null): void;
}>();

const query = ref('');
const options = ref<PickerOption[]>([]);
const loading = ref(false);
const chosen = ref<PickerOption | null>(null);

let timer: ReturnType<typeof setTimeout>;
let latestRequest = 0;

async function search() {
    const requestId = ++latestRequest;
    loading.value = true;

    try {
        const response = await fetch(`${props.endpoint}?q=${encodeURIComponent(query.value.trim())}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });

        if (!response.ok) throw new Error('Search failed');

        const data: PickerOption[] = await response.json();

        // Ignore the answer if a newer search was started while this one was running.
        if (requestId === latestRequest) options.value = data;
    } catch {
        if (requestId === latestRequest) options.value = [];
    } finally {
        if (requestId === latestRequest) loading.value = false;
    }
}

watch(query, () => {
    clearTimeout(timer);
    timer = setTimeout(search, 250);
});

onMounted(search);
onBeforeUnmount(() => clearTimeout(timer));

function choose(option: PickerOption) {
    chosen.value = option;
    emit('update:modelValue', option.id);
}

function clear() {
    chosen.value = null;
    emit('update:modelValue', null);
    query.value = '';
    search();
}
</script>

<template>
    <div v-if="chosen" class="flex items-center gap-3 rounded-lg border bg-muted/30 p-3">
        <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-medium">{{ chosen.title }}</p>
            <p v-if="chosen.subtitle" class="truncate text-xs text-muted-foreground">{{ chosen.subtitle }}</p>
        </div>
        <button
            type="button"
            class="rounded-md p-1 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
            aria-label="Choose a different one"
            @click="clear"
        >
            <X class="h-4 w-4" />
        </button>
    </div>

    <div v-else class="space-y-2">
        <div class="relative">
            <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input v-model="query" class="pl-9" :placeholder="placeholder" @keydown.enter.prevent />
            <Loader2 v-if="loading" class="absolute right-3 top-1/2 size-4 -translate-y-1/2 animate-spin text-muted-foreground" />
        </div>

        <ul class="max-h-48 divide-y overflow-y-auto rounded-md border">
            <li v-for="option in options" :key="option.id">
                <button
                    type="button"
                    class="flex w-full flex-col items-start px-3 py-2 text-left transition-colors hover:bg-primary/10"
                    @click="choose(option)"
                >
                    <span class="text-sm font-medium">{{ option.title }}</span>
                    <span v-if="option.subtitle" class="text-xs text-muted-foreground">{{ option.subtitle }}</span>
                </button>
            </li>
            <li v-if="!options.length && !loading" class="px-3 py-4 text-center text-sm text-muted-foreground">No matches.</li>
        </ul>
    </div>
</template>
