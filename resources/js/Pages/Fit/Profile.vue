<script setup>
import {computed} from 'vue';
import {Head, router, useForm, usePage} from '@inertiajs/vue3';
import FitLayout from '@/Layouts/FitLayout.vue';
import {ArrowRightStartOnRectangleIcon} from '@heroicons/vue/24/outline/index.js';

const props = defineProps({
    goals: Object,
    canAdmin: Boolean,
});

const page = usePage();
const user = computed(() => page.props.auth.user);

const form = useForm({
    calories: props.goals.calories,
    steps: props.goals.steps,
    waterMl: props.goals.waterMl,
});

const fields = [
    {key: 'calories', label: 'Calorii pe zi', unit: 'kcal', color: 'focus:border-lime'},
    {key: 'steps', label: 'Pași pe zi', unit: 'pași', color: 'focus:border-sun'},
    {key: 'waterMl', label: 'Apă pe zi', unit: 'ml', color: 'focus:border-aqua'},
];

const save = () => form.put('/me/goals', {preserveScroll: true});
const logout = () => router.post('/logout');
</script>

<template>
    <Head title="Profil"/>
    <FitLayout title="Profil" back="/today">
        <section class="flex items-center gap-4 rounded-[2rem] border border-white/10 bg-gradient-to-b from-panel2 to-panel p-5">
            <div class="flex size-16 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-lime to-aqua text-2xl font-extrabold text-ink">
                {{ user.name.trim().charAt(0).toUpperCase() }}
            </div>
            <div class="min-w-0">
                <p class="truncate text-xl font-extrabold leading-tight">{{ user.name }}</p>
                <p class="truncate text-sm text-white/50">{{ user.email }}</p>
            </div>
        </section>

        <h2 class="mb-3 mt-7 text-lg font-extrabold tracking-tight">Obiective zilnice</h2>
        <form class="space-y-3" @submit.prevent="save">
            <label v-for="field in fields" :key="field.key" class="block rounded-[1.5rem] border border-white/5 bg-panel p-4">
                <span class="text-xs font-semibold uppercase tracking-wider text-white/50">{{ field.label }}</span>
                <span class="mt-2 flex items-center gap-3">
                    <input v-model.number="form[field.key]" type="number" inputmode="numeric"
                           class="h-12 min-w-0 flex-1 rounded-xl border border-white/10 bg-white/5 px-4 text-xl font-extrabold text-white focus:ring-0"
                           :class="field.color"/>
                    <span class="w-10 text-sm font-semibold text-white/45">{{ field.unit }}</span>
                </span>
                <span v-if="form.errors[field.key]" class="mt-1.5 block text-sm text-rose">{{ form.errors[field.key] }}</span>
            </label>

            <button type="submit" :disabled="form.processing || !form.isDirty"
                    class="h-14 w-full rounded-2xl bg-lime text-base font-extrabold text-ink active:scale-[0.98] disabled:opacity-40">
                {{ form.processing ? 'Se salvează…' : 'Salvează obiectivele' }}
            </button>
        </form>

        <a v-if="canAdmin" href="/dashboard"
           class="mt-6 flex h-12 items-center justify-center rounded-2xl bg-white/5 text-sm font-semibold text-white/70">
            Panou de administrare
        </a>

        <button type="button"
                class="mt-3 flex h-14 w-full items-center justify-center gap-2 rounded-2xl border border-rose/30 text-base font-bold text-rose active:scale-[0.98]"
                @click="logout">
            <ArrowRightStartOnRectangleIcon class="size-5"/> Ieși din cont
        </button>
    </FitLayout>
</template>
