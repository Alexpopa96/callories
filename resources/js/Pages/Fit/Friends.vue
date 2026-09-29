<script setup>
import {ref} from 'vue';
import {Head, router, useForm} from '@inertiajs/vue3';
import FitLayout from '@/Layouts/FitLayout.vue';
import BottomSheet from '@/Components/Fit/BottomSheet.vue';
import Section from '@/Components/Fit/Section.vue';
import {
    ArrowPathIcon,
    EllipsisHorizontalIcon,
    LinkIcon,
    NoSymbolIcon,
    ShareIcon,
    UserPlusIcon,
    UsersIcon,
} from '@heroicons/vue/24/outline/index.js';

const props = defineProps({
    code: String,
    inviteUrl: String,
    prefillCode: {type: String, default: null},
    friends: Array,
    incoming: Array,
    outgoing: Array,
    blocked: Array,
});

const initial = (name) => (name ?? '?').trim().charAt(0).toUpperCase();

// add by code
const form = useForm({code: props.prefillCode ?? ''});

function add() {
    form.post('/friends', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

// share own code
const copied = ref(false);
const canShare = typeof navigator !== 'undefined' && !!navigator.share;

async function share() {
    try {
        await navigator.share({
            title: 'Hai să fim prieteni',
            text: `Adaugă-mă ca prieten cu codul ${props.code}`,
            url: props.inviteUrl,
        });
    } catch {
        // dismissed by the user
    }
}

async function copyLink() {
    try {
        await navigator.clipboard.writeText(props.inviteUrl);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } catch {
        window.prompt('Copiază linkul:', props.inviteUrl);
    }
}

const regenerateSheet = ref(false);

function regenerate() {
    router.post('/friends/code', {}, {
        preserveScroll: true,
        onFinish: () => (regenerateSheet.value = false),
    });
}

// requests and friends
const busy = ref(null);

function run(method, url, key) {
    busy.value = key;
    router[method](url, {}, {preserveScroll: true, onFinish: () => (busy.value = null)});
}

const accept = (request) => run('post', `/friends/${request.id}/accept`, `accept-${request.id}`);
const remove = (row) => run('delete', `/friends/${row.id}`, `remove-${row.id}`);
const unblock = (row) => run('delete', `/blocks/${row.userId}`, `unblock-${row.userId}`);

const selected = ref(null);
const confirmBlock = ref(false);

function openFriend(friend) {
    selected.value = friend;
    confirmBlock.value = false;
}

function removeSelected() {
    remove(selected.value);
    selected.value = null;
}

function blockSelected() {
    const row = selected.value;
    busy.value = `block-${row.userId}`;
    router.post(`/blocks/${row.userId}`, {}, {
        preserveScroll: true,
        onFinish: () => {
            busy.value = null;
            selected.value = null;
        },
    });
}
</script>

<template>
    <Head title="Prieteni"/>
    <FitLayout title="Prieteni" back="/me">
        <section class="rounded-[2rem] border border-white/10 bg-gradient-to-b from-panel2 to-panel p-5">
            <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-lime">
                <UsersIcon class="size-4"/> Codul tău
            </p>
            <p class="mt-2 font-mono text-3xl font-extrabold tracking-[0.2em]">{{ code }}</p>
            <p class="mt-1 text-xs text-white/50">Trimite-l prietenilor. Ei văd doar numele tău, nu și emailul.</p>
            <div class="mt-4 flex gap-2">
                <button v-if="canShare" type="button"
                        class="flex h-12 flex-1 items-center justify-center gap-2 rounded-2xl bg-lime font-extrabold text-ink active:scale-[0.98]"
                        @click="share">
                    <ShareIcon class="size-5"/> Trimite
                </button>
                <button type="button"
                        class="flex h-12 flex-1 items-center justify-center gap-2 rounded-2xl font-bold active:scale-[0.98]"
                        :class="canShare ? 'bg-white/10 text-white' : 'bg-lime text-ink'"
                        @click="copyLink">
                    <LinkIcon class="size-5"/> {{ copied ? 'Copiat!' : 'Copiază linkul' }}
                </button>
                <button type="button" aria-label="Generează alt cod"
                        class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-white/5 text-white/60 active:scale-95"
                        @click="regenerateSheet = true">
                    <ArrowPathIcon class="size-5"/>
                </button>
            </div>
        </section>

        <form class="mt-4 rounded-[1.5rem] border border-white/10 bg-panel p-4" @submit.prevent="add">
            <p class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-white/50">
                <UserPlusIcon class="size-4 text-aqua"/> Adaugă un prieten
            </p>
            <div class="mt-2 flex gap-2">
                <input v-model="form.code" type="text" autocapitalize="characters" autocomplete="off" spellcheck="false"
                       maxlength="12" placeholder="Codul prietenului"
                       class="h-12 min-w-0 flex-1 rounded-2xl border border-white/10 bg-white/5 px-4 font-mono text-lg font-bold uppercase tracking-widest text-white placeholder:font-sans placeholder:text-sm placeholder:normal-case placeholder:tracking-normal placeholder:text-white/30 focus:border-lime focus:ring-0"/>
                <button type="submit" :disabled="form.processing || !form.code.trim()"
                        class="h-12 shrink-0 rounded-2xl bg-aqua px-5 font-extrabold text-ink active:scale-[0.98] disabled:opacity-40">
                    Adaugă
                </button>
            </div>
            <p v-if="form.errors.code" class="mt-1.5 text-sm text-rose">{{ form.errors.code }}</p>
            <p v-else-if="prefillCode" class="mt-1.5 text-xs text-white/50">Ai deschis un link de invitație — apasă „Adaugă” ca să trimiți cererea.</p>
        </form>

        <section v-if="incoming.length" class="mt-4 rounded-[1.5rem] border border-lime/25 bg-lime/[0.06] p-4">
            <p class="text-xs font-semibold uppercase tracking-wider text-lime">Cereri primite</p>
            <ul class="mt-2 divide-y divide-white/5">
                <li v-for="request in incoming" :key="request.id" class="flex items-center gap-3 py-2.5">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-white/10 font-extrabold">{{ initial(request.name) }}</span>
                    <span class="min-w-0 flex-1 truncate font-bold">{{ request.name }}</span>
                    <button type="button" :disabled="busy !== null"
                            class="h-10 rounded-xl bg-lime px-4 text-sm font-extrabold text-ink active:scale-95 disabled:opacity-50"
                            @click="accept(request)">
                        Acceptă
                    </button>
                    <button type="button" :disabled="busy !== null" aria-label="Mai multe"
                            class="flex size-10 items-center justify-center rounded-xl bg-white/10 text-white/70 active:scale-95 disabled:opacity-50"
                            @click="openFriend({...request, pending: true})">
                        <EllipsisHorizontalIcon class="size-5"/>
                    </button>
                </li>
            </ul>
        </section>

        <section class="mt-4 rounded-[1.5rem] border border-white/10 bg-panel p-4">
            <p class="text-xs font-semibold uppercase tracking-wider text-white/50">Prieteni · {{ friends.length }}</p>
            <ul v-if="friends.length" class="mt-2 divide-y divide-white/5">
                <li v-for="friend in friends" :key="friend.id" class="flex items-center gap-3 py-2.5">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-lime to-aqua font-extrabold text-ink">{{ initial(friend.name) }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-bold">{{ friend.name }}</p>
                        <p class="text-[11px] text-white/40">prieteni din {{ friend.since }}</p>
                    </div>
                    <button type="button" aria-label="Opțiuni"
                            class="flex size-10 items-center justify-center rounded-xl text-white/50 active:bg-white/10"
                            @click="openFriend(friend)">
                        <EllipsisHorizontalIcon class="size-5"/>
                    </button>
                </li>
            </ul>
            <p v-else class="mt-2 text-sm text-white/50">Încă nu ai prieteni aici. Trimite-le codul tău sau adaugă-l pe al lor.</p>
        </section>

        <section v-if="outgoing.length" class="mt-4 rounded-[1.5rem] border border-white/10 bg-panel p-4">
            <p class="text-xs font-semibold uppercase tracking-wider text-white/50">Cereri trimise</p>
            <ul class="mt-2 divide-y divide-white/5">
                <li v-for="request in outgoing" :key="request.id" class="flex items-center gap-3 py-2.5">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-white/10 font-extrabold text-white/60">{{ initial(request.name) }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-bold">{{ request.name }}</p>
                        <p class="text-[11px] text-white/40">în așteptare din {{ request.since }}</p>
                    </div>
                    <button type="button" :disabled="busy !== null"
                            class="h-10 rounded-xl bg-white/10 px-4 text-sm font-bold text-white/70 active:scale-95 disabled:opacity-50"
                            @click="remove(request)">
                        Anulează
                    </button>
                </li>
            </ul>
        </section>

        <Section v-if="blocked.length" :title="`Blocați · ${blocked.length}`" color="rose">
            <template #icon><NoSymbolIcon class="size-5"/></template>
            <ul class="divide-y divide-white/5">
                <li v-for="row in blocked" :key="row.userId" class="flex items-center gap-3 py-2.5">
                    <span class="min-w-0 flex-1 truncate font-bold text-white/70">{{ row.name }}</span>
                    <button type="button" :disabled="busy !== null"
                            class="h-10 rounded-xl bg-white/10 px-4 text-sm font-bold text-white/70 active:scale-95 disabled:opacity-50"
                            @click="unblock(row)">
                        Deblochează
                    </button>
                </li>
            </ul>
            <p class="mt-2 text-xs text-white/40">Cei blocați nu îți pot trimite cereri și nu știu că i-ai blocat.</p>
        </Section>

        <BottomSheet :open="!!selected" :title="selected?.name ?? ''" @close="selected = null">
            <template v-if="selected && !confirmBlock">
                <button type="button"
                        class="h-14 w-full rounded-2xl bg-white/10 text-base font-bold text-white active:scale-[0.98]"
                        @click="removeSelected">
                    {{ selected.pending ? 'Refuză cererea' : 'Elimină din prieteni' }}
                </button>
                <button type="button"
                        class="mt-3 h-14 w-full rounded-2xl bg-rose/15 text-base font-bold text-rose active:scale-[0.98]"
                        @click="confirmBlock = true">
                    Blochează
                </button>
            </template>
            <template v-else-if="selected">
                <p class="text-sm text-white/60">
                    {{ selected.name }} nu va mai fi prietenul tău și nu îți va mai putea trimite cereri. Nu va fi anunțat. Poți debloca oricând.
                </p>
                <button type="button" :disabled="busy !== null"
                        class="mt-4 h-14 w-full rounded-2xl bg-rose text-base font-extrabold text-white active:scale-[0.98] disabled:opacity-50"
                        @click="blockSelected">
                    Blochează
                </button>
            </template>
        </BottomSheet>

        <BottomSheet :open="regenerateSheet" title="Generezi alt cod?" @close="regenerateSheet = false">
            <p class="text-sm text-white/60">Codul și linkurile trimise până acum nu vor mai funcționa. Prietenii și cererile existente rămân.</p>
            <button type="button"
                    class="mt-4 h-14 w-full rounded-2xl bg-lime text-base font-extrabold text-ink active:scale-[0.98]"
                    @click="regenerate">
                Generează cod nou
            </button>
        </BottomSheet>
    </FitLayout>
</template>
