<script setup>
import {computed, ref} from 'vue';
import {Head, router} from '@inertiajs/vue3';
import FitLayout from '@/Layouts/FitLayout.vue';
import {TrashIcon} from '@heroicons/vue/24/outline/index.js';

const props = defineProps({
    logs: Array,
    latest: {type: Number, default: null},
    change: {type: Number, default: null},
    goalType: String,
    today: String,
});

const weight = ref(props.latest ? String(props.latest) : '');
const date = ref(props.today);
const saving = ref(false);

function save() {
    const value = parseFloat(String(weight.value).replace(',', '.'));
    if (!(value > 0)) return;
    saving.value = true;
    router.put('/log/weight', {date: date.value, weight_kg: value}, {
        preserveScroll: true,
        onFinish: () => (saving.value = false),
    });
}

function remove(log) {
    if (!confirm(`Ștergi măsurătoarea din ${log.label}?`)) return;
    router.delete(`/weight/${log.id}`, {preserveScroll: true});
}

const W = 320;
const H = 150;
const PAD = 14;

const chart = computed(() => {
    const points = props.logs;
    if (points.length < 2) return null;

    const values = points.map((log) => log.weightKg);
    const min = Math.min(...values) - 0.5;
    const max = Math.max(...values) + 0.5;
    const t0 = new Date(points[0].date).getTime();
    const span = new Date(points[points.length - 1].date).getTime() - t0 || 1;

    const xy = points.map((log) => ({
        x: PAD + ((new Date(log.date).getTime() - t0) / span) * (W - PAD * 2),
        y: PAD + (1 - (log.weightKg - min) / (max - min)) * (H - PAD * 2),
        log,
    }));

    return {
        xy,
        line: xy.map((p, i) => `${i ? 'L' : 'M'}${p.x.toFixed(1)},${p.y.toFixed(1)}`).join(' '),
        min: (min + 0.5).toLocaleString('ro-RO'),
        max: (max - 0.5).toLocaleString('ro-RO'),
    };
});

// losing weight is good news when the goal is to lose, and the reverse when gaining
const changeColor = computed(() => {
    if (props.change === null || props.change === 0 || props.goalType === 'maintain') return 'text-white';
    const good = props.goalType === 'lose' ? props.change < 0 : props.change > 0;
    return good ? 'text-lime' : 'text-rose';
});

const list = computed(() => props.logs.slice().reverse());
</script>

<template>
    <Head title="Greutate"/>
    <FitLayout title="Greutate" subtitle="Ultimele 12 luni" back="/me">
        <section class="rounded-[2rem] border border-white/10 bg-gradient-to-b from-panel2 to-panel p-5">
            <div class="flex items-end justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-white/50">Ultima măsurătoare</p>
                    <p class="mt-1 text-4xl font-extrabold leading-none tracking-tight">
                        {{ latest !== null ? latest.toLocaleString('ro-RO') : '—' }}
                        <span class="text-base font-semibold text-white/55">kg</span>
                    </p>
                </div>
                <p v-if="change !== null" class="text-right">
                    <span class="block text-lg font-extrabold" :class="changeColor">{{ change > 0 ? '+' : '' }}{{ change.toLocaleString('ro-RO') }} kg</span>
                    <span class="block text-[11px] text-white/45">față de prima</span>
                </p>
            </div>

            <div class="mt-5">
                <svg v-if="chart" :viewBox="`0 0 ${W} ${H}`" class="h-40 w-full" role="img" aria-label="Evoluția greutății">
                    <path :d="chart.line" fill="none" stroke="#B8F34A" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle v-for="p in chart.xy" :key="p.log.date" :cx="p.x" :cy="p.y" r="3.5" fill="#B8F34A"/>
                </svg>
                <p v-else class="rounded-2xl border border-dashed border-white/15 p-5 text-center text-sm text-white/50">
                    Graficul apare după două măsurători.
                </p>
                <div v-if="chart" class="mt-1 flex justify-between text-[10px] font-semibold text-white/40">
                    <span>{{ logs[0].label }}</span>
                    <span>{{ chart.min }}–{{ chart.max }} kg</span>
                    <span>{{ logs[logs.length - 1].label }}</span>
                </div>
            </div>
        </section>

        <h2 class="mb-3 mt-6 text-lg font-extrabold tracking-tight">Adaugă măsurătoare</h2>
        <form class="flex gap-2" @submit.prevent="save">
            <input v-model="weight" type="text" inputmode="decimal" placeholder="kg"
                   class="h-14 min-w-0 flex-1 rounded-2xl border border-white/10 bg-white/5 px-4 text-xl font-extrabold text-white placeholder:text-base placeholder:font-normal placeholder:text-white/30 focus:border-lime focus:ring-0"/>
            <input v-model="date" type="date" :max="today"
                   class="h-14 w-40 rounded-2xl border border-white/10 bg-white/5 px-3 text-sm text-white focus:border-lime focus:ring-0"/>
            <button type="submit" :disabled="saving" class="h-14 rounded-2xl bg-lime px-5 font-extrabold text-ink active:scale-95 disabled:opacity-50">
                Salvează
            </button>
        </form>

        <ul v-if="list.length" class="mt-6 space-y-2">
            <li v-for="log in list" :key="log.id" class="flex items-center justify-between rounded-2xl border border-white/5 bg-panel px-4 py-3">
                <span class="font-semibold text-white/70">{{ log.label }}</span>
                <span class="flex items-center gap-3">
                    <span class="font-extrabold">{{ log.weightKg.toLocaleString('ro-RO') }} kg</span>
                    <button type="button" aria-label="Șterge" class="text-white/35 active:text-rose" @click="remove(log)">
                        <TrashIcon class="size-5"/>
                    </button>
                </span>
            </li>
        </ul>
    </FitLayout>
</template>
