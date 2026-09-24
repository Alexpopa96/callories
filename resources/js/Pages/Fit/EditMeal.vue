<script setup>
import {computed, onBeforeUnmount, ref} from 'vue';
import {Head, useForm} from '@inertiajs/vue3';
import BottomSheet from '@/Components/Fit/BottomSheet.vue';
import FitLayout from '@/Layouts/FitLayout.vue';
import MealItemsEditor from '@/Components/Fit/MealItemsEditor.vue';
import MealRemark from '@/Components/Fit/MealRemark.vue';
import PhotoViewer from '@/Components/Fit/PhotoViewer.vue';
import {useMealItems} from '@/Composables/useMealItems.js';
import {shrinkImage} from '@/Composables/shrinkImage.js';
import {CameraIcon, PencilIcon, PhotoIcon, TrashIcon} from '@heroicons/vue/24/outline/index.js';

const props = defineProps({
    meal: Object,
    dateLabel: String,
});

const list = useMealItems(props.meal.items);
const title = ref(props.meal.title);
const notes = ref(props.meal.notes);
const photoOpen = ref(false);
const newPhoto = ref(null);
const newPhotoPreview = ref(null);
const removePhoto = ref(false);
const photoSheet = ref(false);
const photoBusy = ref(false);
const cameraInput = ref(null);
const galleryInput = ref(null);

// what the meal will show once saved
const photoUrl = computed(() => newPhotoPreview.value ?? (removePhoto.value ? null : props.meal.photoUrl));
const photoPending = computed(() => newPhoto.value !== null || removePhoto.value);

function clearNewPhoto() {
    if (newPhotoPreview.value) URL.revokeObjectURL(newPhotoPreview.value);
    newPhoto.value = null;
    newPhotoPreview.value = null;
}

onBeforeUnmount(clearNewPhoto);

async function pickPhoto(event) {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file) return;

    photoBusy.value = true;
    const prepared = await shrinkImage(file, 1568);
    clearNewPhoto();
    newPhoto.value = prepared;
    newPhotoPreview.value = URL.createObjectURL(prepared);
    removePhoto.value = false;
    photoBusy.value = false;
    photoSheet.value = false;
}

function markPhotoRemoved() {
    clearNewPhoto();
    removePhoto.value = true;
    photoSheet.value = false;
}

function undoPhotoChange() {
    clearNewPhoto();
    removePhoto.value = false;
}

const form = useForm({title: props.meal.title, items: [], notes: null, photo: null, remove_photo: false});

function onRefined(data) {
    list.setAnalyzed(data.items);
    notes.value = data.notes || notes.value;
}

function save() {
    form.title = title.value;
    form.items = list.payload();
    form.notes = notes.value;
    form.photo = newPhoto.value;
    form.remove_photo = removePhoto.value && !newPhoto.value;
    // sent as POST with _method so a new photo can travel as multipart form data
    form.transform((data) => ({...data, _method: 'put'})).post(`/meals/${props.meal.id}`);
}
</script>

<template>
    <Head title="Editează masa"/>
    <FitLayout title="Editează masa" :subtitle="dateLabel" :back="`/today?date=${meal.date}`" hide-nav>
        <div v-if="photoUrl" class="relative mb-4">
            <button type="button" aria-label="Deschide poza" class="block w-full active:scale-[0.99]" @click="photoOpen = true">
                <img :src="photoUrl" :alt="meal.title" class="max-h-56 w-full rounded-[2rem] border border-white/10 object-cover"/>
            </button>
            <button type="button" class="absolute bottom-3 right-3 flex items-center gap-1.5 rounded-full bg-black/60 px-3.5 py-2 text-sm font-bold text-white backdrop-blur active:scale-95"
                    @click="photoSheet = true">
                <PencilIcon class="size-4"/> Schimbă
            </button>
            <PhotoViewer :open="photoOpen" :src="photoUrl" :alt="meal.title" @close="photoOpen = false"/>
        </div>
        <button v-else type="button"
                class="mb-4 flex h-28 w-full flex-col items-center justify-center gap-1.5 rounded-[2rem] border border-dashed border-white/15 bg-white/[0.03] text-sm font-semibold text-white/50 active:scale-[0.99]"
                @click="photoSheet = true">
            <CameraIcon class="size-6"/> Adaugă poză
        </button>

        <div v-if="photoPending" class="-mt-2 mb-4 flex items-center justify-between gap-3 rounded-2xl border border-sun/25 bg-sun/[0.06] px-4 py-2.5 text-sm">
            <span class="text-white/75">{{ removePhoto ? 'Poza va fi ștearsă' : 'Poza nouă va fi folosită' }} când salvezi modificările.</span>
            <button type="button" class="shrink-0 font-bold text-sun" @click="undoPhotoChange">Anulează</button>
        </div>
        <p v-if="form.errors.photo" class="-mt-2 mb-4 text-sm text-rose">{{ form.errors.photo }}</p>

        <input ref="cameraInput" type="file" accept="image/*" capture="environment" class="hidden" @change="pickPhoto"/>
        <input ref="galleryInput" type="file" accept="image/jpeg,image/png,image/webp,image/gif" class="hidden" @change="pickPhoto"/>
        <BottomSheet :open="photoSheet" title="Poza mesei" @close="photoSheet = false">
            <div class="space-y-2">
                <button type="button" :disabled="photoBusy"
                        class="flex h-14 w-full items-center justify-center gap-2 rounded-2xl bg-lime text-base font-extrabold text-ink active:scale-[0.98] disabled:opacity-50"
                        @click="cameraInput.click()">
                    <CameraIcon class="size-5"/> Fă o poză
                </button>
                <button type="button" :disabled="photoBusy"
                        class="flex h-14 w-full items-center justify-center gap-2 rounded-2xl bg-white/5 text-base font-bold text-white active:scale-[0.98] disabled:opacity-50"
                        @click="galleryInput.click()">
                    <PhotoIcon class="size-5"/> Alege din galerie
                </button>
                <button v-if="photoUrl" type="button" :disabled="photoBusy"
                        class="flex h-14 w-full items-center justify-center gap-2 rounded-2xl bg-white/5 text-base font-bold text-rose active:scale-[0.98] disabled:opacity-50"
                        @click="markPhotoRemoved">
                    <TrashIcon class="size-5"/> Șterge poza
                </button>
            </div>
        </BottomSheet>

        <label class="block">
            <span class="text-xs font-semibold uppercase tracking-wider text-white/50">Nume</span>
            <input v-model="title" type="text" maxlength="120"
                   class="mt-1 h-12 w-full rounded-2xl border border-white/10 bg-white/5 px-4 text-base font-bold text-white focus:border-lime focus:ring-0"/>
        </label>

        <div class="mt-4 rounded-2xl bg-gradient-to-b from-panel2 to-panel p-4">
            <p class="text-xs font-semibold uppercase tracking-wider text-white/50">Total</p>
            <p class="mt-1 text-3xl font-extrabold leading-none">{{ list.totals.value.calories.toLocaleString('ro-RO') }} <span class="text-base font-semibold text-white/55">kcal</span></p>
            <p class="mt-2 text-xs text-white/50">
                P {{ list.totals.value.protein }} · C {{ list.totals.value.carbs }} · G {{ list.totals.value.fat }} · F {{ list.totals.value.fiber }}
            </p>
        </div>

        <h2 class="mb-2 mt-5 text-sm font-bold uppercase tracking-wider text-white/50">Alimente</h2>
        <p v-if="!list.items.value.length" class="rounded-2xl border border-dashed border-white/15 p-5 text-center text-sm text-white/50">
            Masa nu are alimente. Șterge-o din pagina „Azi” sau adaugă una nouă.
        </p>
        <template v-else>
            <MealItemsEditor :items="list.items.value" @grams="list.setGrams" @remove="list.remove"/>
            <MealRemark class="mt-3" :items="list.payload()" :notes="notes" @refined="onRefined"/>
            <p v-if="notes" class="mt-2 text-sm text-white/55">{{ notes }}</p>
        </template>

        <p v-if="form.errors.items" class="mt-3 text-sm text-rose">{{ form.errors.items }}</p>

        <template #footer>
            <div class="flex items-center gap-3">
                <div class="shrink-0">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-white/45">Total</p>
                    <p class="text-xl font-extrabold leading-tight">{{ list.totals.value.calories.toLocaleString('ro-RO') }} <span class="text-xs font-semibold text-white/50">kcal</span></p>
                </div>
                <button type="button" :disabled="form.processing || !list.items.value.length"
                        class="h-14 flex-1 rounded-2xl bg-lime text-base font-extrabold text-ink active:scale-[0.98] disabled:opacity-50"
                        @click="save">
                    {{ form.processing ? 'Se salvează…' : 'Salvează modificările' }}
                </button>
            </div>
        </template>
    </FitLayout>
</template>
