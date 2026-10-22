<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{
    title: string;
    subtitle?: string;
    icon: Component;
}>();
</script>

<template>
    <section class="flex flex-col overflow-hidden rounded-xl border bg-card">
        <header class="flex items-center gap-3 border-b px-5 py-4">
            <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                <component :is="icon" class="size-5" />
            </div>
            <div class="min-w-0">
                <h2 class="font-medium leading-none">{{ title }}</h2>
                <p v-if="subtitle" class="mt-1 truncate text-xs text-muted-foreground">{{ subtitle }}</p>
            </div>
            <div class="ml-auto"><slot name="action" /></div>
        </header>
        <div class="flex-1"><slot /></div>
    </section>
</template>
