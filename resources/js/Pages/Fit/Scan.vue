<script setup>
import {computed, defineAsyncComponent, onBeforeUnmount, ref} from 'vue';
import {Head, Link, useForm} from '@inertiajs/vue3';
import axios from 'axios';
import FitLayout from '@/Layouts/FitLayout.vue';
import MealItemsEditor from '@/Components/Fit/MealItemsEditor.vue';
import {useMealItems} from '@/Composables/useMealItems.js';

const BarcodeScanner = defineAsyncComponent(() => import('@/Components/Fit/BarcodeScanner.vue'));
import {CameraIcon, PencilSquareIcon, PhotoIcon, QrCodeIcon} from '@heroicons/vue/24/outline/index.js';

const props = defineProps({
    date: String,
    dateLabel: String,
    isToday: Boolean,
    scansLeft: {type: Number, default: null},
});

const MAX_SIDE = 1568;

const cameraInput = ref(null);
const galleryInput = ref(null);
const photo = ref(null);
const previewUrl = ref(null);
const analyzing = ref(false);
const error = ref(null);
const result = ref(null);
const list = useMealItems();
const scansLeft = ref(props.scansLeft);

const flow = ref(null); // null | 'photo' | 'barcode'
const barcodeScanning = ref(false);
const barcodeLooking = ref(false);
const barcodeError = ref(null);
const manualCode = ref('');
const cameraSupported = typeof navigator !== 'undefined' && !!navigator.mediaDevices?.getUserMedia;

const showFooter = computed(() => (flow.value === 'photo' && result.value?.is_food)
    || (flow.value === 'barcode' && list.items.value.length > 0));

const form = useForm({
    date: props.date,
    photo: null,
    items: [],
    confidence: null,
    notes: null,
});

const confidenceLabels = {low: 'Încredere scăzută', medium: 'Încredere medie', high: 'Încredere ridicată'};
const confidenceClasses = {
    low: 'bg-rose/15 text-rose',
    medium: 'bg-sun/15 text-sun',
    high: 'bg-lime/15 text-lime',
};

function revokePreview() {
    if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
}

onBeforeUnmount(revokePreview);

async function shrink(file) {
    try {
        const bitmap = await createImageBitmap(file);
        const scale = Math.min(1, MAX_SIDE / Math.max(bitmap.width, bitmap.height));
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(bitmap.width * scale);
        canvas.height = Math.round(bitmap.height * scale);
        canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.85));
        return blob ? new File([blob], 'photo.jpg', {type: 'image/jpeg'}) : file;
    } catch {
        return file;
    }
}

function reset() {
    revokePreview();
    photo.value = null;
    previewUrl.value = null;
    result.value = null;
    error.value = null;
    list.set([]);
    flow.value = null;
    barcodeScanning.value = false;
    barcodeError.value = null;
    manualCode.value = '';
    if (cameraInput.value) cameraInput.value.value = '';
    if (galleryInput.value) galleryInput.value.value = '';
}

async function onFileChange(event) {
    const file = event.target.files[0];
    if (!file) return;

    reset();
    flow.value = 'photo';
    analyzing.value = true;

    const prepared = await shrink(file);
    photo.value = prepared;
    previewUrl.value = URL.createObjectURL(prepared);

    try {
        const body = new FormData();
        body.append('photo', prepared);
        const {data} = await axios.post('/scan/analyze', body);
        result.value = data;
        list.set(data.items);
        scansLeft.value = data.scans_left ?? null;
    } catch (e) {
        if (e.response?.data?.scans_left !== undefined) scansLeft.value = e.response.data.scans_left;
        error.value = e.response?.data?.errors?.photo?.[0]
            ?? e.response?.data?.message
            ?? 'A apărut o eroare. Încearcă din nou.';
    } finally {
        analyzing.value = false;
    }
}

function save() {
    form.photo = photo.value;
    form.items = list.payload();
    form.confidence = result.value.confidence;
    form.notes = result.value.notes;
    form.post('/meals', {forceFormData: true});
}

function startBarcode() {
    reset();
    flow.value = 'barcode';
    if (cameraSupported) barcodeScanning.value = true;
}

async function lookupBarcode(value = manualCode.value) {
    barcodeError.value = null;
    const digits = String(value).replace(/\D/g, '');
    if (digits.length < 8) return (barcodeError.value = 'Codul trebuie să aibă cel puțin 8 cifre.');

    barcodeLooking.value = true;
    try {
        const {data} = await axios.get(`/barcode/${digits}`);
        list.add(data);
        manualCode.value = '';
    } catch (e) {
        barcodeError.value = e.response?.data?.message ?? 'A apărut o eroare. Încearcă din nou.';
    } finally {
        barcodeLooking.value = false;
    }
}

function onBarcodeDetected(value) {
    barcodeScanning.value = false;
    lookupBarcode(value);
}

function saveBarcodeMeal() {
    form.photo = null;
    form.items = list.payload();
    form.confidence = null;
    form.notes = null;
    form.post('/meals');
}

function saveMeal() {
    if (flow.value === 'barcode') return saveBarcodeMeal();
    return save();
}
</script>

<template>
    <Head title="Scanează masa"/>
    <FitLayout title="Scanează masa" :subtitle="isToday ? 'Se salvează azi' : `Se salvează pe ${dateLabel}`"
               :back="`/today?date=${date}`" hide-nav>
        <input ref="cameraInput" type="file" accept="image/*" capture="environment" class="hidden"
               @change="onFileChange"/>
        <input ref="galleryInput" type="file" accept="image/jpeg,image/png,image/webp,image/gif" class="hidden"
               @change="onFileChange"/>

        <section v-if="flow === null"
                 class="rounded-[2rem] border border-dashed border-white/15 bg-white/[0.03] px-6 py-10 text-center">
            <div class="mx-auto flex size-20 items-center justify-center rounded-full bg-gradient-to-br from-lime to-aqua text-ink shadow-[0_14px_40px_-10px_rgba(184,243,74,0.6)]">
                <CameraIcon class="size-10"/>
            </div>
            <h2 class="mt-5 text-xl font-extrabold">Fotografiază mâncarea</h2>
            <p class="mx-auto mt-1.5 max-w-64 text-sm text-white/55">
                Pune farfuria în cadru, cu lumină bună, și estimăm caloriile și macronutrienții.
            </p>
            <button type="button"
                    class="mt-6 flex h-14 w-full items-center justify-center gap-2 rounded-2xl bg-lime text-base font-extrabold text-ink active:scale-[0.98]"
                    @click="cameraInput.click()">
                <CameraIcon class="size-5"/> Fă o poză
            </button>
            <button type="button"
                    class="mt-3 flex h-14 w-full items-center justify-center gap-2 rounded-2xl bg-white/5 text-base font-bold text-white/80 active:scale-[0.98]"
                    @click="galleryInput.click()">
                <PhotoIcon class="size-5"/> Alege din galerie
            </button>
            <button type="button"
                    class="mt-3 flex h-14 w-full items-center justify-center gap-2 rounded-2xl bg-white/5 text-base font-bold text-white/80 active:scale-[0.98]"
                    @click="startBarcode">
                <QrCodeIcon class="size-5"/> Scanează cod de bare
            </button>
            <Link :href="`/meals/create?date=${date}`"
                  class="mt-3 flex h-14 w-full items-center justify-center gap-2 rounded-2xl border border-white/10 text-base font-bold text-white/70 active:scale-[0.98]">
                <PencilSquareIcon class="size-5"/> Adaugă manual sau din recente
            </Link>
            <p v-if="scansLeft !== null" class="mt-3 text-xs text-white/40">
                {{ scansLeft > 0 ? `${scansLeft} ${scansLeft === 1 ? 'analiză rămasă' : 'analize rămase'} azi` : 'Ai folosit toate analizele de azi' }}
            </p>
        </section>

        <template v-else-if="flow === 'barcode'">
            <section v-if="!list.items.value.length"
                     class="rounded-[2rem] border border-dashed border-white/15 bg-white/[0.03] px-6 py-10 text-center">
                <div class="mx-auto flex size-20 items-center justify-center rounded-full bg-gradient-to-br from-lime to-aqua text-ink shadow-[0_14px_40px_-10px_rgba(184,243,74,0.6)]">
                    <QrCodeIcon class="size-10"/>
                </div>
                <h2 class="mt-5 text-xl font-extrabold">Scanează codul de bare</h2>
                <p class="mx-auto mt-1.5 max-w-64 text-sm text-white/55">
                    Îndreaptă camera spre codul produsului și preluăm automat valorile nutriționale.
                </p>
                <button v-if="cameraSupported" type="button"
                        class="mt-6 flex h-14 w-full items-center justify-center gap-2 rounded-2xl bg-lime text-base font-extrabold text-ink active:scale-[0.98]"
                        @click="barcodeScanning = true">
                    <QrCodeIcon class="size-5"/> Deschide camera
                </button>
                <p v-else class="mt-4 text-xs text-white/40">
                    Telefonul sau browserul acesta nu poate citi codul de bare cu camera; scrie cifrele de sub cod mai jos.
                </p>
                <div class="mt-3 flex gap-2">
                    <input v-model="manualCode" type="text" inputmode="numeric" maxlength="14"
                           placeholder="Cod de bare (EAN)"
                           class="h-13 min-w-0 flex-1 rounded-2xl border border-white/10 bg-white/5 px-4 text-base text-white placeholder:text-white/30 focus:border-lime focus:ring-0"
                           @keyup.enter="lookupBarcode()"/>
                    <button type="button" :disabled="barcodeLooking"
                            class="h-13 rounded-2xl bg-white/10 px-5 font-bold text-white active:scale-95 disabled:opacity-50"
                            @click="lookupBarcode()">
                        {{ barcodeLooking ? '…' : 'Caută' }}
                    </button>
                </div>
                <p v-if="barcodeError" class="mt-3 text-sm text-rose">{{ barcodeError }}</p>
                <p class="mt-3 text-xs text-white/40">Datele vin din Open Food Facts.</p>
                <button type="button"
                        class="mt-3 flex h-12 w-full items-center justify-center gap-2 rounded-2xl text-sm font-semibold text-white/50 active:scale-[0.98]"
                        @click="reset">
                    Renunță
                </button>
            </section>

            <section v-else>
                <div class="rounded-[2rem] border border-white/10 bg-gradient-to-b from-panel2 to-panel p-5">
                    <p class="text-xs font-semibold uppercase tracking-wider text-white/50">Total scanat</p>
                    <p class="mt-1 text-4xl font-extrabold leading-none tracking-tight">
                        {{ list.totals.value.calories.toLocaleString('ro-RO') }}
                        <span class="text-base font-semibold text-white/55">kcal</span>
                    </p>
                </div>

                <h3 class="mb-2 mt-5 text-sm font-bold uppercase tracking-wider text-white/50">Produse scanate</h3>
                <p class="mb-2 text-xs text-white/40">Corectează porția dacă produsul e altă cantitate decât cea standard.</p>
                <MealItemsEditor :items="list.items.value" @grams="list.setGrams" @remove="list.remove"/>

                <p v-if="barcodeLooking" class="mt-3 text-sm text-white/55">Caut produsul…</p>
                <p v-if="barcodeError" class="mt-3 text-sm text-rose">{{ barcodeError }}</p>

                <div class="mt-4 flex gap-2">
                    <input v-model="manualCode" type="text" inputmode="numeric" maxlength="14"
                           placeholder="Alt cod de bare"
                           class="h-12 min-w-0 flex-1 rounded-2xl border border-white/10 bg-white/5 px-4 text-sm text-white placeholder:text-white/30 focus:border-lime focus:ring-0"
                           @keyup.enter="lookupBarcode()"/>
                    <button type="button" :disabled="barcodeLooking"
                            class="h-12 rounded-2xl bg-white/10 px-4 text-sm font-bold text-white active:scale-95 disabled:opacity-50"
                            @click="lookupBarcode()">
                        Caută
                    </button>
                </div>
                <button v-if="cameraSupported" type="button"
                        class="mt-3 h-12 w-full rounded-2xl bg-white/5 text-sm font-semibold text-white/70 active:scale-[0.98]"
                        @click="barcodeScanning = true">
                    Scanează alt produs
                </button>
                <button type="button"
                        class="mt-3 h-12 w-full rounded-2xl text-sm font-semibold text-white/50 active:scale-[0.98]"
                        @click="reset">
                    Renunță
                </button>
            </section>

            <BarcodeScanner v-if="barcodeScanning" @detected="onBarcodeDetected" @close="barcodeScanning = false"/>
        </template>

        <template v-else-if="flow === 'photo'">
            <div class="relative overflow-hidden rounded-[2rem] border border-white/10 bg-panel">
                <img v-if="previewUrl" :src="previewUrl" alt="Poza mesei" class="w-full object-cover"
                     :class="result ? 'h-36' : 'max-h-80'"/>
                <div v-if="analyzing"
                     class="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-ink/70 backdrop-blur-sm">
                    <div class="size-12 animate-spin rounded-full border-4 border-white/15 border-t-lime"></div>
                    <p class="text-sm font-semibold text-white/80">Analizez farfuria…</p>
                </div>
            </div>

            <p v-if="error" class="mt-4 rounded-2xl bg-rose/10 px-4 py-3 text-sm font-medium text-rose">{{ error }}</p>

            <section v-if="result && !result.is_food" class="mt-4 rounded-[1.75rem] bg-panel p-5">
                <p class="font-bold">Nu am găsit mâncare în poză.</p>
                <p v-if="result.notes" class="mt-1 text-sm text-white/55">{{ result.notes }}</p>
            </section>

            <section v-else-if="result" class="mt-4">
                <div class="rounded-[2rem] border border-white/10 bg-gradient-to-b from-panel2 to-panel p-5">
                    <div class="flex items-end justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-white/50">Total estimat</p>
                            <p class="mt-1 text-4xl font-extrabold leading-none tracking-tight">
                                {{ list.totals.value.calories.toLocaleString('ro-RO') }}
                                <span class="text-base font-semibold text-white/55">kcal</span>
                            </p>
                        </div>
                        <span class="rounded-full px-3 py-1 text-xs font-bold" :class="confidenceClasses[result.confidence]">
                            {{ confidenceLabels[result.confidence] }}
                        </span>
                    </div>
                    <div class="mt-4 grid grid-cols-4 gap-2 text-center">
                        <div class="rounded-2xl bg-white/5 py-2.5">
                            <p class="text-lg font-extrabold leading-none text-aqua">{{ list.totals.value.protein }}<span class="text-xs"> g</span></p>
                            <p class="mt-1 text-[11px] text-white/50">Proteine</p>
                        </div>
                        <div class="rounded-2xl bg-white/5 py-2.5">
                            <p class="text-lg font-extrabold leading-none text-sun">{{ list.totals.value.carbs }}<span class="text-xs"> g</span></p>
                            <p class="mt-1 text-[11px] text-white/50">Carbohidrați</p>
                        </div>
                        <div class="rounded-2xl bg-white/5 py-2.5">
                            <p class="text-lg font-extrabold leading-none text-rose">{{ list.totals.value.fat }}<span class="text-xs"> g</span></p>
                            <p class="mt-1 text-[11px] text-white/50">Grăsimi</p>
                        </div>
                        <div class="rounded-2xl bg-white/5 py-2.5">
                            <p class="text-lg font-extrabold leading-none text-lime">{{ list.totals.value.fiber }}<span class="text-xs"> g</span></p>
                            <p class="mt-1 text-[11px] text-white/50">Fibre</p>
                        </div>
                    </div>
                </div>

                <h3 class="mb-2 mt-5 text-sm font-bold uppercase tracking-wider text-white/50">Alimente găsite</h3>
                <p class="mb-2 text-xs text-white/40">Corectează porția dacă estimarea nu se potrivește; valorile se recalculează.</p>
                <MealItemsEditor :items="list.items.value" @grams="list.setGrams" @remove="list.remove"/>

                <p v-if="result.notes" class="mt-3 text-sm text-white/55">{{ result.notes }}</p>
                <p class="mt-2 text-xs text-white/35">Valorile sunt estimări din poză, nu măsurători exacte.</p>
                <p v-if="form.errors.items || form.errors.date" class="mt-3 text-sm text-rose">
                    {{ form.errors.items || form.errors.date }}
                </p>
            </section>

            <button v-if="!analyzing" type="button"
                    class="mt-3 h-12 w-full rounded-2xl bg-white/5 text-sm font-semibold text-white/70 active:scale-[0.98]"
                    @click="reset">
                Altă poză
            </button>
        </template>

        <template v-if="showFooter" #footer>
            <div class="flex items-center gap-3">
                <div class="shrink-0">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-white/45">Total</p>
                    <p class="text-xl font-extrabold leading-tight">{{ list.totals.value.calories.toLocaleString('ro-RO') }} <span class="text-xs font-semibold text-white/50">kcal</span></p>
                </div>
                <button type="button" :disabled="form.processing || list.items.value.length === 0"
                        class="h-14 flex-1 rounded-2xl bg-lime text-base font-extrabold text-ink active:scale-[0.98] disabled:opacity-50"
                        @click="saveMeal">
                    {{ form.processing ? 'Se salvează…' : 'Salvează masa' }}
                </button>
            </div>
        </template>
    </FitLayout>
</template>
