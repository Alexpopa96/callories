<script setup>
import {computed, onBeforeUnmount, onMounted, ref, watch} from 'vue';
import {Head, Link, router, usePage} from '@inertiajs/vue3';
import {onRealtime, realtimeConnected} from '@/Composables/useRealtime.js';
import {enablePush, pushSupported} from '@/Composables/usePush.js';
import {isNativeApp} from '@/Composables/useNativeReminders.js';
import FitLayout from '@/Layouts/FitLayout.vue';
import BottomSheet from '@/Components/Fit/BottomSheet.vue';
import {
    BellAlertIcon,
    ChatBubbleOvalLeftEllipsisIcon,
    MagnifyingGlassIcon,
    PencilSquareIcon,
    UserPlusIcon,
} from '@heroicons/vue/24/outline/index.js';
import {CheckIcon} from '@heroicons/vue/24/solid/index.js';

const props = defineProps({
    chats: Array,
    friends: Array,
    pushKey: {type: String, default: null},
    notifyMessages: {type: Boolean, default: true},
});

// nudge towards push when this device would miss new messages
const needsPush = ref(false);
const pushBusy = ref(false);
const pushError = ref('');

async function checkPush() {
    if (isNativeApp() || !pushSupported() || !props.pushKey) return;

    try {
        const registration = await navigator.serviceWorker.getRegistration('/sw.js');
        const subscribed = Notification.permission === 'granted' && !!(await registration?.pushManager.getSubscription());
        needsPush.value = !subscribed || !props.notifyMessages;
    } catch {
        needsPush.value = false;
    }
}

async function turnOnPush() {
    pushBusy.value = true;
    pushError.value = '';
    const result = await enablePush(props.pushKey);

    if (result.ok) {
        router.put('/me/notify-messages', {enabled: true}, {preserveScroll: true});
        needsPush.value = false;
    } else {
        pushError.value = result.reason;
    }

    pushBusy.value = false;
}

const initial = (name) => (name ?? '?').trim().charAt(0).toUpperCase();

// search across conversations by name or last message
const query = ref('');
const normalize = (text) => (text ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

const filtered = computed(() => {
    const needle = normalize(query.value.trim());
    if (!needle) return props.chats;
    return props.chats.filter((chat) => normalize(chat.name).includes(needle) || normalize(chat.lastMessage.body).includes(needle));
});

// new conversation: friends you already talk to are listed first
const newChatSheet = ref(false);
const pickable = computed(() => {
    const talking = new Set(props.chats.map((chat) => chat.userId));
    return [...props.friends].sort((a, b) => Number(talking.has(b.userId)) - Number(talking.has(a.userId)));
});

// refresh on live events; poll every 8 s only while the websocket is down
const REFRESH_MS = 8000;
let timer = null;

function refresh() {
    if (document.visibilityState !== 'visible') return;
    router.reload({only: ['chats', 'social'], preserveScroll: true, preserveState: true});
}

function tick() {
    if (!realtimeConnected.value) refresh();
}

const me = usePage().props.auth.user.id;
const unsubscribe = [
    onRealtime(me, 'message.sent', refresh),
    onRealtime(me, 'messages.read', refresh),
];

watch(realtimeConnected, (connected) => connected && refresh());

function onVisible() {
    if (document.visibilityState === 'visible') refresh();
}

onMounted(() => {
    checkPush();
    timer = setInterval(tick, REFRESH_MS);
    document.addEventListener('visibilitychange', onVisible);
});

onBeforeUnmount(() => {
    unsubscribe.forEach((off) => off());
    clearInterval(timer);
    document.removeEventListener('visibilitychange', onVisible);
});
</script>

<template>
    <Head title="Chat"/>
    <FitLayout title="Chat">
        <section v-if="needsPush" class="mb-3 flex items-center gap-3 rounded-[1.5rem] border border-aqua/25 bg-aqua/[0.06] p-4">
            <BellAlertIcon class="size-6 shrink-0 text-aqua"/>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-bold">Primește notificare la mesaje noi</p>
                <p v-if="pushError" class="text-xs text-rose">{{ pushError }}</p>
                <p v-else class="text-xs text-white/50">Altfel le vezi doar când deschizi aplicația.</p>
            </div>
            <button type="button" :disabled="pushBusy"
                    class="h-10 shrink-0 rounded-xl bg-aqua px-4 text-sm font-extrabold text-ink active:scale-95 disabled:opacity-50"
                    @click="turnOnPush">
                Activează
            </button>
        </section>
        <label v-if="chats.length" class="relative block">
            <MagnifyingGlassIcon class="pointer-events-none absolute left-4 top-1/2 size-5 -translate-y-1/2 text-white/35"/>
            <input v-model="query" type="search" placeholder="Caută" autocomplete="off"
                   class="h-11 w-full rounded-2xl border-0 bg-white/5 pl-11 pr-4 text-base text-white placeholder:text-white/35 focus:ring-1 focus:ring-lime"/>
        </label>

        <ul v-if="filtered.length" class="-mx-2 mt-2">
            <li v-for="chat in filtered" :key="chat.userId">
                <Link :href="`/chat/${chat.userId}`" class="flex items-center gap-3 rounded-2xl px-2 py-2.5 active:bg-white/5">
                    <span class="flex size-12 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-lime to-aqua text-lg font-extrabold text-ink">
                        {{ initial(chat.name) }}
                    </span>
                    <div class="min-w-0 flex-1 border-b border-white/5 pb-2.5">
                        <div class="flex items-baseline gap-2">
                            <p class="min-w-0 flex-1 truncate text-[16px] font-bold">{{ chat.name }}</p>
                            <span class="shrink-0 text-[11px]" :class="chat.unread ? 'font-bold text-lime' : 'text-white/40'">{{ chat.lastMessage.when }}</span>
                        </div>
                        <div class="mt-0.5 flex items-center gap-2">
                            <p class="flex min-w-0 flex-1 items-center gap-1 text-sm"
                               :class="chat.unread ? 'font-semibold text-white' : 'text-white/50'">
                                <span v-if="chat.lastMessage.mine" class="flex shrink-0" :class="chat.lastMessage.read ? 'text-aqua' : 'text-white/35'"
                                      :aria-label="chat.lastMessage.read ? 'Văzut' : 'Trimis'">
                                    <CheckIcon class="size-4"/><CheckIcon v-if="chat.lastMessage.read" class="-ml-2.5 size-4"/>
                                </span>
                                <span class="truncate">{{ chat.lastMessage.body }}</span>
                            </p>
                            <span v-if="chat.unread" class="min-w-[20px] shrink-0 rounded-full bg-lime px-1.5 text-center text-[11px] font-extrabold leading-5 text-ink">
                                {{ chat.unread > 99 ? '99+' : chat.unread }}
                            </span>
                        </div>
                    </div>
                </Link>
            </li>
        </ul>

        <p v-else-if="chats.length" class="mt-10 text-center text-sm text-white/45">Nicio conversație pentru „{{ query }}”.</p>

        <div v-else class="mt-16 text-center">
            <ChatBubbleOvalLeftEllipsisIcon class="mx-auto size-12 text-white/25"/>
            <p class="mt-3 font-bold">Nicio conversație încă</p>
            <p class="mt-1 text-sm text-white/45">
                {{ friends.length ? 'Apasă pe creion ca să scrii unui prieten.' : 'Adaugă prieteni ca să poți vorbi cu ei.' }}
            </p>
            <Link v-if="!friends.length" href="/friends"
                  class="mt-5 inline-flex h-12 items-center gap-2 rounded-2xl bg-lime px-5 font-extrabold text-ink active:scale-[0.98]">
                <UserPlusIcon class="size-5"/> Adaugă prieteni
            </Link>
        </div>

        <button v-if="friends.length" type="button" aria-label="Conversație nouă"
                class="fixed bottom-[calc(env(safe-area-inset-bottom)+5.5rem)] right-[max(1.25rem,calc(50%-14rem+1.25rem))] z-30 flex size-14 items-center justify-center rounded-2xl bg-lime text-ink shadow-[0_10px_30px_-6px_rgba(184,243,74,0.6)] active:scale-95"
                @click="newChatSheet = true">
            <PencilSquareIcon class="size-6"/>
        </button>

        <BottomSheet :open="newChatSheet" title="Conversație nouă" @close="newChatSheet = false">
            <ul class="-mx-2 max-h-[60vh] overflow-y-auto">
                <li v-for="friend in pickable" :key="friend.userId">
                    <Link :href="`/chat/${friend.userId}`" class="flex items-center gap-3 rounded-2xl px-2 py-2.5 active:bg-white/5">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-lime to-aqua font-extrabold text-ink">{{ initial(friend.name) }}</span>
                        <span class="min-w-0 flex-1 truncate font-bold">{{ friend.name }}</span>
                    </Link>
                </li>
            </ul>
            <Link href="/friends" class="mt-3 flex h-12 items-center justify-center gap-2 rounded-2xl bg-white/5 text-sm font-bold text-white/70 active:scale-[0.98]">
                <UserPlusIcon class="size-5"/> Adaugă un prieten nou
            </Link>
        </BottomSheet>
    </FitLayout>
</template>
