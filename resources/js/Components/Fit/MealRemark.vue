<script setup>
import {ref} from 'vue';
import axios from 'axios';
import {SparklesIcon} from '@heroicons/vue/24/outline/index.js';

const props = defineProps({
    items: {type: Array, required: true},
    notes: {type: String, default: null},
});

const emit = defineEmits(['refined']);

const suggestions = ['Am mâncat doar jumătate', 'A fost gătit cu ulei', 'Porția era mai mare'];

const remark = ref('');
const sending = ref(false);
const error = ref(null);
const applied = ref([]);
const textsLeft = ref(null);

async function send() {
    error.value = null;
    const text = remark.value.trim();
    if (!text) return (error.value = 'Scrie ce nu e corect.');

    sending.value = true;
    try {
        const {data} = await axios.post('/meals/refine', {items: props.items, remark: text, notes: props.notes});
        textsLeft.value = data.texts_left ?? null;
        if (!data.is_food || !data.items.length) {
            error.value = data.notes || 'Remarca ar lăsa masa fără alimente. Elimină-le manual dacă asta vrei.';
            return;
        }
        applied.value.push(text);
        remark.value = '';
        emit('refined', data);
    } catch (e) {
        if (e.response?.data?.texts_left !== undefined) textsLeft.value = e.response.data.texts_left;
        error.value = e.response?.data?.errors?.remark?.[0]
            ?? e.response?.data?.message
            ?? 'A apărut o eroare. Încearcă din nou.';
    } finally {
        sending.value = false;
    }
}
</script>

<template>
    <form class="rounded-2xl border border-white/10 bg-white/[0.03] p-3.5" @submit.prevent="send">
        <p class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-white/50">
            <SparklesIcon class="size-4"/> Ceva nu e corect?
        </p>
        <ul v-if="applied.length" class="mt-2 space-y-1">
            <li v-for="(text, index) in applied" :key="index" class="text-xs text-lime/80">✓ {{ text }}</li>
        </ul>
        <textarea v-model="remark" rows="2" maxlength="500" :disabled="sending"
                  placeholder="ex: nu e orez, e cuscus; am pus și smântână"
                  class="mt-2 w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2.5 text-base text-white placeholder:text-white/30 focus:border-lime focus:ring-0 disabled:opacity-60"/>
        <div class="mt-1.5 flex flex-wrap gap-1.5">
            <button v-for="text in suggestions" :key="text" type="button" :disabled="sending"
                    class="rounded-full bg-white/5 px-2.5 py-1 text-[11px] font-semibold text-white/55 active:scale-95"
                    @click="remark = remark.trim() ? `${remark.trim()}; ${text.toLowerCase()}` : text">
                {{ text }}
            </button>
        </div>
        <button type="submit" :disabled="sending"
                class="mt-2.5 h-11 w-full rounded-xl bg-white/10 text-sm font-bold text-white active:scale-[0.98] disabled:opacity-50">
            {{ sending ? 'Recalculez…' : 'Recalculează' }}
        </button>
        <p v-if="error" class="mt-2 text-sm text-rose">{{ error }}</p>
        <p v-if="textsLeft !== null" class="mt-2 text-xs text-white/40">
            {{ textsLeft > 0 ? `${textsLeft} ${textsLeft === 1 ? 'analiză rămasă' : 'analize rămase'} azi` : 'Ai folosit toate analizele de azi' }}
        </p>
    </form>
</template>
