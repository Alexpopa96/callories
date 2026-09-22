<script setup>
import {computed, nextTick, reactive, ref} from 'vue';
import {Head, useForm} from '@inertiajs/vue3';
import axios from 'axios';
import FitLayout from '@/Layouts/FitLayout.vue';
import BottomSheet from '@/Components/Fit/BottomSheet.vue';
import MealItemsEditor from '@/Components/Fit/MealItemsEditor.vue';
import {useMealItems} from '@/Composables/useMealItems.js';
import {ArrowUpIcon, PlusIcon, SparklesIcon} from '@heroicons/vue/24/outline/index.js';

const props = defineProps({
    goals: Object,
    remaining: Object,
    questionsLeft: {type: Number, default: null},
});

const chips = computed(() => [
    {label: 'Calorii', value: props.remaining.calories, unit: 'kcal'},
    ...(props.remaining.proteinG !== null ? [{label: 'Proteine', value: props.remaining.proteinG, unit: 'g'}] : []),
    ...(props.remaining.carbsG !== null ? [{label: 'Carbo', value: props.remaining.carbsG, unit: 'g'}] : []),
    ...(props.remaining.fatG !== null ? [{label: 'Grăsimi', value: props.remaining.fatG, unit: 'g'}] : []),
].map((chip) => ({...chip, over: chip.value < 0})));

const prompts = [
    'Ce mănânc ca să completez proteina?',
    'O idee rapidă de gustare',
    'Ce cinez fără să depășesc caloriile?',
];

const messages = ref([]);
const question = ref('');
const asking = ref(false);
const askError = ref(null);
const questionsLeft = ref(props.questionsLeft);
const log = ref(null);

async function scrollDown() {
    await nextTick();
    log.value?.scrollTo({top: log.value.scrollHeight, behavior: 'smooth'});
}

async function ask(text = question.value) {
    const trimmed = text.trim();
    if (!trimmed || asking.value) return;

    askError.value = null;
    messages.value.push({role: 'user', content: trimmed});
    question.value = '';
    asking.value = true;
    scrollDown();

    try {
        const history = messages.value.slice(0, -1).slice(-8).map(({role, content}) => ({role, content}));
        const {data} = await axios.post('/assistant/ask', {message: trimmed, history});
        questionsLeft.value = data.questions_left ?? null;
        messages.value.push({role: 'assistant', content: data.reply, suggestions: data.suggestions ?? []});
    } catch (e) {
        if (e.response?.data?.questions_left !== undefined) questionsLeft.value = e.response.data.questions_left;
        askError.value = e.response?.data?.message ?? 'A apărut o eroare. Încearcă din nou.';
        messages.value.pop();
    } finally {
        asking.value = false;
        scrollDown();
    }
}

// building today's meal from tapped suggestions, saved through the same flow as the other add-meal screens
const meal = useMealItems();
const cartOpen = ref(false);
const added = reactive(new Set());

function addSuggestion(suggestion, key) {
    meal.add(suggestion);
    added.add(key);
}

const today = new Date().toISOString().slice(0, 10);
const form = useForm({date: today, title: '', items: []});

function save() {
    form.items = meal.payload();
    form.post('/meals', {onSuccess: () => (cartOpen.value = false)});
}
</script>

<template>
    <Head title="Asistent"/>
    <FitLayout title="Asistent" subtitle="Întreabă ce să mănânci" back="/today" hide-nav>
        <div class="-mx-5 flex gap-2 overflow-x-auto px-5 pb-1">
            <span v-for="chip in chips" :key="chip.label"
                  class="shrink-0 rounded-full border px-3 py-1.5 text-xs font-bold"
                  :class="chip.over ? 'border-rose/30 bg-rose/10 text-rose' : 'border-white/10 bg-white/5 text-white/70'">
                {{ chip.label }}: {{ Math.round(chip.value) }} {{ chip.unit }} {{ chip.over ? 'peste' : 'rămas' }}
            </span>
        </div>

        <div ref="log" class="no-scrollbar mt-4 space-y-3 overflow-y-auto" style="max-height: calc(100dvh - 17rem)">
            <div v-if="!messages.length" class="rounded-[1.75rem] border border-dashed border-white/15 px-5 py-8 text-center">
                <div class="mx-auto flex size-14 items-center justify-center rounded-full bg-lime/10 text-lime">
                    <SparklesIcon class="size-7"/>
                </div>
                <p class="mt-3 font-bold">Întreabă orice despre masa de azi</p>
                <p class="mt-1 text-sm text-white/50">Asistentul vede ce ai mâncat și cât îți mai rămâne.</p>
                <div class="mt-4 flex flex-col gap-2">
                    <button v-for="p in prompts" :key="p" type="button"
                            class="h-11 rounded-2xl bg-white/5 text-sm font-semibold text-white/75 active:scale-[0.98]"
                            @click="ask(p)">
                        {{ p }}
                    </button>
                </div>
            </div>

            <template v-for="(msg, index) in messages" :key="index">
                <p v-if="msg.role === 'user'" class="ml-auto max-w-[85%] w-fit rounded-2xl rounded-tr-md bg-lime px-4 py-2.5 text-sm font-semibold text-ink">
                    {{ msg.content }}
                </p>
                <div v-else class="max-w-[90%] w-fit space-y-2">
                    <p class="rounded-2xl rounded-tl-md bg-panel px-4 py-2.5 text-sm text-white/85">{{ msg.content }}</p>
                    <div v-if="msg.suggestions?.length" class="space-y-2">
                        <div v-for="(s, sIndex) in msg.suggestions" :key="sIndex"
                             class="flex items-center gap-3 rounded-2xl border border-white/5 bg-panel p-3">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-bold leading-tight">{{ s.name }}</p>
                                <p class="mt-0.5 text-[11px] text-white/45">
                                    {{ Math.round(s.portion_grams) }} g · {{ Math.round(s.calories) }} kcal · P {{ s.protein_g }}
                                </p>
                            </div>
                            <button type="button" :disabled="added.has(`${index}-${sIndex}`)"
                                    class="flex h-9 shrink-0 items-center gap-1 rounded-full bg-lime/15 px-3 text-xs font-bold text-lime disabled:opacity-40"
                                    @click="addSuggestion(s, `${index}-${sIndex}`); cartOpen = true">
                                <PlusIcon class="size-3.5"/> {{ added.has(`${index}-${sIndex}`) ? 'Adăugat' : 'Adaugă' }}
                            </button>
                        </div>
                    </div>
                </div>
            </template>

            <div v-if="asking" class="flex w-fit items-center gap-1.5 rounded-2xl rounded-tl-md bg-panel px-4 py-3.5">
                <span class="size-2 animate-typing-dot rounded-full bg-lime"></span>
                <span class="size-2 animate-typing-dot rounded-full bg-lime [animation-delay:160ms]"></span>
                <span class="size-2 animate-typing-dot rounded-full bg-lime [animation-delay:320ms]"></span>
            </div>
        </div>

        <p v-if="askError" class="mt-2 text-sm text-rose">{{ askError }}</p>

        <template #footer>
            <div class="flex items-center gap-3">
                <button v-if="meal.items.value.length" type="button"
                        class="relative flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-white/10 active:scale-95"
                        aria-label="Masa ta" @click="cartOpen = true">
                    <span class="text-lg">🍽️</span>
                    <span class="absolute -right-1 -top-1 flex size-5 items-center justify-center rounded-full bg-lime text-[11px] font-extrabold text-ink">
                        {{ meal.items.value.length }}
                    </span>
                </button>
                <form class="flex min-w-0 flex-1 items-center gap-2" @submit.prevent="ask()">
                    <input v-model="question" type="text" maxlength="300" placeholder="Scrie o întrebare…"
                           :disabled="asking"
                           class="h-14 min-w-0 flex-1 rounded-2xl border border-white/10 bg-white/5 px-4 text-base text-white placeholder:text-white/30 focus:border-lime focus:ring-0"/>
                    <button type="submit" :disabled="asking || !question.trim()"
                            class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-lime text-ink active:scale-95 disabled:opacity-40">
                        <ArrowUpIcon class="size-5" stroke-width="2.5"/>
                    </button>
                </form>
            </div>
            <p v-if="questionsLeft !== null" class="mt-2 text-center text-[11px] text-white/35">
                {{ questionsLeft > 0 ? `${questionsLeft} ${questionsLeft === 1 ? 'întrebare rămasă' : 'întrebări rămase'} azi` : 'Ai folosit toate întrebările de azi' }}
            </p>
        </template>

        <BottomSheet :open="cartOpen" title="Masa ta" @close="cartOpen = false">
            <MealItemsEditor :items="meal.items.value" @grams="meal.setGrams" @remove="meal.remove"/>
            <p v-if="form.errors.items" class="mt-3 text-sm text-rose">{{ form.errors.items }}</p>
            <button type="button" :disabled="form.processing || !meal.items.value.length"
                    class="mt-4 h-14 w-full rounded-2xl bg-lime text-base font-extrabold text-ink active:scale-[0.98] disabled:opacity-50"
                    @click="save">
                {{ form.processing ? 'Se salvează…' : `Salvează în jurnalul de azi (${meal.totals.value.calories} kcal)` }}
            </button>
        </BottomSheet>
    </FitLayout>
</template>
