<script setup>
import {ref} from 'vue';
import {Head, useForm} from '@inertiajs/vue3';
import FitLayout from '@/Layouts/FitLayout.vue';
import MealItemsEditor from '@/Components/Fit/MealItemsEditor.vue';
import MealRemark from '@/Components/Fit/MealRemark.vue';
import PhotoViewer from '@/Components/Fit/PhotoViewer.vue';
import {useMealItems} from '@/Composables/useMealItems.js';

const props = defineProps({
    meal: Object,
    dateLabel: String,
});

const list = useMealItems(props.meal.items);
const title = ref(props.meal.title);
const notes = ref(props.meal.notes);
const photoOpen = ref(false);
const form = useForm({title: props.meal.title, items: [], notes: null});

function onRefined(data) {
    list.setAnalyzed(data.items);
    notes.value = data.notes || notes.value;
}

function save() {
    form.title = title.value;
    form.items = list.payload();
    form.notes = notes.value;
    form.put(`/meals/${props.meal.id}`);
}
</script>

<template>
    <Head title="Editează masa"/>
    <FitLayout title="Editează masa" :subtitle="dateLabel" :back="`/today?date=${meal.date}`" hide-nav>
        <template v-if="meal.photoUrl">
            <button type="button" aria-label="Deschide poza" class="mb-4 block w-full active:scale-[0.99]" @click="photoOpen = true">
                <img :src="meal.photoUrl" :alt="meal.title" class="max-h-56 w-full rounded-[2rem] border border-white/10 object-cover"/>
            </button>
            <PhotoViewer :open="photoOpen" :src="meal.photoUrl" :alt="meal.title" @close="photoOpen = false"/>
        </template>

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
