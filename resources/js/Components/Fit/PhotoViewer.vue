<script setup>
import {onBeforeUnmount, watch} from 'vue';
import {XMarkIcon} from '@heroicons/vue/24/outline/index.js';

const props = defineProps({
    open: {type: Boolean, default: false},
    src: {type: String, required: true},
    alt: {type: String, default: ''},
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
        <Transition enter-from-class="opacity-0" leave-to-class="opacity-0"
                    enter-active-class="transition duration-200" leave-active-class="transition duration-150">
            <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/95"
                 role="dialog" aria-modal="true" @click="emit('close')">
                <img :src="src" :alt="alt" class="max-h-full max-w-full object-contain"/>
                <button type="button" aria-label="Închide"
                        class="absolute right-4 top-[max(1rem,env(safe-area-inset-top))] flex size-11 items-center justify-center rounded-full bg-white/10 text-white backdrop-blur active:scale-90">
                    <XMarkIcon class="size-6"/>
                </button>
            </div>
        </Transition>
    </Teleport>
</template>
