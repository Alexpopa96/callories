<script setup>
import {BoltIcon} from '@heroicons/vue/24/solid/index.js';

defineProps({
    title: {type: String, required: true},
    subtitle: {type: String, default: ''},
});
</script>

<template>
    <div class="relative min-h-[100dvh] overflow-hidden bg-ink text-white">
        <div class="animate-float-slow pointer-events-none absolute -left-24 -top-28 size-80 rounded-full bg-lime/25 blur-3xl"></div>
        <div class="animate-float-slow pointer-events-none absolute -right-28 top-1/3 size-80 rounded-full bg-aqua/20 blur-3xl [animation-delay:-4s]"></div>
        <div class="pointer-events-none absolute inset-0 bg-[linear-gradient(to_bottom,transparent,rgba(10,14,26,0.85)_75%)]"></div>

        <div class="pt-safe pb-safe relative mx-auto flex min-h-[100dvh] max-w-md flex-col justify-center px-6 py-10">
            <div class="mb-8">
                <div class="flex items-center gap-3">
                    <div class="flex size-12 items-center justify-center rounded-2xl bg-gradient-to-br from-lime to-aqua text-ink shadow-[0_10px_30px_-8px_rgba(184,243,74,0.6)]">
                        <BoltIcon class="size-7"/>
                    </div>
                    <span class="text-lg font-extrabold tracking-tight">Calorii</span>
                </div>

                <h1 class="mt-10 text-4xl font-extrabold leading-tight tracking-tight">{{ title }}</h1>
                <p v-if="subtitle" class="mt-2 text-base text-white/60">{{ subtitle }}</p>

                <div class="mt-5 flex gap-2 text-xs font-semibold text-white/70">
                    <span class="flex items-center gap-1.5 rounded-full bg-white/5 px-3 py-1.5"><i class="size-2 rounded-full bg-lime"></i>Calorii</span>
                    <span class="flex items-center gap-1.5 rounded-full bg-white/5 px-3 py-1.5"><i class="size-2 rounded-full bg-sun"></i>Pași</span>
                    <span class="flex items-center gap-1.5 rounded-full bg-white/5 px-3 py-1.5"><i class="size-2 rounded-full bg-aqua"></i>Apă</span>
                </div>
            </div>

            <div class="rounded-[2rem] border border-white/10 bg-white/[0.04] p-5 shadow-2xl backdrop-blur-xl">
                <slot/>
            </div>

            <div class="mt-6 text-center text-sm text-white/60">
                <slot name="footer"/>
            </div>
        </div>
    </div>
</template>
