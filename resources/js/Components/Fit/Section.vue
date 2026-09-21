<script setup>
import {ChevronDownIcon} from '@heroicons/vue/24/outline/index.js';

defineProps({
    title: {type: String, required: true},
    open: {type: Boolean, default: false},
});
</script>

<template>
    <details :open="open" class="group mt-4 rounded-[1.5rem] border border-white/10 bg-panel">
        <summary class="flex h-14 cursor-pointer list-none items-center justify-between px-4 text-base font-extrabold [&::-webkit-details-marker]:hidden">
            {{ title }}
            <ChevronDownIcon class="size-5 text-white/40 transition group-open:rotate-180"/>
        </summary>
        <div class="px-4 pb-4">
            <slot/>
        </div>
    </details>
</template>
