<script setup>
import {computed, nextTick, onMounted, ref} from 'vue';
import {Head, Link, router} from '@inertiajs/vue3';
import FitLayout from '@/Layouts/FitLayout.vue';
import ProgressRing from '@/Components/Fit/ProgressRing.vue';
import BottomSheet from '@/Components/Fit/BottomSheet.vue';
import {BeakerIcon, CameraIcon, ChevronRightIcon, FireIcon, PencilSquareIcon, PlusIcon, ScaleIcon, TrashIcon} from '@heroicons/vue/24/outline/index.js';

const props = defineProps({
    date: String,
    dateLabel: String,
    isToday: Boolean,
    strip: Array,
    goals: Object,
    totals: Object,
    steps: Number,
    waterMl: Number,
    weightKg: {type: Number, default: null},
    meals: Array,
});

const fmt = (value) => Math.round(value).toLocaleString('ro-RO');
const liters = (ml) => (ml / 1000).toLocaleString('ro-RO', {maximumFractionDigits: 2});

const remaining = computed(() => props.goals.calories - props.totals.calories);

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

const stripEl = ref(null);

onMounted(async () => {
    await nextTick();
    stripEl.value?.querySelector('[data-selected="true"]')?.scrollIntoView({inline: 'center', block: 'nearest'});
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

function removeMeal(meal) {
    if (!confirm(`Ștergi „${meal.title}”?`)) return;
    router.delete(`/meals/${meal.id}`, {preserveScroll: true});
}
</script>

<template>
    <Head title="Azi"/>
    <FitLayout :title="isToday ? 'Azi' : dateLabel" :subtitle="isToday ? dateLabel : 'Ziua selectată'"
               :scan-date="date">
        <div ref="stripEl" class="no-scrollbar -mx-5 flex gap-2 overflow-x-auto px-5 pb-1">
            <button v-for="day in strip" :key="day.date" type="button" :data-selected="day.date === date"
                    class="flex w-[3.4rem] shrink-0 flex-col items-center gap-1 rounded-2xl border py-2.5 transition active:scale-95"
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
                <ProgressRing :value="totals.calories" :max="goals.calories" :size="148" :stroke="14" warn-over>
                    <span class="text-3xl font-extrabold leading-none tracking-tight">{{ fmt(totals.calories) }}</span>
                    <span class="mt-1 text-xs font-medium text-white/50">din {{ fmt(goals.calories) }} kcal</span>
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
                </div>
            </div>

            <div class="mt-5 grid grid-cols-4 gap-3">
                <div v-for="macro in macros" :key="macro.label">
                    <div class="h-1.5 overflow-hidden rounded-full bg-white/10">
                        <div class="h-full rounded-full transition-all duration-700" :class="macro.bar"
                             :style="{width: `${macro.pct}%`}"></div>
                    </div>
                    <p class="mt-2 text-lg font-extrabold leading-none">{{ macro.value }}<span class="text-xs font-semibold text-white/50"> {{ macro.goal ? `/ ${macro.goal} g` : 'g' }}</span></p>
                    <p class="mt-1 text-[11px] font-medium text-white/50">{{ macro.label }}</p>
                </div>
            </div>
        </section>

        <div class="mt-4 grid grid-cols-2 gap-4">
            <button type="button" class="rounded-[1.75rem] border border-white/10 bg-panel p-4 text-left transition active:scale-[0.98]"
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
                        <p class="truncate font-bold leading-tight">{{ meal.title }}</p>
                        <p class="mt-0.5 text-xs text-white/45">
                            <template v-if="isToday">{{ meal.time }} · </template>P {{ meal.protein }} · C {{ meal.carbs }} · G {{ meal.fat }} · F {{ meal.fiber }}
                        </p>
                        <p class="mt-1 text-sm font-extrabold text-lime">{{ fmt(meal.calories) }} kcal</p>
                    </div>
                    <div class="flex shrink-0 flex-col">
                        <Link :href="`/meals/${meal.id}/edit`" class="rounded-full p-2 text-white/35 transition active:scale-90 active:text-lime"
                              aria-label="Editează masa">
                            <PencilSquareIcon class="size-5"/>
                        </Link>
                        <button type="button" class="rounded-full p-2 text-white/35 transition active:scale-90 active:text-rose"
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
    </FitLayout>
</template>
