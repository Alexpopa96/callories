<script setup>
import {computed, onMounted, ref, watch} from 'vue';
import {router, usePage} from '@inertiajs/vue3';
import {appLocked, disableAppLock, unlockApp} from '@/Composables/useAppLock.js';
import {FaceSmileIcon, LockClosedIcon} from '@heroicons/vue/24/outline/index.js';

const page = usePage();
const visible = computed(() => appLocked.value && !!page.props.auth?.user);
const failed = ref(false);

const unlock = async () => {
    failed.value = !(await unlockApp());
};

// Safari may refuse the prompt without a tap; the button below covers that case
onMounted(() => visible.value && unlock());
watch(visible, (now) => now && unlock());

const logout = () => {
    disableAppLock();
    router.post('/logout');
};
</script>

<template>
    <div v-if="visible" class="fixed inset-0 z-[100] flex flex-col items-center justify-center gap-6 bg-ink px-8 text-center text-white">
        <span class="flex size-20 items-center justify-center rounded-full bg-lime/15 text-lime">
            <LockClosedIcon class="size-10"/>
        </span>
        <div>
            <p class="text-xl font-extrabold">Kalo Mind este blocat</p>
            <p class="mt-1 text-sm text-white/50">Deblochează cu Face ID ca să continui.</p>
        </div>
        <button type="button" class="flex h-14 w-full max-w-xs items-center justify-center gap-2 rounded-2xl bg-lime text-base font-extrabold text-ink active:scale-[0.98]"
                @click="unlock">
            <FaceSmileIcon class="size-5"/> Deblochează
        </button>
        <p v-if="failed" class="text-sm text-white/45">Nu a mers. Încearcă din nou.</p>
        <button type="button" class="text-sm font-semibold text-rose/80" @click="logout">Ieși din cont</button>
    </div>
</template>
