<script setup>
import {onBeforeUnmount, onMounted, ref} from 'vue';
import {BrowserMultiFormatReader} from '@zxing/browser';
import {BarcodeFormat, DecodeHintType} from '@zxing/library';
import {XMarkIcon} from '@heroicons/vue/24/outline/index.js';

const emit = defineEmits(['detected', 'close']);

const video = ref(null);
const error = ref(null);
let controls = null;
let done = false;

onMounted(async () => {
    const hints = new Map();
    hints.set(DecodeHintType.POSSIBLE_FORMATS, [
        BarcodeFormat.EAN_13,
        BarcodeFormat.EAN_8,
        BarcodeFormat.UPC_A,
        BarcodeFormat.UPC_E,
    ]);
    const reader = new BrowserMultiFormatReader(hints);

    try {
        controls = await reader.decodeFromConstraints(
            {video: {facingMode: 'environment'}},
            video.value,
            (result) => {
                if (result && !done) {
                    done = true;
                    controls?.stop();
                    emit('detected', result.getText());
                }
            },
        );
    } catch {
        error.value = 'Nu pot porni camera. Verifică permisiunile sau scrie codul de mână.';
    }
});

onBeforeUnmount(() => {
    done = true;
    controls?.stop();
});
</script>

<template>
    <Teleport to="body">
        <div class="fixed inset-0 z-50 flex flex-col bg-black">
            <div class="pt-safe flex items-center justify-between px-5 py-4">
                <p class="font-bold">Scanează codul de bare</p>
                <button type="button" aria-label="Închide" class="rounded-full bg-white/10 p-2" @click="emit('close')">
                    <XMarkIcon class="size-5"/>
                </button>
            </div>
            <div class="relative flex-1">
                <video ref="video" playsinline muted class="size-full object-cover"></video>
                <div class="pointer-events-none absolute inset-x-8 top-1/2 h-32 -translate-y-1/2 rounded-2xl border-2 border-lime/80"></div>
            </div>
            <p v-if="error" class="px-5 py-4 text-center text-sm text-rose">{{ error }}</p>
            <p v-else class="pb-safe px-5 py-4 text-center text-sm text-white/60">Îndreaptă camera spre codul de bare al produsului.</p>
        </div>
    </Teleport>
</template>
