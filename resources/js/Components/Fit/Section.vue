<script setup>
import {ChevronDownIcon} from '@heroicons/vue/24/outline/index.js';

defineProps({
    title: {type: String, required: true},
    open: {type: Boolean, default: false},
    color: {type: String, default: 'lime'},
});

const tints = {
    lime: 'bg-lime/15 text-lime',
    aqua: 'bg-aqua/15 text-aqua',
    rose: 'bg-rose/15 text-rose',
    sun: 'bg-sun/15 text-sun',
};
</script>

<template>
    <details :open="open" class="group mt-4 rounded-[1.5rem] border border-white/10 bg-panel">
        <summary class="flex h-14 cursor-pointer list-none items-center gap-3 px-4 text-base font-extrabold [&::-webkit-details-marker]:hidden">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-full" :class="tints[color]">
                <slot name="icon"/>
            </span>
            <span class="min-w-0 flex-1 truncate">{{ title }}</span>
            <ChevronDownIcon class="size-5 shrink-0 text-white/40 transition group-open:rotate-180"/>
        </summary>
        <div class="px-4 pb-4">
            <slot/>
        </div>
    </details>
</template>
