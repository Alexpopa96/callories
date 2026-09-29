<script setup>
import {computed, nextTick, onBeforeUnmount, onMounted, ref, watch} from 'vue';
import axios from 'axios';
import {Head, router} from '@inertiajs/vue3';
import FitLayout from '@/Layouts/FitLayout.vue';
import BottomSheet from '@/Components/Fit/BottomSheet.vue';
import {PaperAirplaneIcon} from '@heroicons/vue/24/solid/index.js';

const props = defineProps({
    friend: Object,
    messages: Array,
    hasMore: Boolean,
    today: String,
});

const list = ref([...props.messages]);
const more = ref(props.hasMore);
const readUpTo = ref(0);
const draft = ref('');
const sending = ref(false);
const error = ref('');

const lastId = () => list.value.reduce((max, message) => (typeof message.id === 'number' ? Math.max(max, message.id) : max), 0);

// scrolling: the page itself scrolls, the composer sits in the fixed footer
const nearBottom = () => window.innerHeight + window.scrollY >= document.body.scrollHeight - 120;

async function scrollToBottom() {
    await nextTick();
    window.scrollTo({top: document.body.scrollHeight});
}

// polling while the chat is open and visible
const POLL_MS = 4000;
let timer = null;
let polling = false;

async function poll() {
    if (polling || document.visibilityState !== 'visible') return;
    polling = true;

    try {
        const {data} = await axios.get(`/chat/${props.friend.id}/messages`, {params: {after: lastId()}});
        const known = new Set(list.value.map((message) => message.id));
        const fresh = data.messages.filter((message) => !known.has(message.id));
        readUpTo.value = data.readUpTo;

        if (fresh.length) {
            const stick = nearBottom();
            list.value.push(...fresh);
            if (stick) scrollToBottom();
        }

        // a send that looked failed turned out to be saved
        const saved = pendingClientId && !sending.value && fresh.find((message) => message.clientId === pendingClientId);
        if (saved) confirmSent(saved);
    } catch (e) {
        // unfriended or blocked meanwhile: the page itself explains it
        if (e.response?.status === 404) router.visit(`/chat/${props.friend.id}`);
    } finally {
        polling = false;
    }
}

function onVisible() {
    if (document.visibilityState === 'visible') poll();
}

onMounted(() => {
    window.scrollTo({top: document.body.scrollHeight});
    timer = setInterval(poll, POLL_MS);
    document.addEventListener('visibilitychange', onVisible);
    poll();
});

onBeforeUnmount(() => {
    clearInterval(timer);
    document.removeEventListener('visibilitychange', onVisible);
});

// older history
const loadingOlder = ref(false);

async function loadOlder() {
    const first = list.value.find((message) => typeof message.id === 'number');
    if (!first || loadingOlder.value) return;
    loadingOlder.value = true;

    const heightBefore = document.body.scrollHeight;

    try {
        const {data} = await axios.get(`/chat/${props.friend.id}/messages`, {params: {before: first.id}});
        list.value.unshift(...data.messages);
        more.value = data.hasMore;
        await nextTick();
        // keep the message you were looking at in place
        window.scrollBy({top: document.body.scrollHeight - heightBefore});
    } finally {
        loadingOlder.value = false;
    }
}

// sending: the draft keeps its id until it is confirmed, so pressing send again after an error is a safe retry
let pendingClientId = null;

const newClientId = () => (crypto.randomUUID ? crypto.randomUUID()
    : '10000000-1000-4000-8000-100000000000'.replace(/[018]/g, (c) => (c ^ (crypto.getRandomValues(new Uint8Array(1))[0] & (15 >> (c / 4)))).toString(16)));

// editing the text after a failed send makes it a different message
watch(draft, () => {
    if (!sending.value) pendingClientId = null;
});

function confirmSent(message) {
    draft.value = '';
    pendingClientId = null;
    error.value = '';
    if (!list.value.some((row) => row.id === message.id)) list.value.push(message);
    scrollToBottom();
}

async function send() {
    const body = draft.value.trim();
    if (!body || sending.value) return;

    sending.value = true;
    error.value = '';
    pendingClientId ??= newClientId();
    const clientId = pendingClientId;

    try {
        const {data} = await axios.post(`/chat/${props.friend.id}`, {body, client_id: clientId});
        confirmSent(data.message);
    } catch (e) {
        // the message may have been saved even though the answer never arrived: check before complaining
        await poll();
        const saved = list.value.find((message) => message.clientId === clientId);

        if (saved) {
            confirmSent(saved);
        } else {
            const status = e.response?.status;
            error.value = status === 429
                ? 'Prea multe mesaje într-un minut. Mai așteaptă puțin.'
                : (e.response?.data?.errors?.body?.[0] ?? `Mesajul nu a putut fi trimis${status ? ` (cod ${status})` : ' (fără conexiune)'}. Apasă din nou pentru a reîncerca.`);
        }
    } finally {
        sending.value = false;
    }
}

function onKeydown(event) {
    // Enter sends on a physical keyboard, Shift+Enter adds a line; phones keep Enter as a new line
    if (event.key === 'Enter' && !event.shiftKey && window.matchMedia('(hover: hover)').matches) {
        event.preventDefault();
        send();
    }
}

// grouping by day
const dayLabel = (day) => {
    if (day === props.today) return 'Azi';
    const yesterday = new Date(`${props.today}T12:00:00`);
    yesterday.setDate(yesterday.getDate() - 1);
    if (day === yesterday.toISOString().slice(0, 10)) return 'Ieri';
    return new Date(`${day}T12:00:00`).toLocaleDateString('ro-RO', {weekday: 'long', day: 'numeric', month: 'long'});
};

const rows = computed(() => list.value.map((message, index) => ({
    ...message,
    showDay: index === 0 || list.value[index - 1].day !== message.day,
    // tighter spacing between consecutive messages from the same person
    grouped: index > 0 && list.value[index - 1].mine === message.mine && list.value[index - 1].day === message.day,
})));

const lastMineId = computed(() => [...list.value].reverse().find((message) => message.mine)?.id ?? null);

// report and block
const selected = ref(null);
const reportReason = ref('');
const reported = ref(new Set());
const acting = ref(false);

function openMessage(message) {
    if (message.mine) return;
    selected.value = message;
    reportReason.value = '';
}

async function report() {
    acting.value = true;
    try {
        await axios.post(`/chat/messages/${selected.value.id}/report`, {reason: reportReason.value || null});
        reported.value = new Set([...reported.value, selected.value.id]);
        selected.value = null;
    } finally {
        acting.value = false;
    }
}

function block() {
    acting.value = true;
    router.post(`/blocks/${props.friend.id}`, {}, {onFinish: () => (acting.value = false)});
}
</script>

<template>
    <Head :title="friend.name"/>
    <FitLayout :title="friend.name" subtitle="Chat" back="/chats" hide-nav>
        <div class="pb-4">
            <div v-if="more" class="mb-3 flex justify-center">
                <button type="button" :disabled="loadingOlder"
                        class="h-9 rounded-full bg-white/5 px-4 text-xs font-bold text-white/60 active:scale-95 disabled:opacity-50"
                        @click="loadOlder">
                    {{ loadingOlder ? 'Se încarcă…' : 'Mesaje mai vechi' }}
                </button>
            </div>

            <div v-if="!list.length" class="mt-16 text-center">
                <p class="text-4xl">👋</p>
                <p class="mt-3 font-bold">Începe conversația cu {{ friend.name }}</p>
                <p class="mt-1 text-sm text-white/45">Mesajele se văd doar între voi doi.</p>
            </div>

            <template v-for="message in rows" :key="message.id">
                <p v-if="message.showDay" class="my-4 text-center text-[11px] font-semibold uppercase tracking-wider text-white/35">
                    {{ dayLabel(message.day) }}
                </p>
                <div class="flex" :class="[message.mine ? 'justify-end' : 'justify-start', message.grouped ? 'mt-1' : 'mt-3']">
                    <button type="button" :disabled="message.mine"
                            class="max-w-[80%] rounded-3xl px-4 py-2 text-left disabled:cursor-default"
                            :class="message.mine ? 'rounded-br-lg bg-lime text-ink' : 'rounded-bl-lg bg-white/10 text-white active:bg-white/15'"
                            @click="openMessage(message)">
                        <span class="whitespace-pre-wrap break-words text-[15px] leading-snug">{{ message.body }}</span>
                        <span class="ml-2 inline-block translate-y-0.5 text-[10px] font-semibold"
                              :class="message.mine ? 'text-ink/50' : 'text-white/35'">{{ message.time }}</span>
                    </button>
                </div>
                <p v-if="message.mine && message.id === lastMineId && (message.read || message.id <= readUpTo)"
                   class="mt-1 text-right text-[11px] text-white/40">Văzut</p>
                <p v-if="reported.has(message.id)" class="mt-1 text-[11px] text-white/40">Raportat. Mulțumim!</p>
            </template>
        </div>

        <template #footer>
            <p v-if="error" class="mb-2 text-sm text-rose">{{ error }}</p>
            <form class="flex items-end gap-2" @submit.prevent="send">
                <textarea v-model="draft" rows="1" maxlength="2000" placeholder="Scrie un mesaj…"
                          class="max-h-32 min-h-12 flex-1 resize-none rounded-3xl border border-white/10 bg-white/5 px-4 py-3 text-[15px] text-white placeholder:text-white/30 [field-sizing:content] focus:border-lime focus:ring-0"
                          @keydown="onKeydown"></textarea>
                <button type="submit" aria-label="Trimite" :disabled="sending || !draft.trim()"
                        class="flex size-12 shrink-0 items-center justify-center rounded-full bg-lime text-ink active:scale-95 disabled:opacity-40">
                    <PaperAirplaneIcon class="size-5"/>
                </button>
            </form>
        </template>

        <BottomSheet :open="!!selected" title="Mesaj" @close="selected = null">
            <template v-if="selected">
                <p class="line-clamp-4 rounded-2xl bg-white/5 p-3 text-sm text-white/70">{{ selected.body }}</p>
                <template v-if="!reported.has(selected.id)">
                    <label class="mt-4 block">
                        <span class="text-xs font-semibold uppercase tracking-wider text-white/50">Motiv (opțional)</span>
                        <input v-model="reportReason" type="text" maxlength="500" placeholder="ex. mesaj jignitor, spam"
                               class="mt-1 h-12 w-full rounded-2xl border border-white/10 bg-white/5 px-4 text-white placeholder:text-white/30 focus:border-lime focus:ring-0"/>
                    </label>
                    <button type="button" :disabled="acting"
                            class="mt-3 h-14 w-full rounded-2xl bg-white/10 text-base font-bold text-white active:scale-[0.98] disabled:opacity-50"
                            @click="report">
                        Raportează mesajul
                    </button>
                </template>
                <button type="button" :disabled="acting"
                        class="mt-3 h-14 w-full rounded-2xl bg-rose/15 text-base font-bold text-rose active:scale-[0.98] disabled:opacity-50"
                        @click="block">
                    Blochează pe {{ friend.name }}
                </button>
                <p class="mt-2 text-xs text-white/40">Rapoartele ajung la echipa aplicației. Blocarea încheie prietenia și conversația.</p>
            </template>
        </BottomSheet>
    </FitLayout>
</template>
