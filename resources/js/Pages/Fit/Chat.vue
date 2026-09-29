<script setup>
import {computed, nextTick, onBeforeUnmount, onMounted, ref, watch} from 'vue';
import axios from 'axios';
import {Head, Link, router, usePage} from '@inertiajs/vue3';
import {onRealtime, realtimeConnected} from '@/Composables/useRealtime.js';
import BottomSheet from '@/Components/Fit/BottomSheet.vue';
import {ChevronLeftIcon, ClockIcon, ExclamationCircleIcon} from '@heroicons/vue/24/outline/index.js';
import {CheckIcon} from '@heroicons/vue/20/solid/index.js';
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

const lastId = () => list.value.reduce((max, message) => (typeof message.id === 'number' ? Math.max(max, message.id) : max), 0);

// layout: a fixed screen glued to the visible area, so opening the keyboard shrinks the message list
// instead of letting iOS push the whole page (header included) upwards
const scroller = ref(null);
const screen = ref({top: 0, height: null});
const keyboardOpen = computed(() => screen.value.height !== null && window.innerHeight - screen.value.height > 120);

const nearBottom = () => {
    const el = scroller.value;
    return !el || el.scrollHeight - el.scrollTop - el.clientHeight < 120;
};

async function scrollToBottom() {
    await nextTick();
    if (scroller.value) scroller.value.scrollTop = scroller.value.scrollHeight;
}

let wasAtBottom = true;

function fitToViewport() {
    const viewport = window.visualViewport;
    wasAtBottom = nearBottom();
    screen.value = viewport ? {top: viewport.offsetTop, height: viewport.height} : {top: 0, height: window.innerHeight};
    // iOS may still scroll the document to reveal the text field; there is nothing to scroll to
    if (window.scrollY !== 0) window.scrollTo(0, 0);
    // like any messenger: when you were reading the latest messages, they stay just above the keyboard
    if (wasAtBottom) scrollToBottom();
}

// updates: pushed over the websocket when it is up, polling every 4 s otherwise; with a live connection
// a slow poll every 20 s still runs, which also tells the server the chat is open (no push meanwhile)
const POLL_MS = 4000;
const CONNECTED_POLL_MS = 20000;
let timer = null;
let polling = false;
let lastPollAt = 0;

function pollTick() {
    if (realtimeConnected.value && Date.now() - lastPollAt < CONNECTED_POLL_MS) return;
    poll();
}

async function poll() {
    if (polling || document.visibilityState !== 'visible') return;
    polling = true;
    lastPollAt = Date.now();

    try {
        const {data} = await axios.get(`/chat/${props.friend.id}/messages`, {params: {after: lastId()}});
        readUpTo.value = data.readUpTo;

        const stick = nearBottom();
        const added = data.messages.map(upsert).some(Boolean);
        if (added && stick) scrollToBottom();
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
    document.documentElement.style.overflow = 'hidden';
    document.body.style.overflow = 'hidden';
    fitToViewport();
    window.visualViewport?.addEventListener('resize', fitToViewport);
    window.visualViewport?.addEventListener('scroll', fitToViewport);
    window.addEventListener('resize', fitToViewport);
    scrollToBottom();
    timer = setInterval(pollTick, POLL_MS);
    document.addEventListener('visibilitychange', onVisible);
    poll();
});

// live events for this conversation
const me = usePage().props.auth.user.id;
const inThisChat = ({senderId, recipientId}) => (senderId === props.friend.id && recipientId === me)
    || (senderId === me && recipientId === props.friend.id);

const unsubscribe = [
    onRealtime(me, 'message.sent', (event) => inThisChat(event) && poll()),
    onRealtime(me, 'messages.read', ({readerId, senderId, upTo}) => {
        if (readerId === props.friend.id && senderId === me) readUpTo.value = Math.max(readUpTo.value, upTo);
    }),
];

// catch up on anything missed while the connection was down
watch(realtimeConnected, (connected) => connected && poll());

onBeforeUnmount(() => {
    unsubscribe.forEach((off) => off());
    document.documentElement.style.overflow = '';
    document.body.style.overflow = '';
    window.visualViewport?.removeEventListener('resize', fitToViewport);
    window.visualViewport?.removeEventListener('scroll', fitToViewport);
    window.removeEventListener('resize', fitToViewport);
    clearInterval(timer);
    document.removeEventListener('visibilitychange', onVisible);
});

// older history
const loadingOlder = ref(false);

async function loadOlder() {
    const first = list.value.find((message) => typeof message.id === 'number');
    if (!first || loadingOlder.value) return;
    loadingOlder.value = true;

    const el = scroller.value;
    const heightBefore = el.scrollHeight;

    try {
        const {data} = await axios.get(`/chat/${props.friend.id}/messages`, {params: {before: first.id}});
        list.value.unshift(...data.messages);
        more.value = data.hasMore;
        await nextTick();
        // keep the message you were looking at in place
        el.scrollTop += el.scrollHeight - heightBefore;
    } finally {
        loadingOlder.value = false;
    }
}

// sending, WhatsApp style: the bubble shows up at once with a clock, then ✓ when saved and ✓✓ once read.
// Each message carries an id made on this device, so retrying a failed one never creates a copy.
const newClientId = () => (crypto.randomUUID ? crypto.randomUUID()
    : '10000000-1000-4000-8000-100000000000'.replace(/[018]/g, (c) => (c ^ (crypto.getRandomValues(new Uint8Array(1))[0] & (15 >> (c / 4)))).toString(16)));

const pad = (n) => String(n).padStart(2, '0');

/** Adds a message from the server, replacing its local placeholder if there is one; true when it is new on screen. */
function upsert(message) {
    if (list.value.some((row) => row.id === message.id)) return false;

    const local = message.clientId ? list.value.findIndex((row) => row.clientId === message.clientId && row.status) : -1;

    if (local !== -1) {
        list.value.splice(local, 1, message);
        return false;
    }

    list.value.push(message);
    return true;
}

async function deliver(clientId) {
    const row = () => list.value.find((message) => message.clientId === clientId && message.status);
    if (!row()) return;
    Object.assign(row(), {status: 'sending', failure: null});

    try {
        const {data} = await axios.post(`/chat/${props.friend.id}`, {body: row().body, client_id: clientId});
        upsert(data.message);
    } catch (e) {
        // the message may have been saved even though the answer never arrived: check before complaining
        await poll();
        if (!row()) return;

        const status = e.response?.status;
        Object.assign(row(), {
            status: 'failed',
            failure: status === 429
                ? 'Prea multe mesaje într-un minut.'
                : (e.response?.data?.errors?.body?.[0] ?? (status ? `Eroare ${status}.` : 'Fără conexiune.')),
        });
    }
}

function send() {
    const body = draft.value.trim();
    if (!body) return;

    const now = new Date();
    const clientId = newClientId();

    list.value.push({
        id: `local-${clientId}`,
        clientId,
        body,
        mine: true,
        read: false,
        status: 'sending',
        failure: null,
        time: `${pad(now.getHours())}:${pad(now.getMinutes())}`,
        day: props.today,
    });

    draft.value = '';
    scrollToBottom();
    deliver(clientId);
}

const retry = (message) => message.status === 'failed' && deliver(message.clientId);

/** sending → sent → read, for the ticks on your own bubbles. */
const tick = (message) => {
    if (message.status) return message.status;
    return message.read || message.id <= readUpTo.value ? 'read' : 'sent';
};

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

// shared meals
const macros = (meal) => [
    {label: 'Prot', value: meal.protein},
    {label: 'Carb', value: meal.carbs},
    {label: 'Grăs', value: meal.fat},
    {label: 'Fibre', value: meal.fiber},
];

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
    <div class="fixed inset-x-0 z-10 mx-auto flex max-w-md flex-col bg-ink text-white [-webkit-tap-highlight-color:transparent]"
         :style="{top: `${screen.top}px`, height: screen.height ? `${screen.height}px` : '100dvh'}">
        <header class="pt-safe shrink-0 border-b border-white/5 px-5">
            <div class="flex items-center gap-3 py-3">
                <Link href="/chats" aria-label="Înapoi"
                      class="-ml-1 flex size-11 shrink-0 items-center justify-center rounded-full bg-white/5 text-white/80 active:scale-95">
                    <ChevronLeftIcon class="size-5"/>
                </Link>
                <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-lime to-aqua font-extrabold text-ink">
                    {{ friend.name.trim().charAt(0).toUpperCase() }}
                </span>
                <h1 class="min-w-0 flex-1 truncate text-lg font-extrabold">{{ friend.name }}</h1>
            </div>
        </header>

        <div ref="scroller" class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-5 py-4">
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
                    <button type="button" :disabled="message.mine && message.status !== 'failed'"
                            class="max-w-[80%] rounded-3xl px-4 py-2 text-left disabled:cursor-default"
                            :class="[
                                message.mine ? 'rounded-br-lg bg-lime text-ink' : 'rounded-bl-lg bg-white/10 text-white active:bg-white/15',
                                message.status === 'failed' ? 'opacity-60' : '',
                            ]"
                            @click="message.mine ? retry(message) : openMessage(message)">
                        <span v-if="message.meal" class="block min-w-52 pb-1 pt-1">
                            <span class="block text-[11px] font-semibold uppercase tracking-wider opacity-60">🍽️ Masă</span>
                            <span class="mt-1 block font-extrabold leading-tight">{{ message.meal.title }}</span>
                            <span class="mt-1 block text-2xl font-extrabold leading-none">
                                {{ message.meal.calories.toLocaleString('ro-RO') }} <span class="text-sm font-semibold opacity-60">kcal</span>
                                <span v-if="message.meal.grams" class="text-sm font-semibold opacity-60">· {{ message.meal.grams.toLocaleString('ro-RO') }} g</span>
                            </span>
                            <span class="mt-2 grid grid-cols-4 gap-1 text-center">
                                <span v-for="macro in macros(message.meal)" :key="macro.label"
                                      class="rounded-xl py-1" :class="message.mine ? 'bg-ink/10' : 'bg-white/5'">
                                    <span class="block text-sm font-extrabold leading-tight">{{ macro.value }}<span class="text-[10px] font-semibold">g</span></span>
                                    <span class="block text-[10px] font-semibold uppercase opacity-60">{{ macro.label }}</span>
                                </span>
                            </span>
                            <span v-if="message.meal.items.length" class="mt-2 block space-y-0.5 text-[13px] leading-snug">
                                <span v-for="(item, index) in message.meal.items.slice(0, 8)" :key="index" class="flex gap-2">
                                    <span class="min-w-0 flex-1 truncate">{{ item.name }} <span class="opacity-50">{{ item.grams }} g</span></span>
                                    <span class="shrink-0 font-semibold opacity-70">{{ item.calories }} kcal</span>
                                </span>
                                <span v-if="message.meal.items.length > 8" class="block opacity-50">și încă {{ message.meal.items.length - 8 }}</span>
                            </span>
                        </span>
                        <span v-if="message.body" class="whitespace-pre-wrap break-words text-[15px] leading-snug">{{ message.body }}</span>
                        <span class="ml-2 inline-flex translate-y-0.5 items-center gap-0.5 text-[10px] font-semibold"
                              :class="message.mine ? 'text-ink/50' : 'text-white/35'">
                            {{ message.time }}
                            <template v-if="message.mine">
                                <ClockIcon v-if="tick(message) === 'sending'" class="size-3.5" aria-label="Se trimite"/>
                                <ExclamationCircleIcon v-else-if="tick(message) === 'failed'" class="size-4 text-rose" aria-label="Netrimis"/>
                                <span v-else class="flex" :class="tick(message) === 'read' ? 'text-[#1467d6]' : ''"
                                      :aria-label="tick(message) === 'read' ? 'Văzut' : 'Trimis'">
                                    <CheckIcon class="size-4" :class="tick(message) === 'read' ? 'stroke-[2.5]' : ''"/>
                                    <CheckIcon v-if="tick(message) === 'read'" class="-ml-2.5 size-4 stroke-[2.5]"/>
                                </span>
                            </template>
                        </span>
                    </button>
                </div>
                <p v-if="message.status === 'failed'" class="mt-1 text-right text-[11px] text-rose">
                    Netrimis. {{ message.failure }} Atinge mesajul ca să reîncerci.
                </p>
                <p v-if="reported.has(message.id)" class="mt-1 text-[11px] text-white/40">Raportat. Mulțumim!</p>
            </template>
        </div>

        <footer class="shrink-0 border-t border-white/10 bg-panel/95 px-5 py-3" :class="{'pb-safe': !keyboardOpen}">
            <form class="flex items-end gap-2" @submit.prevent="send">
                <textarea v-model="draft" rows="1" maxlength="2000" placeholder="Scrie un mesaj…"
                          class="max-h-32 min-h-12 flex-1 resize-none rounded-3xl border border-white/10 bg-white/5 px-4 py-3 text-base text-white placeholder:text-white/30 [field-sizing:content] focus:border-lime focus:ring-0"
                          @keydown="onKeydown"></textarea>
                <button type="submit" aria-label="Trimite" :disabled="!draft.trim()"
                        @pointerdown.prevent
                        class="flex size-12 shrink-0 items-center justify-center rounded-full bg-lime text-ink active:scale-95 disabled:opacity-40">
                    <PaperAirplaneIcon class="size-5"/>
                </button>
            </form>
        </footer>

        <BottomSheet :open="!!selected" title="Mesaj" @close="selected = null">
            <template v-if="selected">
                <p class="line-clamp-4 rounded-2xl bg-white/5 p-3 text-sm text-white/70">{{ selected.meal ? `🍽️ ${selected.meal.title} · ${selected.meal.calories} kcal` : '' }} {{ selected.body }}</p>
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
    </div>
</template>
