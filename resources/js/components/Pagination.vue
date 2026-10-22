<script setup lang="ts">
import type { PaginationLink } from '@/types';
import { Link } from '@inertiajs/vue3';

defineProps<{
    links: PaginationLink[];
}>();
</script>

<template>
    <div v-if="links.length > 3" class="flex flex-wrap gap-1">
        <template v-for="link in links" :key="link.label">
            <Link
                v-if="link.url"
                :href="link.url"
                preserve-scroll
                class="rounded-md border px-3 py-1 text-sm"
                :class="{ 'bg-primary text-primary-foreground': link.active }"
                v-html="link.label"
            />
            <span v-else class="rounded-md border px-3 py-1 text-sm text-muted-foreground opacity-50" v-html="link.label" />
        </template>
    </div>
</template>
