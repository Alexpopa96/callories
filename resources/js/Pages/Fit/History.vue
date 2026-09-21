<script setup>
import {computed, ref} from 'vue';
import {Head, Link} from '@inertiajs/vue3';
import FitLayout from '@/Layouts/FitLayout.vue';

const props = defineProps({
    goals: Object,
    averages: Object,
    days: Array,
});

const metrics = {
    calories: {label: 'Calorii', unit: 'kcal', goal: () => props.goals.calories, text: 'text-lime', bar: 'bg-lime', soft: 'bg-lime/15'},
    steps: {label: 'Pași', unit: '', goal: () => props.goals.steps, text: 'text-sun', bar: 'bg-sun', soft: 'bg-sun/15'},
    waterMl: {label: 'Apă', unit: 'L', goal: () => props.goals.waterMl, text: 'text-aqua', bar: 'bg-aqua', soft: 'bg-aqua/15'},
};

const active = ref('calories');
const metric = computed(() => metrics[active.value]);

const week = computed(() => props.days.slice(0, 7).slice().reverse());
const scaleMax = computed(() => Math.max(metric.value.goal() * 1.25, ...week.value.map((day) => day[active.value])));

function format(key, value) {
    if (key === 'waterMl') return (value / 1000).toLocaleString('ro-RO', {maximumFractionDigits: 2});
    return Math.round(value).toLocaleString('ro-RO');
}

function short(key, value) {
    if (value === 0) return '';
    if (key === 'waterMl') return (value / 1000).toLocaleString('ro-RO', {maximumFractionDigits: 1});
    if (key === 'steps' && value >= 1000) return `${(value / 1000).toLocaleString('ro-RO', {maximumFractionDigits: 1})}k`;
    return Math.round(value).toString();
}

const barHeight = (value) => `${Math.max(value > 0 ? 4 : 0, (value / scaleMax.value) * 100)}%`;
const goalBottom = computed(() => `${(metric.value.goal() / scaleMax.value) * 100}%`);
const pct = (value, goal) => Math.min(100, Math.round((value / goal) * 100));
</script>

<template>
    <Head title="Istoric"/>
    <FitLayout title="Istoric" subtitle="Ultimele 30 de zile">
        <div class="grid grid-cols-3 gap-3">
            <div class="rounded-2xl border border-white/10 bg-panel p-3">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-white/45">Calorii</p>
                <p class="mt-1.5 text-xl font-extrabold leading-none text-lime">{{ format('calories', averages.calories) }}</p>
                <p class="mt-1 text-[11px] text-white/45">kcal / zi</p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-panel p-3">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-white/45">Pași</p>
                <p class="mt-1.5 text-xl font-extrabold leading-none text-sun">{{ format('steps', averages.steps) }}</p>
                <p class="mt-1 text-[11px] text-white/45">pași / zi</p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-panel p-3">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-white/45">Apă</p>
                <p class="mt-1.5 text-xl font-extrabold leading-none text-aqua">{{ format('waterMl', averages.waterMl) }}</p>
                <p class="mt-1 text-[11px] text-white/45">L / zi</p>
            </div>
        </div>
        <p class="mt-2 text-xs text-white/35">Media ultimelor 7 zile, doar zilele cu date.</p>

        <section class="mt-5 rounded-[2rem] border border-white/10 bg-gradient-to-b from-panel2 to-panel p-5">
            <div class="flex gap-2">
                <button v-for="(m, key) in metrics" :key="key" type="button"
                        class="rounded-full px-4 py-1.5 text-sm font-bold transition"
                        :class="active === key ? `${m.soft} ${m.text}` : 'text-white/45'" @click="active = key">
                    {{ m.label }}
                </button>
            </div>

            <div class="relative mt-6 h-44">
                <div class="absolute inset-x-0 border-t border-dashed border-white/25" :style="{bottom: goalBottom}">
                    <span class="absolute -top-4 right-0 text-[10px] font-semibold text-white/40">obiectiv</span>
                </div>
                <div class="absolute inset-0 flex items-end justify-between gap-2">
                    <div v-for="day in week" :key="day.date" class="flex h-full flex-1 flex-col items-center justify-end">
                        <span class="mb-1 text-[10px] font-bold text-white/60">{{ short(active, day[active]) }}</span>
                        <div class="w-full max-w-9 rounded-t-lg transition-all duration-500"
                             :class="[metric.bar, day[active] === 0 ? 'opacity-0' : '']"
                             :style="{height: barHeight(day[active])}"></div>
                    </div>
                </div>
            </div>
            <div class="mt-2 flex justify-between gap-2">
                <span v-for="day in week" :key="day.date"
                      class="flex-1 text-center text-[11px] font-semibold uppercase text-white/45">{{ day.weekday }}</span>
            </div>
        </section>

        <h2 class="mb-3 mt-7 text-lg font-extrabold tracking-tight">Zile</h2>
        <ul class="space-y-2.5">
            <li v-for="day in days" :key="day.date">
                <Link :href="`/today?date=${day.date}`"
                      class="block rounded-[1.5rem] border border-white/5 bg-panel p-4 transition active:scale-[0.99]">
                    <div class="flex items-baseline justify-between">
                        <p class="font-bold">{{ day.label }} <span class="text-sm font-medium uppercase text-white/40">{{ day.weekday }}</span></p>
                        <p class="text-xs text-white/40">{{ day.meals }} {{ day.meals === 1 ? 'masă' : 'mese' }}</p>
                    </div>
                    <div class="mt-3 grid grid-cols-3 gap-3">
                        <div v-for="(m, key) in metrics" :key="key">
                            <div class="h-1.5 overflow-hidden rounded-full bg-white/10">
                                <div class="h-full rounded-full" :class="m.bar"
                                     :style="{width: `${pct(day[key], m.goal())}%`}"></div>
                            </div>
                            <p class="mt-1.5 text-sm font-extrabold leading-none" :class="m.text">
                                {{ format(key, day[key]) }}<span class="text-[10px] font-semibold text-white/40"> {{ m.unit }}</span>
                            </p>
                        </div>
                    </div>
                </Link>
            </li>
        </ul>
    </FitLayout>
</template>
