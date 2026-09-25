<script setup>
import {computed, ref} from 'vue';
import {Head, useForm, router} from '@inertiajs/vue3';
import FitLayout from '@/Layouts/FitLayout.vue';
import ProgressRing from '@/Components/Fit/ProgressRing.vue';
import BottomSheet from '@/Components/Fit/BottomSheet.vue';
import {
    BeakerIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    FireIcon,
    ScaleIcon,
    TrophyIcon,
} from '@heroicons/vue/24/outline/index.js';

const props = defineProps({
    challenge: {type: Object, default: null},
    latestWeightKg: {type: Number, default: null},
});

const goalOptions = [
    {value: 'lose_weight', label: 'Slăbesc'},
    {value: 'gain_weight', label: 'Cresc în greutate'},
    {value: 'gain_muscle', label: 'Masă musculară'},
];

const goalLabels = Object.fromEntries(goalOptions.map((option) => [option.value, option.label]));

const inputClass = 'mt-1 h-14 w-full rounded-2xl border border-white/10 bg-white/5 px-4 text-xl font-extrabold text-white placeholder:text-base placeholder:font-normal placeholder:text-white/30 focus:border-lime focus:ring-0';

// setup form
const form = useForm({
    weightKg: props.latestWeightKg ? String(props.latestWeightKg) : '',
    targetWeightKg: '',
    days: 30,
    goal: 'lose_weight',
});

function save() {
    form.transform((data) => ({
        ...data,
        weightKg: String(data.weightKg).replace(',', '.'),
        targetWeightKg: String(data.targetWeightKg).replace(',', '.'),
    })).post('/challenge', {preserveScroll: true});
}

// active dashboard
const abandonSheet = ref(false);
const abandoning = ref(false);

function abandon() {
    abandoning.value = true;
    router.delete(`/challenge/${props.challenge.id}`, {
        preserveScroll: true,
        onFinish: () => {
            abandoning.value = false;
            abandonSheet.value = false;
        },
    });
}

// edit days
const editDaysSheet = ref(false);
const daysForm = useForm({days: 30});

function openEditDays() {
    daysForm.clearErrors();
    daysForm.days = props.challenge.progress.totalDays;
    editDaysSheet.value = true;
}

function saveDays() {
    daysForm.put(`/challenge/${props.challenge.id}`, {
        preserveScroll: true,
        onSuccess: () => (editDaysSheet.value = false),
    });
}

// day picker, limited server-side to the days of the challenge
function goToDay(date) {
    if (!date) return;
    router.get('/challenge', {date}, {preserveScroll: true, preserveState: true});
}

const liters = (ml) => (ml / 1000).toLocaleString('ro-RO', {maximumFractionDigits: 1});

const macros = computed(() => {
    if (!props.challenge) return [];
    const {day, targets, progress} = props.challenge;

    return [
        {label: 'Proteine', value: day.proteinG, goal: targets.proteinG, pct: day.pctProtein, avg: progress.avgProteinG, text: 'text-aqua', bar: 'bg-aqua'},
        {label: 'Carbohidrați', value: day.carbsG, goal: targets.carbsG, pct: day.pctCarbs, avg: progress.avgCarbsG, text: 'text-sun', bar: 'bg-sun'},
        {label: 'Grăsimi', value: day.fatG, goal: targets.fatG, pct: day.pctFat, avg: progress.avgFatG, text: 'text-rose', bar: 'bg-rose'},
    ];
});

const goalLabel = computed(() => goalLabels[props.challenge?.goal] ?? '');

const W = 320;
const H = 120;
const PAD = 14;

const chart = computed(() => {
    if (!props.challenge) return null;
    const {startWeightKg, targetWeightKg, currentWeightKg} = props.challenge.progress;

    const values = [startWeightKg, currentWeightKg, targetWeightKg];
    const min = Math.min(...values) - 0.5;
    const max = Math.max(...values) + 0.5;
    const span = max - min || 1;
    const x = (pct) => PAD + pct * (W - PAD * 2);
    const y = (value) => PAD + (1 - (value - min) / span) * (H - PAD * 2);

    const {pctDays} = props.challenge.progress;

    return {
        startPoint: {x: x(0), y: y(startWeightKg)},
        currentPoint: {x: x(Math.min(1, pctDays / 100)), y: y(currentWeightKg)},
        targetLineY: y(targetWeightKg),
    };
});
</script>

<template>
    <Head title="Provocare"/>
    <FitLayout title="Provocare" back="/today">
        <template v-if="!challenge">
            <section class="rounded-[2rem] border border-white/10 bg-gradient-to-b from-panel2 to-panel p-5">
                <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-sun">
                    <TrophyIcon class="size-4"/> Provocare nouă
                </p>
                <p class="mt-2 text-sm text-white/60">
                    Spune-ne greutatea curentă, cea dorită și în câte zile vrei să ajungi acolo. Îți calculăm un plan zilnic de calorii, apă și macro-uri.
                </p>
            </section>

            <form class="mt-5 space-y-4" @submit.prevent="save">
                <div>
                    <span class="text-xs font-semibold uppercase tracking-wider text-white/50">Obiectiv</span>
                    <div class="mt-1 flex gap-1.5 rounded-2xl bg-white/5 p-1">
                        <button v-for="option in goalOptions" :key="option.value" type="button"
                                class="h-11 flex-1 rounded-xl text-xs font-bold transition"
                                :class="form.goal === option.value ? 'bg-lime text-ink' : 'text-white/55'"
                                @click="form.goal = option.value">
                            {{ option.label }}
                        </button>
                    </div>
                    <p v-if="form.errors.goal" class="mt-1.5 text-sm text-rose">{{ form.errors.goal }}</p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <label class="block">
                        <span class="text-xs font-semibold uppercase tracking-wider text-white/50">Greutate curentă</span>
                        <input v-model="form.weightKg" type="text" inputmode="decimal" placeholder="kg" :class="inputClass"/>
                        <p v-if="form.errors.weightKg" class="mt-1.5 text-sm text-rose">{{ form.errors.weightKg }}</p>
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold uppercase tracking-wider text-white/50">Greutate țintă</span>
                        <input v-model="form.targetWeightKg" type="text" inputmode="decimal" placeholder="kg" :class="inputClass"/>
                        <p v-if="form.errors.targetWeightKg" class="mt-1.5 text-sm text-rose">{{ form.errors.targetWeightKg }}</p>
                    </label>
                </div>

                <label class="block">
                    <span class="text-xs font-semibold uppercase tracking-wider text-white/50">În câte zile</span>
                    <input v-model.number="form.days" type="number" inputmode="numeric" :class="inputClass"/>
                    <div class="mt-2 flex gap-2">
                        <button v-for="preset in [30, 60, 90]" :key="preset" type="button"
                                class="h-9 rounded-xl bg-white/5 px-4 text-xs font-bold text-white/60 active:scale-95"
                                @click="form.days = preset">
                            {{ preset }} zile
                        </button>
                    </div>
                    <p v-if="form.errors.days" class="mt-1.5 text-sm text-rose">{{ form.errors.days }}</p>
                </label>

                <button type="submit" :disabled="form.processing"
                        class="h-14 w-full rounded-2xl bg-lime text-base font-extrabold text-ink active:scale-[0.98] disabled:opacity-40">
                    {{ form.processing ? 'Se calculează…' : 'Generează planul' }}
                </button>
            </form>
        </template>

        <template v-else>
            <section class="rounded-[2rem] border border-white/10 bg-gradient-to-b from-panel2 to-panel p-5">
                <div class="flex items-center gap-5">
                    <ProgressRing :value="challenge.progress.daysElapsed" :max="challenge.progress.totalDays" :size="120" :stroke="12" color="#FF9F43">
                        <span class="text-2xl font-extrabold leading-none">{{ challenge.progress.pctDays }}%</span>
                        <span class="mt-1 text-[11px] font-medium text-white/50">din provocare</span>
                    </ProgressRing>
                    <div class="min-w-0 flex-1">
                        <p class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-sun">
                            <TrophyIcon class="size-4"/> {{ goalLabel }}
                        </p>
                        <p class="mt-1.5 text-2xl font-extrabold leading-tight">Ziua {{ challenge.progress.daysElapsed }} din {{ challenge.progress.totalDays }}</p>
                        <p class="mt-1 text-xs text-white/45">{{ challenge.progress.daysLeft }} zile rămase · adere {{ challenge.progress.adherencePct }}%</p>
                        <button type="button" class="mt-1.5 text-[11px] font-semibold text-lime underline underline-offset-2"
                                @click="openEditDays">
                            Editează zilele
                        </button>
                    </div>
                </div>
            </section>

            <section v-if="challenge.adjusted" class="mt-4 rounded-[1.5rem] border border-sun/25 bg-sun/[0.06] p-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-sun">Plan ajustat</p>
                <p class="mt-1.5 text-sm text-white/75">{{ challenge.explanation }}</p>
            </section>
            <section v-else class="mt-4 rounded-[1.5rem] border border-lime/25 bg-lime/[0.06] p-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-lime">Plan sigur</p>
                <p class="mt-1.5 text-sm text-white/75">{{ challenge.explanation }}</p>
            </section>

            <section class="mt-4 rounded-[1.75rem] border border-white/10 bg-panel p-5">
                <p class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-white/50">
                    <ScaleIcon class="size-4 text-rose"/> Greutate
                </p>
                <div class="mt-2 flex items-baseline justify-between">
                    <p class="text-3xl font-extrabold leading-none">{{ challenge.progress.currentWeightKg.toLocaleString('ro-RO') }}
                        <span class="text-base font-semibold text-white/55">kg</span>
                    </p>
                    <div class="text-right">
                        <p class="text-sm font-semibold text-white/50">țintă {{ challenge.progress.targetWeightKg.toLocaleString('ro-RO') }} kg</p>
                        <p class="mt-0.5 text-xs font-bold text-rose">
                            {{ challenge.progress.pctWeight !== null ? `${challenge.progress.pctWeight}% realizat` : '—' }}
                        </p>
                    </div>
                </div>
                <div v-if="challenge.progress.pctWeight !== null" class="mt-2 h-1.5 overflow-hidden rounded-full bg-white/10">
                    <div class="h-full rounded-full bg-rose transition-all duration-700" :style="{width: `${challenge.progress.pctWeight}%`}"></div>
                </div>

                <svg v-if="chart" :viewBox="`0 0 ${W} ${H}`" class="mt-4 h-28 w-full" role="img" aria-label="Progres greutate">
                    <line :x1="PAD" :x2="W - PAD" :y1="chart.targetLineY" :y2="chart.targetLineY" stroke="rgba(184,243,74,0.4)" stroke-width="1.5" stroke-dasharray="4 4"/>
                    <line :x1="chart.startPoint.x" :y1="chart.startPoint.y" :x2="chart.currentPoint.x" :y2="chart.currentPoint.y" stroke="#FF5D8F" stroke-width="2.5" stroke-linecap="round"/>
                    <circle :cx="chart.startPoint.x" :cy="chart.startPoint.y" r="3.5" fill="rgba(255,255,255,0.4)"/>
                    <circle :cx="chart.currentPoint.x" :cy="chart.currentPoint.y" r="4.5" fill="#FF5D8F"/>
                </svg>
                <p class="mt-1 text-[11px] text-white/40">
                    {{ challenge.progress.lastWeighInDate ? `Ultima cântărire: ${challenge.progress.lastWeighInDate}` : 'Nicio cântărire notată încă' }} — <a href="/weight" class="underline">notează greutatea</a>
                </p>
            </section>

            <section class="mt-4 rounded-[1.75rem] border border-white/10 bg-panel p-5">
                <div class="-mx-2 -mt-2 flex items-center justify-between">
                    <button type="button" aria-label="Ziua anterioară" :disabled="!challenge.day.prev"
                            class="flex size-11 items-center justify-center rounded-full text-white/60 active:scale-90 disabled:opacity-25"
                            @click="goToDay(challenge.day.prev)">
                        <ChevronLeftIcon class="size-5"/>
                    </button>
                    <div class="text-center">
                        <p class="text-sm font-extrabold">{{ challenge.day.isToday ? 'Azi' : challenge.day.label }}</p>
                        <p class="text-[11px] font-semibold text-white/45">Ziua {{ challenge.day.dayNumber }} din {{ challenge.progress.totalDays }}</p>
                    </div>
                    <button type="button" aria-label="Ziua următoare" :disabled="!challenge.day.next"
                            class="flex size-11 items-center justify-center rounded-full text-white/60 active:scale-90 disabled:opacity-25"
                            @click="goToDay(challenge.day.next)">
                        <ChevronRightIcon class="size-5"/>
                    </button>
                </div>

                <p class="mt-3 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-white/50">
                    <FireIcon class="size-4 text-lime"/> Ținte zilnice
                </p>
                <div class="mt-3 grid grid-cols-2 gap-3">
                    <div class="flex flex-col">
                        <p class="text-2xl font-extrabold leading-none">{{ challenge.day.calories }}
                            <span class="text-sm font-semibold text-white/45">/ {{ challenge.day.calorieBudget }}</span>
                        </p>
                        <p class="mt-1 text-[11px] text-white/45">kcal<template v-if="challenge.day.exerciseCalories > 0"> (+{{ challenge.day.exerciseCalories }} din sport)</template> · medie {{ challenge.progress.avgCalories }}</p>
                        <div class="mt-auto pt-1">
                            <p class="text-xs font-bold text-lime">{{ challenge.day.pctCalories }}% realizat</p>
                            <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-white/10">
                                <div class="h-full rounded-full bg-lime transition-all duration-700" :style="{width: `${challenge.day.pctCalories}%`}"></div>
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-col">
                        <p class="flex items-center gap-1 text-2xl font-extrabold leading-none">
                            <BeakerIcon class="size-4 text-aqua"/> {{ liters(challenge.day.waterMl) }}
                            <span class="text-sm font-semibold text-white/45">/ {{ liters(challenge.targets.waterMl) }} L</span>
                        </p>
                        <p class="mt-1 text-[11px] text-white/45">apă · medie {{ liters(challenge.progress.avgWaterMl) }} L</p>
                        <div class="mt-auto pt-1">
                            <p class="text-xs font-bold text-aqua">{{ challenge.day.pctWater }}% realizat</p>
                            <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-white/10">
                                <div class="h-full rounded-full bg-aqua transition-all duration-700" :style="{width: `${challenge.day.pctWater}%`}"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-4 grid grid-cols-3 gap-2 border-t border-white/5 pt-3 text-center">
                    <div v-for="macro in macros" :key="macro.label">
                        <p class="text-lg font-extrabold">{{ Math.round(macro.value) }} <span class="text-xs font-semibold text-white/50">/ {{ macro.goal }} g</span></p>
                        <p class="text-[11px] text-white/40">{{ macro.label }}</p>
                        <p class="mt-1 text-[11px] font-bold" :class="macro.text">{{ macro.pct }}% <span class="font-semibold text-white/35">· medie {{ Math.round(macro.avg) }} g</span></p>
                        <div class="mt-1 h-1 overflow-hidden rounded-full bg-white/10">
                            <div class="h-full rounded-full transition-all duration-700" :class="macro.bar" :style="{width: `${macro.pct}%`}"></div>
                        </div>
                    </div>
                </div>
                <p v-if="challenge.day.meals === 0" class="mt-3 text-center text-[11px] text-white/40">
                    Nicio masă notată în această zi — <a :href="`/today?date=${challenge.day.date}`" class="underline">adaugă</a>
                </p>
            </section>

            <button type="button" class="mt-5 h-12 w-full rounded-2xl text-sm font-semibold text-rose/80 active:bg-rose/10"
                    @click="abandonSheet = true">
                Abandonează provocarea
            </button>

            <BottomSheet :open="editDaysSheet" title="Editează numărul de zile" @close="editDaysSheet = false">
                <p class="text-sm text-white/60">
                    Planul zilnic (calorii, apă, proteine, carbohidrați, grăsimi) se recalculează automat pentru noua durată.
                </p>
                <label class="mt-4 block">
                    <span class="text-xs font-semibold uppercase tracking-wider text-white/50">Total zile</span>
                    <input v-model.number="daysForm.days" type="number" inputmode="numeric" :class="inputClass"/>
                    <p v-if="daysForm.errors.days" class="mt-1.5 text-sm text-rose">{{ daysForm.errors.days }}</p>
                </label>
                <div class="mt-2 flex gap-2">
                    <button v-for="preset in [30, 60, 90]" :key="preset" type="button"
                            class="h-9 rounded-xl bg-white/5 px-4 text-xs font-bold text-white/60 active:scale-95"
                            @click="daysForm.days = preset">
                        {{ preset }} zile
                    </button>
                </div>
                <button type="button" :disabled="daysForm.processing"
                        class="mt-4 h-14 w-full rounded-2xl bg-lime text-base font-extrabold text-ink active:scale-[0.98] disabled:opacity-50"
                        @click="saveDays">
                    {{ daysForm.processing ? 'Se salvează…' : 'Salvează' }}
                </button>
            </BottomSheet>

            <BottomSheet :open="abandonSheet" title="Abandonezi provocarea?" @close="abandonSheet = false">
                <p class="text-sm text-white/60">Progresul rămâne salvat, dar provocarea se încheie și poți porni una nouă oricând.</p>
                <button type="button" :disabled="abandoning"
                        class="mt-4 h-14 w-full rounded-2xl bg-rose text-base font-extrabold text-white active:scale-[0.98] disabled:opacity-50"
                        @click="abandon">
                    Abandonează
                </button>
            </BottomSheet>
        </template>
    </FitLayout>
</template>
