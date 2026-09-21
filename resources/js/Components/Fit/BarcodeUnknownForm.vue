<script setup>
import {reactive, ref} from 'vue';
import axios from 'axios';

const props = defineProps({code: {type: String, required: true}});
const emit = defineEmits(['saved', 'cancel']);

const blank = () => ({name: '', portion_grams: 100, calories: '', protein_g: 0, carbs_g: 0, fat_g: 0, fiber_g: 0});
const form = reactive(blank());
const saving = ref(false);
const error = ref(null);

const fields = [
    {key: 'calories', label: 'Calorii (kcal)'},
    {key: 'protein_g', label: 'Proteine (g)'},
    {key: 'carbs_g', label: 'Carbohidrați (g)'},
    {key: 'fat_g', label: 'Grăsimi (g)'},
    {key: 'fiber_g', label: 'Fibre (g)'},
];

async function save() {
    error.value = null;
    if (!form.name.trim()) return (error.value = 'Scrie numele produsului.');
    if (!(Number(form.portion_grams) > 0)) return (error.value = 'Porția trebuie să fie mai mare de 0 g.');
    if (form.calories === '' || Number(form.calories) < 0) return (error.value = 'Scrie caloriile.');

    saving.value = true;
    try {
        const {data} = await axios.post(`/barcode/${props.code}`, {
            name: form.name.trim(),
            portion_grams: Number(form.portion_grams),
            calories: Number(form.calories),
            protein_g: Number(form.protein_g) || 0,
            carbs_g: Number(form.carbs_g) || 0,
            fat_g: Number(form.fat_g) || 0,
            fiber_g: Number(form.fiber_g) || 0,
        });
        emit('saved', data);
    } catch (e) {
        error.value = e.response?.data?.message ?? 'A apărut o eroare. Încearcă din nou.';
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
        <p class="text-sm font-bold">Produs necunoscut</p>
        <p class="mt-1 text-xs text-white/45">
            Codul {{ code }} nu e în baza de date. Completează-l o dată, ca să-l recunoaștem automat data viitoare.
        </p>
        <div class="mt-3 space-y-3">
            <input v-model="form.name" type="text" maxlength="160" placeholder="Numele produsului"
                   class="h-12 w-full rounded-2xl border border-white/10 bg-white/5 px-4 text-base text-white placeholder:text-white/30 focus:border-lime focus:ring-0"/>
            <label class="block">
                <span class="text-xs font-semibold uppercase tracking-wider text-white/50">Porție (g)</span>
                <input v-model.number="form.portion_grams" type="number" inputmode="decimal" min="1"
                       class="mt-1 h-11 w-full rounded-2xl border border-white/10 bg-white/5 px-4 text-base font-bold text-white focus:border-lime focus:ring-0"/>
            </label>
            <div class="grid grid-cols-2 gap-3">
                <label v-for="field in fields" :key="field.key" class="block" :class="field.key === 'calories' ? 'col-span-2' : ''">
                    <span class="text-xs font-semibold uppercase tracking-wider text-white/50">{{ field.label }}</span>
                    <input v-model="form[field.key]" type="number" inputmode="decimal" min="0" step="0.1"
                           class="mt-1 h-11 w-full rounded-2xl border border-white/10 bg-white/5 px-4 text-base font-bold text-white focus:border-lime focus:ring-0"/>
                </label>
            </div>
            <p class="text-xs text-white/40">Valorile sunt pentru porția de mai sus; pe etichetă găsești de obicei valorile la 100 g.</p>
            <p v-if="error" class="text-sm text-rose">{{ error }}</p>
            <div class="flex gap-2">
                <button type="button"
                        class="h-12 flex-1 rounded-2xl bg-white/10 text-sm font-bold text-white/70 active:scale-[0.98]"
                        @click="emit('cancel')">
                    Renunță
                </button>
                <button type="button" :disabled="saving"
                        class="h-12 flex-1 rounded-2xl bg-lime text-sm font-extrabold text-ink active:scale-[0.98] disabled:opacity-50"
                        @click="save">
                    {{ saving ? 'Se salvează…' : 'Salvează produsul' }}
                </button>
            </div>
        </div>
    </div>
</template>
