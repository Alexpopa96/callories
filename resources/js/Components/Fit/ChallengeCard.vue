<script setup>
import {computed} from 'vue';
import {Link} from '@inertiajs/vue3';
import ProgressRing from '@/Components/Fit/ProgressRing.vue';
import {ChevronRightIcon, TrophyIcon} from '@heroicons/vue/24/outline/index.js';

const props = defineProps({
    challenge: {type: Object, default: null},
    todayCalories: {type: Number, default: 0},
    exerciseCalories: {type: Number, default: 0},
});

const goalLabels = {
    lose_weight: 'Slăbire',
    gain_weight: 'Creștere în greutate',
    gain_muscle: 'Masă musculară',
};

const calorieBudget = computed(() => (props.challenge?.calorieGoal ?? 0) + props.exerciseCalories);

// the bar spans the whole budget, or what was eaten if that went past it
const calorieBar = computed(() => {
    const goal = props.challenge?.calorieGoal ?? 0;
    const eaten = Math.round(props.todayCalories);
    const scale = Math.max(calorieBudget.value, eaten, 1);
    const pct = (value) => (value / scale) * 100;

    return {
        eaten,
        remaining: calorieBudget.value - eaten,
        goalPct: pct(goal),
        budgetPct: pct(calorieBudget.value),
        eatenPct: pct(Math.min(eaten, calorieBudget.value)),
        overPct: pct(Math.max(0, eaten - calorieBudget.value)),
    };
});

const fmt = (value) => Math.round(value).toLocaleString('ro-RO');

const goalLabel = computed(() => goalLabels[props.challenge?.goal] ?? '');

const weightLabel = computed(() => {
    if (!props.challenge) return '';
    const {currentWeightKg, targetWeightKg} = props.challenge;
    return `${currentWeightKg.toLocaleString('ro-RO')} → ${targetWeightKg.toLocaleString('ro-RO')} kg`;
});
</script>

<template>
    <Link href="/challenge"
          class="mt-4 block rounded-[1.75rem] border border-white/10 bg-panel p-4 transition active:scale-[0.99]">
        <template v-if="challenge">
            <div class="flex items-center gap-4">
                <ProgressRing :value="challenge.daysElapsed" :max="challenge.totalDays" :size="64" :stroke="7" color="#FF9F43">
                    <span class="text-sm font-extrabold leading-none">{{ challenge.pctDays }}%</span>
                </ProgressRing>
                <div class="min-w-0 flex-1">
                    <p class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-white/50">
                        <TrophyIcon class="size-4 text-sun"/> Provocare · {{ goalLabel }}
                    </p>
                    <p class="mt-1 text-lg font-extrabold leading-tight">Ziua {{ challenge.daysElapsed }} din {{ challenge.totalDays }}</p>
                    <p class="mt-0.5 truncate text-xs text-white/50">{{ weightLabel }}</p>
                </div>
                <ChevronRightIcon class="size-5 shrink-0 text-white/35"/>
            </div>
            <div class="mt-3 border-t border-white/5 pt-3">
                <div class="flex items-baseline justify-between">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-white/45">Calorii azi</p>
                    <p class="text-sm font-extrabold">
                        {{ fmt(calorieBar.eaten) }} <span class="text-xs font-semibold text-white/45">/ {{ fmt(calorieBudget) }} kcal</span>
                    </p>
                </div>
                <div class="relative mt-2 h-3 overflow-hidden rounded-full bg-white/10">
                    <div v-if="exerciseCalories > 0" class="absolute inset-y-0 bg-sun/25"
                         :style="{left: `${calorieBar.goalPct}%`, width: `${calorieBar.budgetPct - calorieBar.goalPct}%`}"></div>
                    <div class="absolute inset-y-0 left-0 rounded-full bg-lime transition-all duration-700"
                         :style="{width: `${calorieBar.eatenPct}%`}"></div>
                    <div v-if="calorieBar.overPct > 0" class="absolute inset-y-0 bg-rose transition-all duration-700"
                         :style="{left: `${calorieBar.budgetPct}%`, width: `${calorieBar.overPct}%`}"></div>
                    <div v-if="exerciseCalories > 0" class="absolute inset-y-0 w-0.5 bg-ink/70"
                         :style="{left: `${calorieBar.goalPct}%`}"></div>
                </div>
                <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-white/55">
                    <span class="flex items-center gap-1"><span class="size-2 rounded-full bg-lime"></span>mâncat {{ fmt(calorieBar.eaten) }}</span>
                    <span v-if="exerciseCalories > 0" class="flex items-center gap-1"><span class="size-2 rounded-full bg-sun"></span>ars +{{ fmt(exerciseCalories) }}</span>
                    <span class="ml-auto font-bold" :class="calorieBar.remaining >= 0 ? 'text-white/70' : 'text-rose'">
                        {{ calorieBar.remaining >= 0 ? `${fmt(calorieBar.remaining)} rămase` : `+${fmt(-calorieBar.remaining)} peste` }}
                    </span>
                </div>
            </div>
            <div class="mt-3 border-t border-white/5 pt-3">
                <div class="flex items-baseline justify-between">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-white/45">Obiectiv greutate</p>
                    <p class="text-sm font-extrabold">{{ challenge.pctWeight !== null ? `${challenge.pctWeight}%` : '—' }}</p>
                </div>
                <div class="mt-2 h-3 overflow-hidden rounded-full bg-white/10">
                    <div class="h-full rounded-full bg-rose transition-all duration-700" :style="{width: `${challenge.pctWeight ?? 0}%`}"></div>
                </div>
                <div class="mt-1.5 flex justify-between text-[11px] text-white/45">
                    <span>{{ challenge.startWeightKg.toLocaleString('ro-RO') }} kg</span>
                    <span class="font-bold text-white/75">acum {{ challenge.currentWeightKg.toLocaleString('ro-RO') }} kg</span>
                    <span>{{ challenge.targetWeightKg.toLocaleString('ro-RO') }} kg</span>
                </div>
            </div>
        </template>
        <template v-else>
            <div class="flex items-center gap-3">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-sun/15 text-sun">
                    <TrophyIcon class="size-5"/>
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block font-bold">Începe o provocare</span>
                    <span class="block text-xs text-white/50">Plan personalizat de calorii, apă și macro ca să ajungi la greutatea dorită.</span>
                </span>
                <ChevronRightIcon class="size-5 shrink-0 text-white/35"/>
            </div>
        </template>
    </Link>
</template>
