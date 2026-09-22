<script setup>
import {computed, ref} from 'vue';
import {Head, Link, router} from '@inertiajs/vue3';
import FitLayout from '@/Layouts/FitLayout.vue';
import ProgressRing from '@/Components/Fit/ProgressRing.vue';
import BottomSheet from '@/Components/Fit/BottomSheet.vue';
import ChallengeCard from '@/Components/Fit/ChallengeCard.vue';
import {BeakerIcon, BoltIcon, CameraIcon, ChevronLeftIcon, ChevronRightIcon, FireIcon, PencilSquareIcon, PlusIcon, ScaleIcon, SparklesIcon, TrashIcon} from '@heroicons/vue/24/outline/index.js';

const props = defineProps({
    date: String,
    dateLabel: String,
    isToday: Boolean,
    strip: Array,
    week: Object,
    goals: Object,
    totals: Object,
    steps: Number,
    waterMl: Number,
    exerciseCalories: {type: Number, default: 0},
    weightKg: {type: Number, default: null},
    challenge: {type: Object, default: null},
    meals: Array,
});

const fmt = (value) => Math.round(value).toLocaleString('ro-RO');
const liters = (ml) => (ml / 1000).toLocaleString('ro-RO', {maximumFractionDigits: 2});

// caloriile arse la sport se adaugă la bugetul zilei, nu doar la ziua curentă
const calorieBudget = computed(() => props.goals.calories + props.exerciseCalories);
const remaining = computed(() => calorieBudget.value - props.totals.calories);

const macros = computed(() => {
    const total = props.totals.protein + props.totals.carbs + props.totals.fat;
    return [
        {label: 'Proteine', value: props.totals.protein, goal: props.goals.proteinG, bar: 'bg-aqua'},
        {label: 'Carbohidrați', value: props.totals.carbs, goal: props.goals.carbsG, bar: 'bg-sun'},
        {label: 'Grăsimi', value: props.totals.fat, goal: props.goals.fatG, bar: 'bg-rose'},
    ].map((macro) => ({
        ...macro,
        pct: macro.goal ? Math.min(100, Math.round((macro.value / macro.goal) * 100)) : (total > 0 ? Math.round((macro.value / total) * 100) : 0),
    }))
        // fibrele fac parte din carbohidrați, deci bara lor e raportată la 30 g/zi, nu la total
        .concat({label: 'Fibre', value: props.totals.fiber, goal: null, bar: 'bg-lime', pct: Math.min(100, Math.round((props.totals.fiber / 30) * 100))});
});

function goToDate(date) {
    if (date === props.date) return;
    router.get('/today', {date}, {preserveScroll: true, preserveState: true});
}

const waterSheet = ref(false);
const customWater = ref('');

function addWater(delta) {
    router.put('/log/water', {date: props.date, delta}, {preserveScroll: true});
}

function addCustomWater() {
    const amount = parseInt(customWater.value, 10);
    if (!(amount > 0)) return;
    addWater(amount);
    customWater.value = '';
    waterSheet.value = false;
}

const stepsSheet = ref(false);
const stepsInput = ref('');

function openSteps() {
    stepsInput.value = props.steps ? String(props.steps) : '';
    stepsSheet.value = true;
}

function bumpSteps(amount) {
    stepsInput.value = String((parseInt(stepsInput.value, 10) || 0) + amount);
}

function saveSteps() {
    const steps = parseInt(stepsInput.value, 10);
    if (Number.isNaN(steps) || steps < 0) return;
    router.put('/log/steps', {date: props.date, steps}, {
        preserveScroll: true,
        onSuccess: () => (stepsSheet.value = false),
    });
}

const exerciseSheet = ref(false);
const exerciseInput = ref('');

function openExercise() {
    exerciseInput.value = props.exerciseCalories ? String(props.exerciseCalories) : '';
    exerciseSheet.value = true;
}

function bumpExercise(amount) {
    exerciseInput.value = String(Math.max(0, (parseInt(exerciseInput.value, 10) || 0) + amount));
}

function saveExercise() {
    const calories = parseInt(exerciseInput.value, 10) || 0;
    if (calories < 0) return;
    router.put('/log/exercise', {date: props.date, calories}, {
        preserveScroll: true,
        onSuccess: () => (exerciseSheet.value = false),
    });
}

function removeMeal(meal) {
    if (!confirm(`Ștergi „${meal.title}”?`)) return;
    router.delete(`/meals/${meal.id}`, {preserveScroll: true});
}
</script>

<template>
    <Head title="Azi"/>
    <FitLayout :title="isToday ? 'Azi' : dateLabel" :subtitle="isToday ? dateLabel : 'Ziua selectată'"
               :scan-date="date">
        <div class="flex items-center justify-between">
            <button type="button" aria-label="Săptămâna anterioară"
                    class="-ml-2 flex size-11 items-center justify-center rounded-full text-white/60 active:scale-90"
                    @click="goToDate(week.prev)">
                <ChevronLeftIcon class="size-5"/>
            </button>
            <p class="text-sm font-bold text-white/70">{{ week.label }}</p>
            <button type="button" aria-label="Săptămâna următoare" :disabled="!week.next"
                    class="-mr-2 flex size-11 items-center justify-center rounded-full text-white/60 active:scale-90 disabled:opacity-25"
                    @click="goToDate(week.next)">
                <ChevronRightIcon class="size-5"/>
            </button>
        </div>

        <div class="grid grid-cols-7 gap-1.5">
            <button v-for="day in strip" :key="day.date" type="button" :data-selected="day.date === date" :disabled="day.future"
                    class="flex min-w-0 flex-col items-center gap-1 rounded-2xl border py-2.5 transition active:scale-95 disabled:opacity-30 disabled:active:scale-100"
                    :class="day.date === date
                        ? 'border-transparent bg-lime text-ink'
                        : 'border-white/10 bg-white/[0.04] text-white/70'"
                    @click="goToDate(day.date)">
                <span class="text-[11px] font-semibold uppercase">{{ day.weekday }}</span>
                <span class="text-lg font-extrabold leading-none">{{ day.day }}</span>
                <span class="size-1.5 rounded-full"
                      :class="day.hasData ? (day.date === date ? 'bg-ink' : 'bg-lime') : 'bg-transparent'"></span>
            </button>
        </div>

        <section class="mt-5 rounded-[2rem] border border-white/10 bg-gradient-to-b from-panel2 to-panel p-5">
            <div class="flex items-center gap-5">
                <ProgressRing :value="totals.calories" :max="calorieBudget" :size="148" :stroke="14" warn-over>
                    <span class="text-3xl font-extrabold leading-none tracking-tight">{{ fmt(totals.calories) }}</span>
                    <span class="mt-1 text-xs font-medium text-white/50">din {{ fmt(calorieBudget) }} kcal</span>
                </ProgressRing>

                <div class="min-w-0 flex-1">
                    <p class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-white/50">
                        <FireIcon class="size-4 text-lime"/> Calorii
                    </p>
                    <p v-if="remaining >= 0" class="mt-1.5 text-2xl font-extrabold leading-tight">
                        {{ fmt(remaining) }} <span class="text-base font-semibold text-white/60">kcal rămase</span>
                    </p>
                    <p v-else class="mt-1.5 text-2xl font-extrabold leading-tight text-rose">
                        +{{ fmt(-remaining) }} <span class="text-base font-semibold">kcal peste</span>
                    </p>
                    <p class="mt-1 text-xs text-white/45">{{ meals.length }} {{ meals.length === 1 ? 'masă' : 'mese' }} înregistrate</p>
                    <p v-if="exerciseCalories > 0" class="mt-0.5 text-xs text-white/45">+{{ fmt(exerciseCalories) }} kcal arse la sport</p>
                </div>
            </div>

            <div class="mt-5 grid grid-cols-4 gap-2.5">
                <div v-for="macro in macros" :key="macro.label">
                    <div class="h-1.5 overflow-hidden rounded-full bg-white/10">
                        <div class="h-full rounded-full transition-all duration-700" :class="macro.bar"
                             :style="{width: `${macro.pct}%`}"></div>
                    </div>
                    <p class="mt-2 text-lg font-extrabold leading-none">{{ macro.value }}<span class="text-xs font-semibold text-white/50"> g</span></p>
                    <p class="mt-1 truncate text-[11px] font-medium text-white/50">{{ macro.label }}</p>
                    <p v-if="macro.goal" class="text-[10px] text-white/35">din {{ macro.goal }} g</p>
                </div>
            </div>
        </section>

        <ChallengeCard :challenge="challenge" :today-calories="totals.calories"/>

        <div class="mt-4 grid grid-cols-2 gap-4">
            <button type="button" class="flex flex-col justify-start rounded-[1.75rem] border border-white/10 bg-panel p-4 text-left transition active:scale-[0.98]"
                    @click="openSteps">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-white/50">Pași</span>
                </div>
                <div class="mt-3 flex justify-center">
                    <ProgressRing :value="steps" :max="goals.steps" :size="96" :stroke="10" color="#FF9F43">
                        <span class="text-lg font-extrabold leading-none">{{ Math.min(100, Math.round(steps / goals.steps * 100)) }}%</span>
                    </ProgressRing>
                </div>
                <p class="mt-3 text-xl font-extrabold leading-none">{{ fmt(steps) }}</p>
                <p class="mt-1 text-xs text-white/50">din {{ fmt(goals.steps) }} pași</p>
            </button>

            <div class="rounded-[1.75rem] border border-white/10 bg-panel p-4">
                <button type="button" class="block w-full text-left" @click="waterSheet = true">
                    <span class="text-xs font-semibold uppercase tracking-wider text-white/50">Apă</span>
                    <div class="mt-3 flex justify-center">
                        <ProgressRing :value="waterMl" :max="goals.waterMl" :size="96" :stroke="10" color="#38BDF8">
                            <BeakerIcon class="size-6 text-aqua"/>
                        </ProgressRing>
                    </div>
                    <p class="mt-3 text-xl font-extrabold leading-none">{{ liters(waterMl) }} <span class="text-sm font-semibold text-white/60">L</span></p>
                    <p class="mt-1 text-xs text-white/50">din {{ liters(goals.waterMl) }} L</p>
                </button>
                <button type="button"
                        class="mt-3 flex h-10 w-full items-center justify-center gap-1.5 rounded-xl bg-aqua/15 text-sm font-bold text-aqua transition active:scale-95"
                        @click="addWater(250)">
                    <PlusIcon class="size-4"/> 250 ml
                </button>
            </div>
        </div>

        <button type="button"
                class="mt-4 flex w-full items-center gap-3 rounded-[1.5rem] border border-white/10 bg-panel p-4 text-left active:scale-[0.99]"
                @click="openExercise">
            <span class="flex size-10 items-center justify-center rounded-full bg-sun/15 text-sun"><BoltIcon class="size-5"/></span>
            <span class="min-w-0 flex-1">
                <span class="block text-xs font-semibold uppercase tracking-wider text-white/50">Sport</span>
                <span class="block text-lg font-extrabold leading-tight">
                    {{ exerciseCalories > 0 ? `${fmt(exerciseCalories)} kcal arse` : 'Adaugă caloriile arse' }}
                </span>
            </span>
            <ChevronRightIcon class="size-5 text-white/35"/>
        </button>

        <Link href="/weight" class="mt-4 flex items-center gap-3 rounded-[1.5rem] border border-white/10 bg-panel p-4 active:scale-[0.99]">
            <span class="flex size-10 items-center justify-center rounded-full bg-rose/15 text-rose"><ScaleIcon class="size-5"/></span>
            <span class="min-w-0 flex-1">
                <span class="block text-xs font-semibold uppercase tracking-wider text-white/50">Greutate</span>
                <span class="block text-lg font-extrabold leading-tight">
                    {{ weightKg ? `${weightKg.toLocaleString('ro-RO')} kg` : 'Adaugă prima măsurătoare' }}
                </span>
            </span>
            <ChevronRightIcon class="size-5 text-white/35"/>
        </Link>

        <Link href="/assistant" class="mt-3 flex items-center gap-3 rounded-[1.5rem] border border-lime/20 bg-lime/[0.06] p-4 active:scale-[0.99]">
            <span class="flex size-10 items-center justify-center rounded-full bg-lime/15 text-lime"><SparklesIcon class="size-5"/></span>
            <span class="flex-1 font-bold">Ce să mai mănânc azi?</span>
            <ChevronRightIcon class="size-5 text-white/35"/>
        </Link>

        <Link href="/workout" class="mt-3 flex items-center gap-3 rounded-[1.5rem] border border-sun/20 bg-sun/[0.06] p-4 active:scale-[0.99]">
            <span class="flex size-10 items-center justify-center rounded-full bg-sun/15 text-sun"><BoltIcon class="size-5"/></span>
            <span class="flex-1 font-bold">Ce fac azi la sală?</span>
            <ChevronRightIcon class="size-5 text-white/35"/>
        </Link>

        <section class="mt-7">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-lg font-extrabold tracking-tight">Mese</h2>
                <div class="flex items-center gap-4">
                    <Link :href="`/meals/create?date=${date}`" class="text-sm font-bold text-white/60">Manual</Link>
                    <Link :href="`/scan?date=${date}`" class="text-sm font-bold text-lime">+ Scanează</Link>
                </div>
            </div>

            <div v-if="meals.length === 0"
                 class="rounded-[1.75rem] border border-dashed border-white/15 px-6 py-8 text-center">
                <div class="mx-auto flex size-14 items-center justify-center rounded-full bg-lime/10 text-lime">
                    <CameraIcon class="size-7"/>
                </div>
                <p class="mt-3 font-bold">Nicio masă înregistrată</p>
                <p class="mt-1 text-sm text-white/50">Fă o poză mâncării și afli imediat caloriile.</p>
                <Link :href="`/scan?date=${date}`"
                      class="mt-4 inline-flex h-11 items-center rounded-full bg-lime px-6 text-sm font-extrabold text-ink">
                    Scanează o masă
                </Link>
            </div>

            <ul v-else class="space-y-3">
                <li v-for="meal in meals" :key="meal.id"
                    class="flex items-center gap-3 rounded-[1.5rem] border border-white/5 bg-panel p-3">
                    <div class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-panel2 text-white/30">
                        <img v-if="meal.photoUrl" :src="meal.photoUrl" :alt="meal.title" class="size-full object-cover"/>
                        <FireIcon v-else class="size-7"/>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="line-clamp-2 font-bold leading-tight">{{ meal.title }}</p>
                        <p class="mt-0.5 text-xs text-white/45">
                            <template v-if="isToday">{{ meal.time }} · </template>P {{ meal.protein }} · C {{ meal.carbs }} · G {{ meal.fat }} · F {{ meal.fiber }}
                        </p>
                        <p class="mt-1 text-sm font-extrabold text-lime">{{ fmt(meal.calories) }} kcal</p>
                    </div>
                    <div class="flex shrink-0 flex-col">
                        <Link :href="`/meals/${meal.id}/edit`" class="flex size-11 items-center justify-center rounded-full text-white/35 transition active:scale-90 active:text-lime"
                              aria-label="Editează masa">
                            <PencilSquareIcon class="size-5"/>
                        </Link>
                        <button type="button" class="flex size-11 items-center justify-center rounded-full text-white/35 transition active:scale-90 active:text-rose"
                                aria-label="Șterge masa" @click="removeMeal(meal)">
                            <TrashIcon class="size-5"/>
                        </button>
                    </div>
                </li>
            </ul>
        </section>

        <BottomSheet :open="waterSheet" title="Adaugă apă" @close="waterSheet = false">
            <div class="grid grid-cols-4 gap-2">
                <button v-for="amount in [150, 250, 330, 500]" :key="amount" type="button"
                        class="h-14 rounded-2xl bg-aqua/15 text-sm font-extrabold text-aqua transition active:scale-95"
                        @click="addWater(amount); waterSheet = false">
                    +{{ amount }}
                </button>
            </div>
            <div class="mt-3 flex gap-2">
                <input v-model="customWater" type="number" inputmode="numeric" min="1" placeholder="Altă cantitate (ml)"
                       class="h-14 min-w-0 flex-1 rounded-2xl border border-white/10 bg-white/5 px-4 text-base text-white placeholder:text-white/30 focus:border-aqua focus:ring-0"
                       @keyup.enter="addCustomWater"/>
                <button type="button" class="h-14 rounded-2xl bg-aqua px-6 font-extrabold text-ink active:scale-95"
                        @click="addCustomWater">Adaugă</button>
            </div>
            <button type="button" :disabled="waterMl === 0"
                    class="mt-3 h-12 w-full rounded-2xl bg-white/5 text-sm font-semibold text-white/70 disabled:opacity-40"
                    @click="addWater(-250)">
                Scade 250 ml (greșeală)
            </button>
        </BottomSheet>

        <BottomSheet :open="stepsSheet" title="Pașii de azi" @close="stepsSheet = false">
            <input v-model="stepsInput" type="number" inputmode="numeric" min="0" placeholder="Număr de pași"
                   class="h-14 w-full rounded-2xl border border-white/10 bg-white/5 px-4 text-2xl font-extrabold text-white placeholder:text-base placeholder:font-normal placeholder:text-white/30 focus:border-sun focus:ring-0"
                   @keyup.enter="saveSteps"/>
            <div class="mt-3 grid grid-cols-3 gap-2">
                <button v-for="amount in [500, 1000, 2500]" :key="amount" type="button"
                        class="h-12 rounded-2xl bg-sun/15 text-sm font-extrabold text-sun active:scale-95"
                        @click="bumpSteps(amount)">
                    +{{ fmt(amount) }}
                </button>
            </div>
            <button type="button" class="mt-4 h-14 w-full rounded-2xl bg-sun text-base font-extrabold text-ink active:scale-[0.98]"
                    @click="saveSteps">
                Salvează
            </button>
        </BottomSheet>

        <BottomSheet :open="exerciseSheet" title="Calorii arse la sport" @close="exerciseSheet = false">
            <p class="mb-3 text-xs text-white/45">De pe ceasul tău, pentru {{ isToday ? 'azi' : dateLabel.toLowerCase() }}.</p>
            <input v-model="exerciseInput" type="number" inputmode="numeric" min="0" placeholder="Kcal arse"
                   class="h-14 w-full rounded-2xl border border-white/10 bg-white/5 px-4 text-2xl font-extrabold text-white placeholder:text-base placeholder:font-normal placeholder:text-white/30 focus:border-sun focus:ring-0"
                   @keyup.enter="saveExercise"/>
            <div class="mt-3 grid grid-cols-3 gap-2">
                <button v-for="amount in [100, 200, 300]" :key="amount" type="button"
                        class="h-12 rounded-2xl bg-sun/15 text-sm font-extrabold text-sun active:scale-95"
                        @click="bumpExercise(amount)">
                    +{{ fmt(amount) }}
                </button>
            </div>
            <button type="button" class="mt-4 h-14 w-full rounded-2xl bg-sun text-base font-extrabold text-ink active:scale-[0.98]"
                    @click="saveExercise">
                Salvează
            </button>
        </BottomSheet>
    </FitLayout>
</template>
