<script setup>
import {ref} from 'vue';
import {Head, useForm} from '@inertiajs/vue3';
import FitLayout from '@/Layouts/FitLayout.vue';
import MealItemsEditor from '@/Components/Fit/MealItemsEditor.vue';
import {useMealItems} from '@/Composables/useMealItems.js';

const props = defineProps({
    meal: Object,
    dateLabel: String,
});

const list = useMealItems(props.meal.items);
const title = ref(props.meal.title);
const form = useForm({title: props.meal.title, items: []});

function save() {
    form.title = title.value;
    form.items = list.payload();
    form.put(`/meals/${props.meal.id}`);
}
</script>

<template>
    <Head title="Editează masa"/>
    <FitLayout title="Editează masa" :subtitle="dateLabel" :back="`/today?date=${meal.date}`" hide-nav>
        <img v-if="meal.photoUrl" :src="meal.photoUrl" :alt="meal.title" class="mb-4 max-h-56 w-full rounded-[2rem] border border-white/10 object-cover"/>

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
        <MealItemsEditor v-else :items="list.items.value" @grams="list.setGrams" @remove="list.remove"/>

        <p v-if="form.errors.items" class="mt-3 text-sm text-rose">{{ form.errors.items }}</p>
        <button type="button" :disabled="form.processing || !list.items.value.length"
                class="mt-5 h-14 w-full rounded-2xl bg-lime text-base font-extrabold text-ink active:scale-[0.98] disabled:opacity-50"
                @click="save">
            {{ form.processing ? 'Se salvează…' : 'Salvează modificările' }}
        </button>
    </FitLayout>
</template>
