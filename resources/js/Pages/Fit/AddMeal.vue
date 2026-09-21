<script setup>
import {computed, reactive, ref} from 'vue';
import {Head, router, useForm} from '@inertiajs/vue3';
import axios from 'axios';
import FitLayout from '@/Layouts/FitLayout.vue';
import MealItemsEditor from '@/Components/Fit/MealItemsEditor.vue';
import BarcodeScanner from '@/Components/Fit/BarcodeScanner.vue';
import {useMealItems} from '@/Composables/useMealItems.js';
import {QrCodeIcon, TrashIcon} from '@heroicons/vue/24/outline/index.js';

const props = defineProps({
    date: String,
    dateLabel: String,
    isToday: Boolean,
    recent: Array,
    favorites: Array,
});

const tabs = [
    {key: 'recent', label: 'Recente'},
    {key: 'favorites', label: 'Favorite'},
    {key: 'manual', label: 'Manual'},
    {key: 'barcode', label: 'Cod bare'},
];

const tab = ref(props.recent.length ? 'recent' : 'manual');
const meal = useMealItems();
const title = ref('');
const favoriteNames = computed(() => props.favorites.map((food) => food.name));

const form = useForm({date: props.date, title: '', items: []});

function save() {
    form.title = title.value;
    form.items = meal.payload();
    form.post('/meals');
}

function toggleFavorite(index) {
    const item = meal.payload()[index];
    const existing = props.favorites.find((food) => food.name === item.name);
    if (existing) router.delete(`/favorites/${existing.id}`, {preserveState: true, preserveScroll: true});
    else router.post('/favorites', item, {preserveState: true, preserveScroll: true});
}

const removeFavorite = (food) => router.delete(`/favorites/${food.id}`, {preserveState: true, preserveScroll: true});

const blank = () => ({name: '', portion_grams: 100, calories: '', protein_g: 0, carbs_g: 0, fat_g: 0, fiber_g: 0});
const manual = reactive(blank());
const saveAsFavorite = ref(false);
const manualError = ref(null);

function addManual() {
    manualError.value = null;
    if (!manual.name.trim()) return (manualError.value = 'Scrie numele alimentului.');
    if (!(Number(manual.portion_grams) > 0)) return (manualError.value = 'Porția trebuie să fie mai mare de 0 g.');
    if (manual.calories === '' || Number(manual.calories) < 0) return (manualError.value = 'Scrie caloriile.');

    const item = {
        name: manual.name.trim(),
        portion_grams: Number(manual.portion_grams),
        calories: Number(manual.calories),
        protein_g: Number(manual.protein_g) || 0,
        carbs_g: Number(manual.carbs_g) || 0,
        fat_g: Number(manual.fat_g) || 0,
        fiber_g: Number(manual.fiber_g) || 0,
    };

    meal.add(item);
    if (saveAsFavorite.value) router.post('/favorites', item, {preserveState: true, preserveScroll: true});
    Object.assign(manual, blank());
    saveAsFavorite.value = false;
}

const manualFields = [
    {key: 'calories', label: 'Calorii (kcal)'},
    {key: 'protein_g', label: 'Proteine (g)'},
    {key: 'carbs_g', label: 'Carbohidrați (g)'},
    {key: 'fat_g', label: 'Grăsimi (g)'},
    {key: 'fiber_g', label: 'Fibre (g)'},
];

const code = ref('');
const looking = ref(false);
const barcodeError = ref(null);
const scanning = ref(false);
const cameraSupported = typeof window !== 'undefined' && 'BarcodeDetector' in window;

async function lookup(value = code.value) {
    barcodeError.value = null;
    const digits = String(value).replace(/\D/g, '');
    if (digits.length < 8) return (barcodeError.value = 'Codul trebuie să aibă cel puțin 8 cifre.');

    looking.value = true;
    try {
        const {data} = await axios.get(`/barcode/${digits}`);
        meal.add(data);
        code.value = '';
    } catch (e) {
        barcodeError.value = e.response?.data?.message ?? 'A apărut o eroare. Încearcă din nou.';
    } finally {
        looking.value = false;
    }
}

function onDetected(value) {
    scanning.value = false;
    code.value = value;
    lookup(value);
}

const fmt = (value) => Math.round(value).toLocaleString('ro-RO');
</script>

<template>
    <Head title="Adaugă masă"/>
    <FitLayout title="Adaugă masă" :subtitle="isToday ? 'Se salvează azi' : `Se salvează pe ${dateLabel}`"
               :back="`/today?date=${date}`" hide-nav>
        <div class="flex gap-1.5 rounded-2xl bg-white/5 p-1">
            <button v-for="t in tabs" :key="t.key" type="button"
                    class="h-11 flex-1 rounded-xl text-[13px] font-bold transition"
                    :class="tab === t.key ? 'bg-lime text-ink' : 'text-white/55'" @click="tab = t.key">
                {{ t.label }}
            </button>
        </div>

        <section class="mt-4">
            <template v-if="tab === 'recent'">
                <p v-if="!recent.length" class="rounded-2xl border border-dashed border-white/15 p-5 text-center text-sm text-white/50">
                    Mesele salvate apar aici, ca să le adaugi din nou dintr-o atingere.
                </p>
                <ul v-else class="space-y-2">
                    <li v-for="item in recent" :key="item.id">
                        <button type="button"
                                class="flex w-full items-center justify-between gap-3 rounded-2xl border border-white/5 bg-panel p-3.5 text-left active:scale-[0.99]"
                                @click="item.items.forEach((food) => meal.add(food))">
                            <span class="min-w-0">
                                <span class="block truncate font-bold leading-tight">{{ item.title }}</span>
                                <span class="mt-0.5 block text-xs text-white/45">{{ item.items.length }} {{ item.items.length === 1 ? 'aliment' : 'alimente' }}</span>
                            </span>
                            <span class="shrink-0 text-sm font-extrabold text-lime">{{ fmt(item.calories) }} kcal</span>
                        </button>
                    </li>
                </ul>
            </template>

            <template v-else-if="tab === 'favorites'">
                <p v-if="!favorites.length" class="rounded-2xl border border-dashed border-white/15 p-5 text-center text-sm text-white/50">
                    Nicio favorită încă. Apasă steaua de lângă un aliment adăugat ca să-l salvezi aici.
                </p>
                <ul v-else class="space-y-2">
                    <li v-for="food in favorites" :key="food.id" class="flex items-center gap-2 rounded-2xl border border-white/5 bg-panel pr-2">
                        <button type="button" class="flex min-w-0 flex-1 items-center justify-between gap-3 p-3.5 text-left"
                                @click="meal.add(food)">
                            <span class="min-w-0">
                                <span class="block truncate font-bold leading-tight">{{ food.name }}</span>
                                <span class="mt-0.5 block text-xs text-white/45">{{ Math.round(food.portion_grams) }} g</span>
                            </span>
                            <span class="shrink-0 text-sm font-extrabold text-lime">{{ fmt(food.calories) }} kcal</span>
                        </button>
                        <button type="button" aria-label="Șterge din favorite" class="flex size-11 items-center justify-center rounded-full text-white/35 active:text-rose"
                                @click="removeFavorite(food)">
                            <TrashIcon class="size-5"/>
                        </button>
                    </li>
                </ul>
            </template>

            <form v-else-if="tab === 'manual'" class="space-y-3" @submit.prevent="addManual">
                <input v-model="manual.name" type="text" maxlength="120" placeholder="Numele alimentului"
                       class="h-13 w-full rounded-2xl border border-white/10 bg-white/5 px-4 text-base text-white placeholder:text-white/30 focus:border-lime focus:ring-0"/>
                <label class="block">
                    <span class="text-xs font-semibold uppercase tracking-wider text-white/50">Porție (g)</span>
                    <input v-model.number="manual.portion_grams" type="number" inputmode="decimal" min="1"
                           class="mt-1 h-12 w-full rounded-2xl border border-white/10 bg-white/5 px-4 text-base font-bold text-white focus:border-lime focus:ring-0"/>
                </label>
                <div class="grid grid-cols-2 gap-3">
                    <label v-for="field in manualFields" :key="field.key" class="block" :class="field.key === 'calories' ? 'col-span-2' : ''">
                        <span class="text-xs font-semibold uppercase tracking-wider text-white/50">{{ field.label }}</span>
                        <input v-model="manual[field.key]" type="number" inputmode="decimal" min="0" step="0.1"
                               class="mt-1 h-12 w-full rounded-2xl border border-white/10 bg-white/5 px-4 text-base font-bold text-white focus:border-lime focus:ring-0"/>
                    </label>
                </div>
                <p class="text-xs text-white/40">Valorile sunt pentru porția de mai sus. Pe eticheta produselor găsești de obicei valorile la 100 g.</p>
                <label class="flex items-center gap-2 text-sm text-white/70">
                    <input v-model="saveAsFavorite" type="checkbox" class="size-5 rounded border-white/20 bg-white/5 text-lime focus:ring-0"/>
                    Salvează și la favorite
                </label>
                <p v-if="manualError" class="text-sm text-rose">{{ manualError }}</p>
                <button type="submit" class="h-12 w-full rounded-2xl bg-white/10 font-bold text-white active:scale-[0.98]">
                    Adaugă în masă
                </button>
            </form>

            <div v-else class="space-y-3">
                <div class="flex gap-2">
                    <input v-model="code" type="text" inputmode="numeric" maxlength="14" placeholder="Cod de bare (EAN)"
                           class="h-13 min-w-0 flex-1 rounded-2xl border border-white/10 bg-white/5 px-4 text-base text-white placeholder:text-white/30 focus:border-lime focus:ring-0"
                           @keyup.enter="lookup()"/>
                    <button type="button" :disabled="looking" class="h-13 rounded-2xl bg-lime px-5 font-extrabold text-ink active:scale-95 disabled:opacity-50"
                            @click="lookup()">
                        {{ looking ? '…' : 'Caută' }}
                    </button>
                </div>
                <button v-if="cameraSupported" type="button"
                        class="flex h-12 w-full items-center justify-center gap-2 rounded-2xl bg-white/5 font-bold text-white/80 active:scale-[0.98]"
                        @click="scanning = true">
                    <QrCodeIcon class="size-5"/> Scanează cu camera
                </button>
                <p v-else class="text-xs text-white/40">Telefonul sau browserul acesta nu poate citi codul de bare cu camera; scrie cifrele de sub cod.</p>
                <p v-if="barcodeError" class="text-sm text-rose">{{ barcodeError }}</p>
                <p class="text-xs text-white/40">Datele vin din Open Food Facts. Poți modifica porția după ce adaugi produsul.</p>
            </div>
        </section>

        <section v-if="meal.items.value.length" class="mt-6">
            <h2 class="mb-2 text-sm font-bold uppercase tracking-wider text-white/50">Masa ta</h2>
            <div class="mb-3 grid grid-cols-5 gap-2 rounded-2xl bg-gradient-to-b from-panel2 to-panel p-3 text-center">
                <div>
                    <p class="text-base font-extrabold leading-none text-lime">{{ meal.totals.value.calories }}</p>
                    <p class="mt-1 text-[10px] text-white/50">kcal</p>
                </div>
                <div>
                    <p class="text-base font-extrabold leading-none text-aqua">{{ meal.totals.value.protein }}</p>
                    <p class="mt-1 text-[10px] text-white/50">Prot.</p>
                </div>
                <div>
                    <p class="text-base font-extrabold leading-none text-sun">{{ meal.totals.value.carbs }}</p>
                    <p class="mt-1 text-[10px] text-white/50">Carbo</p>
                </div>
                <div>
                    <p class="text-base font-extrabold leading-none text-rose">{{ meal.totals.value.fat }}</p>
                    <p class="mt-1 text-[10px] text-white/50">Grăs.</p>
                </div>
                <div>
                    <p class="text-base font-extrabold leading-none text-lime">{{ meal.totals.value.fiber }}</p>
                    <p class="mt-1 text-[10px] text-white/50">Fibre</p>
                </div>
            </div>

            <MealItemsEditor :items="meal.items.value" :favorite-names="favoriteNames" allow-favorite
                             @grams="meal.setGrams" @remove="meal.remove" @favorite="toggleFavorite"/>

            <input v-model="title" type="text" maxlength="120" placeholder="Nume masă (opțional)"
                   class="mt-3 h-12 w-full rounded-2xl border border-white/10 bg-white/5 px-4 text-base text-white placeholder:text-white/30 focus:border-lime focus:ring-0"/>
            <p v-if="form.errors.items || form.errors.date" class="mt-3 text-sm text-rose">{{ form.errors.items || form.errors.date }}</p>
        </section>

        <template v-if="meal.items.value.length" #footer>
            <div class="flex items-center gap-3">
                <div class="shrink-0">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-white/45">Total</p>
                    <p class="text-xl font-extrabold leading-tight">{{ meal.totals.value.calories.toLocaleString('ro-RO') }} <span class="text-xs font-semibold text-white/50">kcal</span></p>
                </div>
                <button type="button" :disabled="form.processing"
                        class="h-14 flex-1 rounded-2xl bg-lime text-base font-extrabold text-ink active:scale-[0.98] disabled:opacity-50"
                        @click="save">
                    {{ form.processing ? 'Se salvează…' : 'Salvează masa' }}
                </button>
            </div>
        </template>

        <BarcodeScanner v-if="scanning" @detected="onDetected" @close="scanning = false"/>
    </FitLayout>
</template>
