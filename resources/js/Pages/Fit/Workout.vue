<script setup>
import {nextTick, ref} from 'vue';
import {Head, router, useForm} from '@inertiajs/vue3';
import axios from 'axios';
import FitLayout from '@/Layouts/FitLayout.vue';
import {ArrowUpIcon, BoltIcon, ChevronDownIcon, ChevronUpIcon, PlayCircleIcon, TrashIcon} from '@heroicons/vue/24/outline/index.js';

const props = defineProps({
    questionsLeft: {type: Number, default: null},
    history: {type: Array, default: () => []},
});

const prompts = [
    'Spate, umeri și triceps, sală completă',
    'Picioare, 45 de minute',
    'Full body, doar gantere acasă',
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
        const {data} = await axios.post('/workout/ask', {message: trimmed, history});
        questionsLeft.value = data.questions_left ?? null;
        messages.value.push({role: 'assistant', content: data.reply, plan: data.plan?.exercises?.length ? data.plan : null});
    } catch (e) {
        if (e.response?.data?.questions_left !== undefined) questionsLeft.value = e.response.data.questions_left;
        askError.value = e.response?.data?.message ?? 'A apărut o eroare. Încearcă din nou.';
        messages.value.pop();
    } finally {
        asking.value = false;
        scrollDown();
    }
}

const today = new Date().toISOString().slice(0, 10);
const saveForm = useForm({date: today, title: '', exercises: []});
const savedPlans = ref(new Set());

function savePlan(plan, key) {
    saveForm.date = today;
    saveForm.title = plan.title;
    saveForm.exercises = plan.exercises;
    saveForm.post('/workout', {
        preserveScroll: true,
        onSuccess: () => savedPlans.value.add(key),
    });
}

const historyOpen = ref(new Set());

function toggleHistory(id) {
    historyOpen.value.has(id) ? historyOpen.value.delete(id) : historyOpen.value.add(id);
}

function removeWorkout(workout) {
    if (!confirm(`Ștergi „${workout.title}”?`)) return;
    router.delete(`/workout/${workout.id}`, {preserveScroll: true});
}

const fmtDate = (date) => new Date(date).toLocaleDateString('ro-RO', {day: 'numeric', month: 'short'});

const tutorialUrl = (name) => `https://www.youtube.com/results?search_query=${encodeURIComponent(`${name} tehnica execuție exercițiu`)}`;
</script>

<template>
    <Head title="Antrenament"/>
    <FitLayout title="Antrenament" subtitle="Spune-i ce vrei să faci azi" back="/today" hide-nav>
        <div ref="log" class="no-scrollbar mt-1 space-y-3 overflow-y-auto" style="max-height: calc(100dvh - 20rem)">
            <div v-if="!messages.length" class="rounded-[1.75rem] border border-dashed border-white/15 px-5 py-8 text-center">
                <div class="mx-auto flex size-14 items-center justify-center rounded-full bg-sun/10 text-sun">
                    <BoltIcon class="size-7"/>
                </div>
                <p class="mt-3 font-bold">Ce vrei să faci azi la sală?</p>
                <p class="mt-1 text-sm text-white/50">Spune-i grupele musculare, timpul și echipamentul — îți face un plan.</p>
                <div class="mt-4 flex flex-col gap-2">
                    <button v-for="p in prompts" :key="p" type="button"
                            class="h-11 rounded-2xl bg-white/5 text-sm font-semibold text-white/75 active:scale-[0.98]"
                            @click="ask(p)">
                        {{ p }}
                    </button>
                </div>
            </div>

            <template v-for="(msg, index) in messages" :key="index">
                <p v-if="msg.role === 'user'" class="ml-auto max-w-[85%] w-fit rounded-2xl rounded-tr-md bg-sun px-4 py-2.5 text-sm font-semibold text-ink">
                    {{ msg.content }}
                </p>
                <div v-else class="max-w-[90%] w-fit space-y-2">
                    <p class="rounded-2xl rounded-tl-md bg-panel px-4 py-2.5 text-sm text-white/85">{{ msg.content }}</p>
                    <div v-if="msg.plan" class="rounded-2xl border border-white/5 bg-panel p-4">
                        <p class="font-bold leading-tight">{{ msg.plan.title }}</p>
                        <ul class="mt-3 space-y-2.5">
                            <li v-for="(ex, exIndex) in msg.plan.exercises" :key="exIndex" class="flex items-start justify-between gap-2">
                                <span class="min-w-0">
                                    <p class="text-sm font-semibold">{{ ex.name }} <span class="font-normal text-white/50">— {{ ex.sets }}×{{ ex.reps }}</span></p>
                                    <p v-if="ex.notes" class="text-[11px] text-white/45">{{ ex.notes }}</p>
                                </span>
                                <a :href="tutorialUrl(ex.name)" target="_blank" rel="noopener noreferrer"
                                   class="flex shrink-0 items-center gap-1 rounded-full bg-white/5 px-2.5 py-1 text-[11px] font-semibold text-white/60 active:scale-95">
                                    <PlayCircleIcon class="size-3.5"/> Tutorial
                                </a>
                            </li>
                        </ul>
                        <button type="button" :disabled="saveForm.processing || savedPlans.has(index)"
                                class="mt-4 h-11 w-full rounded-xl bg-sun text-sm font-extrabold text-ink active:scale-[0.98] disabled:opacity-50"
                                @click="savePlan(msg.plan, index)">
                            {{ savedPlans.has(index) ? 'Salvat în istoric' : 'Salvează antrenamentul' }}
                        </button>
                    </div>
                </div>
            </template>

            <div v-if="asking" class="flex w-fit items-center gap-1.5 rounded-2xl rounded-tl-md bg-panel px-4 py-3.5">
                <span class="size-2 animate-typing-dot rounded-full bg-sun"></span>
                <span class="size-2 animate-typing-dot rounded-full bg-sun [animation-delay:160ms]"></span>
                <span class="size-2 animate-typing-dot rounded-full bg-sun [animation-delay:320ms]"></span>
            </div>
        </div>

        <p v-if="askError" class="mt-2 text-sm text-rose">{{ askError }}</p>

        <section v-if="history.length" class="mt-5">
            <h2 class="mb-2 text-xs font-semibold uppercase tracking-wider text-white/50">Istoric antrenamente</h2>
            <ul class="space-y-2">
                <li v-for="workout in history" :key="workout.id" class="rounded-2xl border border-white/5 bg-panel p-3.5">
                    <button type="button" class="flex w-full items-center gap-3 text-left" @click="toggleHistory(workout.id)">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-sun/15 text-sun"><BoltIcon class="size-4"/></span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-bold leading-tight">{{ workout.title }}</span>
                            <span class="block text-[11px] text-white/45">{{ fmtDate(workout.date) }} · {{ workout.exercises.length }} exerciții</span>
                        </span>
                        <ChevronDownIcon v-if="!historyOpen.has(workout.id)" class="size-4 shrink-0 text-white/35"/>
                        <ChevronUpIcon v-else class="size-4 shrink-0 text-white/35"/>
                    </button>
                    <div v-if="historyOpen.has(workout.id)" class="mt-3 space-y-2 border-t border-white/5 pt-3">
                        <div v-for="(ex, exIndex) in workout.exercises" :key="exIndex" class="flex items-center justify-between gap-2">
                            <p class="min-w-0 truncate text-sm">{{ ex.name }}</p>
                            <span class="flex shrink-0 items-center gap-2">
                                <p class="text-xs text-white/45">{{ ex.sets }}×{{ ex.reps }}</p>
                                <a :href="tutorialUrl(ex.name)" target="_blank" rel="noopener noreferrer"
                                   aria-label="Tutorial video" class="flex size-7 items-center justify-center rounded-full bg-white/5 text-white/60 active:scale-90">
                                    <PlayCircleIcon class="size-4"/>
                                </a>
                            </span>
                        </div>
                        <button type="button" class="mt-2 flex items-center gap-1.5 text-xs font-semibold text-rose"
                                @click="removeWorkout(workout)">
                            <TrashIcon class="size-3.5"/> Șterge
                        </button>
                    </div>
                </li>
            </ul>
        </section>

        <template #footer>
            <form class="flex min-w-0 flex-1 items-center gap-2" @submit.prevent="ask()">
                <input v-model="question" type="text" maxlength="300" placeholder="Ce vrei să faci azi…"
                       :disabled="asking"
                       class="h-14 min-w-0 flex-1 rounded-2xl border border-white/10 bg-white/5 px-4 text-base text-white placeholder:text-white/30 focus:border-sun focus:ring-0"/>
                <button type="submit" :disabled="asking || !question.trim()"
                        class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-sun text-ink active:scale-95 disabled:opacity-40">
                    <ArrowUpIcon class="size-5" stroke-width="2.5"/>
                </button>
            </form>
            <p v-if="questionsLeft !== null" class="mt-2 text-center text-[11px] text-white/35">
                {{ questionsLeft > 0 ? `${questionsLeft} ${questionsLeft === 1 ? 'întrebare rămasă' : 'întrebări rămase'} azi` : 'Ai folosit toate întrebările de azi' }}
            </p>
        </template>
    </FitLayout>
</template>
