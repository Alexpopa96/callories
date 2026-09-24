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
            <div class="mt-3 grid grid-cols-2 gap-2 border-t border-white/5 pt-3 text-center">
                <div>
                    <p class="text-sm font-extrabold">{{ Math.round(todayCalories) }} <span class="text-xs font-semibold text-white/45">/ {{ calorieBudget }} kcal</span></p>
                    <p class="mt-0.5 text-[11px] text-white/40">{{ exerciseCalories > 0 ? `calorii azi · +${exerciseCalories} arse` : 'calorii azi' }}</p>
                </div>
                <div>
                    <p class="text-sm font-extrabold">{{ challenge.pctWeight !== null ? `${challenge.pctWeight}%` : '—' }}</p>
                    <p class="mt-0.5 text-[11px] text-white/40">din obiectivul de greutate</p>
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
