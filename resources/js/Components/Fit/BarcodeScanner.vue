<script setup>
import {onBeforeUnmount, onMounted, ref} from 'vue';
import {XMarkIcon} from '@heroicons/vue/24/outline/index.js';

const emit = defineEmits(['detected', 'close']);

const video = ref(null);
const error = ref(null);
let stream = null;
let timer = null;

function stop() {
    clearInterval(timer);
    stream?.getTracks().forEach((track) => track.stop());
    stream = null;
}

onMounted(async () => {
    try {
        const detector = new window.BarcodeDetector({formats: ['ean_13', 'ean_8', 'upc_a', 'upc_e']});
        stream = await navigator.mediaDevices.getUserMedia({video: {facingMode: 'environment'}});
        video.value.srcObject = stream;
        await video.value.play();

        timer = setInterval(async () => {
            try {
                const [code] = await detector.detect(video.value);
                if (code) {
                    stop();
                    emit('detected', code.rawValue);
                }
            } catch {
                // the frame was not ready yet, try again on the next tick
            }
        }, 300);
    } catch {
        error.value = 'Nu pot porni camera. Verifică permisiunile sau scrie codul de mână.';
    }
});

onBeforeUnmount(stop);
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
