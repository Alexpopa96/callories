<script setup>
import {computed, onBeforeUnmount, ref, watch} from 'vue';
import {Link, usePage} from '@inertiajs/vue3';
import {
    CameraIcon,
    ChartBarIcon,
    ChevronLeftIcon,
    HomeIcon,
} from '@heroicons/vue/24/outline/index.js';

const props = defineProps({
    title: {type: String, required: true},
    subtitle: {type: String, default: ''},
    back: {type: String, default: null},
    scanDate: {type: String, default: null},
    hideNav: {type: Boolean, default: false},
});

const page = usePage();
const initial = computed(() => (page.props.auth?.user?.name ?? '?').trim().charAt(0).toUpperCase());
const path = computed(() => page.url.split('?')[0]);
const scanHref = computed(() => (props.scanDate ? `/scan?date=${props.scanDate}` : '/scan'));

const message = ref(null);
let timer = null;

watch(() => page.props.toast, (toast) => {
    const text = toast?.success || toast?.error;
    if (!text) return;
    message.value = {text, error: !toast.success};
    clearTimeout(timer);
    timer = setTimeout(() => (message.value = null), 2600);
}, {immediate: true, deep: true});

onBeforeUnmount(() => clearTimeout(timer));
</script>

<template>
    <div class="relative min-h-[100dvh] overflow-x-hidden bg-ink text-white">
        <div class="pointer-events-none absolute inset-x-0 top-0 h-72 bg-[radial-gradient(60%_60%_at_50%_0%,rgba(184,243,74,0.13),transparent)]"></div>

        <div class="relative mx-auto min-h-[100dvh] max-w-md" :class="hideNav ? 'pb-10' : 'pb-32'">
            <header class="pt-safe px-5">
                <div class="flex items-center justify-between gap-3 pb-4 pt-5">
                    <div class="flex min-w-0 items-center gap-3">
                        <Link v-if="back" :href="back" aria-label="Înapoi"
                              class="-ml-1 rounded-full bg-white/5 p-2 text-white/80">
                            <ChevronLeftIcon class="size-5"/>
                        </Link>
                        <div class="min-w-0">
                            <p v-if="subtitle" class="truncate text-xs font-medium text-white/50">{{ subtitle }}</p>
                            <h1 class="truncate text-2xl font-extrabold tracking-tight">{{ title }}</h1>
                        </div>
                    </div>
                    <Link href="/me" aria-label="Profil"
                          class="flex size-10 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-lime to-aqua text-sm font-extrabold text-ink">
                        {{ initial }}
                    </Link>
                </div>
            </header>

            <main class="px-5">
                <slot/>
            </main>
        </div>

        <Transition enter-active-class="transition duration-200" enter-from-class="translate-y-2 opacity-0"
                    leave-active-class="transition duration-200" leave-to-class="translate-y-2 opacity-0">
            <div v-if="message" class="pointer-events-none fixed inset-x-0 top-4 z-50 flex justify-center px-5 pt-safe">
                <div class="rounded-full px-4 py-2 text-sm font-semibold shadow-lg"
                     :class="message.error ? 'bg-rose text-white' : 'bg-lime text-ink'">
                    {{ message.text }}
                </div>
            </div>
        </Transition>

        <nav v-if="!hideNav" class="fixed inset-x-0 bottom-0 z-40 mx-auto max-w-md">
            <div class="pb-safe border-t border-white/10 bg-panel/90 backdrop-blur-xl">
                <div class="grid h-16 grid-cols-3 items-center px-4">
                    <Link href="/today" class="flex flex-col items-center gap-0.5 text-[11px] font-semibold"
                          :class="path.startsWith('/today') ? 'text-lime' : 'text-white/45'">
                        <HomeIcon class="size-6"/>
                        Azi
                    </Link>
                    <div class="flex justify-center">
                        <Link :href="scanHref" aria-label="Scanează masa"
                              class="-mt-9 flex size-16 items-center justify-center rounded-full bg-gradient-to-br from-lime to-aqua text-ink shadow-[0_10px_30px_-6px_rgba(184,243,74,0.6)] ring-4 ring-ink transition active:scale-95">
                            <CameraIcon class="size-7"/>
                        </Link>
                    </div>
                    <Link href="/history" class="flex flex-col items-center gap-0.5 text-[11px] font-semibold"
                          :class="path.startsWith('/history') ? 'text-lime' : 'text-white/45'">
                        <ChartBarIcon class="size-6"/>
                        Istoric
                    </Link>
                </div>
            </div>
        </nav>
    </div>
</template>
