<script setup>
import {onBeforeUnmount, watch} from 'vue';
import {XMarkIcon} from '@heroicons/vue/24/outline/index.js';

const props = defineProps({
    open: {type: Boolean, default: false},
    title: {type: String, default: ''},
});

const emit = defineEmits(['close']);

function onKey(event) {
    if (event.key === 'Escape') emit('close');
}

watch(() => props.open, (open) => {
    document.body.style.overflow = open ? 'hidden' : '';
    if (open) window.addEventListener('keydown', onKey);
    else window.removeEventListener('keydown', onKey);
});

onBeforeUnmount(() => {
    document.body.style.overflow = '';
    window.removeEventListener('keydown', onKey);
});
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50 flex items-end justify-center">
            <div class="absolute inset-0 animate-fade-in bg-black/60 backdrop-blur-sm" @click="emit('close')"></div>
            <section
                class="animate-sheet-up pb-safe relative w-full max-w-md rounded-t-[2rem] border border-white/10 bg-panel px-5 pt-3 shadow-2xl">
                <div class="mx-auto mb-3 h-1.5 w-10 rounded-full bg-white/20"></div>
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-bold">{{ title }}</h2>
                    <button type="button" class="rounded-full bg-white/5 p-2 text-white/60" aria-label="Închide"
                            @click="emit('close')">
                        <XMarkIcon class="size-5"/>
                    </button>
                </div>
                <div class="pb-6">
                    <slot/>
                </div>
            </section>
        </div>
    </Teleport>
</template>
