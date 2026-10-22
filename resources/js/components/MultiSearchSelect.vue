<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import type { PickerOption } from '@/types';
import { Check, ChevronDown, Loader2, Search } from 'lucide-vue-next';
import { onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps<{
    label: string;
    endpoint: string;
    modelValue: PickerOption[];
    placeholder?: string;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: PickerOption[]): void;
}>();

const open = ref(false);
const query = ref('');
const options = ref<PickerOption[]>([]);
const loading = ref(false);

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

// Each time the panel opens, start with an empty search box and the first matches.
watch(open, (isOpen) => {
    if (!isOpen) return;
    query.value = '';
    search();
});

watch(query, () => {
    clearTimeout(timer);
    timer = setTimeout(search, 250);
});

onBeforeUnmount(() => clearTimeout(timer));

const isSelected = (option: PickerOption) => props.modelValue.some((item) => item.id === option.id);

function toggle(option: PickerOption) {
    emit('update:modelValue', isSelected(option) ? props.modelValue.filter((item) => item.id !== option.id) : [...props.modelValue, option]);
}
</script>

<template>
    <Popover v-model:open="open">
        <PopoverTrigger as-child>
            <Button variant="outline" class="justify-between gap-2" :class="{ 'border-primary/60': modelValue.length }">
                {{ label }}
                <span v-if="modelValue.length" class="rounded-full bg-primary px-1.5 text-xs font-medium text-primary-foreground">
                    {{ modelValue.length }}
                </span>
                <ChevronDown class="h-4 w-4 opacity-60" />
            </Button>
        </PopoverTrigger>

        <PopoverContent align="start" class="w-80 p-2">
            <div class="relative">
                <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input v-model="query" class="pl-9" :placeholder="placeholder ?? 'Search...'" />
                <Loader2 v-if="loading" class="absolute right-3 top-1/2 size-4 -translate-y-1/2 animate-spin text-muted-foreground" />
            </div>

            <ul class="mt-2 max-h-64 overflow-y-auto">
                <li v-for="option in options" :key="option.id">
                    <button
                        type="button"
                        class="flex w-full items-center gap-3 rounded-md px-2 py-2 text-left transition-colors hover:bg-primary/10"
                        @click="toggle(option)"
                    >
                        <span
                            class="flex size-4 shrink-0 items-center justify-center rounded border transition-colors"
                            :class="isSelected(option) ? 'border-primary bg-primary text-primary-foreground' : 'border-border'"
                        >
                            <Check v-if="isSelected(option)" class="size-3" />
                        </span>
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-medium">{{ option.title }}</span>
                            <span v-if="option.subtitle" class="block truncate text-xs text-muted-foreground">{{ option.subtitle }}</span>
                        </span>
                    </button>
                </li>
                <li v-if="!options.length && !loading" class="px-2 py-4 text-center text-sm text-muted-foreground">No matches.</li>
            </ul>

            <div class="mt-2 flex items-center justify-between border-t pt-2 text-xs text-muted-foreground">
                <span>{{ modelValue.length }} selected · top 10 matches shown</span>
                <button
                    v-if="modelValue.length"
                    type="button"
                    class="font-medium text-foreground hover:underline"
                    @click="emit('update:modelValue', [])"
                >
                    Clear
                </button>
            </div>
        </PopoverContent>
    </Popover>
</template>
