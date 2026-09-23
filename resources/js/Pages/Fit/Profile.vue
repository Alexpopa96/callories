<script setup>
import {computed, ref, watch} from 'vue';
import {Head, Link, router, useForm, usePage} from '@inertiajs/vue3';
import FitLayout from '@/Layouts/FitLayout.vue';
import BottomSheet from '@/Components/Fit/BottomSheet.vue';
import Section from '@/Components/Fit/Section.vue';
import {disablePush, enablePush, pushSupported} from '@/Composables/usePush.js';
import {isNativeApp, syncNativeReminders} from '@/Composables/useNativeReminders.js';
import {
    ArrowDownTrayIcon,
    ArrowRightStartOnRectangleIcon,
    BellIcon,
    CalculatorIcon,
    ChevronRightIcon,
    FolderIcon,
    KeyIcon,
    ScaleIcon,
} from '@heroicons/vue/24/outline/index.js';

const props = defineProps({
    goals: Object,
    body: Object,
    suggestion: {type: Object, default: null},
    adaptiveSuggestion: {type: Object, default: null},
    reminders: Object,
    apiKeyHint: {type: String, default: null},
    pushKey: {type: String, default: null},
});

const page = usePage();
const user = computed(() => page.props.auth.user);

const goalValues = () => ({
    calories: props.goals.calories,
    steps: props.goals.steps,
    waterMl: props.goals.waterMl,
    proteinG: props.goals.proteinG,
    carbsG: props.goals.carbsG,
    fatG: props.goals.fatG,
});

const form = useForm(goalValues());

// the recommendation can rewrite the goals from the server, so keep the form in sync
watch(() => props.goals, () => form.defaults(goalValues()).reset());

const fields = [
    {key: 'calories', label: 'Calorii pe zi', unit: 'kcal', color: 'focus:border-lime'},
    {key: 'steps', label: 'Pași pe zi', unit: 'pași', color: 'focus:border-sun'},
    {key: 'waterMl', label: 'Apă pe zi', unit: 'ml', color: 'focus:border-aqua'},
];

const macroFields = [
    {key: 'proteinG', label: 'Proteine', color: 'focus:border-aqua'},
    {key: 'carbsG', label: 'Carbohidrați', color: 'focus:border-sun'},
    {key: 'fatG', label: 'Grăsimi', color: 'focus:border-rose'},
];

const save = () => form.transform((data) => ({
    ...data,
    proteinG: data.proteinG || null,
    carbsG: data.carbsG || null,
    fatG: data.fatG || null,
})).put('/me/goals', {preserveScroll: true});

const bodyForm = useForm({
    sex: props.body.sex ?? '',
    birthDate: props.body.birthDate ?? '',
    heightCm: props.body.heightCm ?? '',
    weightKg: props.body.weightKg ?? '',
    activityLevel: props.body.activityLevel,
    goalType: props.body.goalType,
});

const saveBody = () => bodyForm.transform((data) => ({
    ...data,
    weightKg: data.weightKg === '' ? null : String(data.weightKg).replace(',', '.'),
})).put('/me/body', {preserveScroll: true});

const applySuggestion = () => router.post('/me/goals/apply', {}, {preserveScroll: true});
const applyAdaptive = () => router.post('/me/goals/adaptive/apply', {}, {preserveScroll: true});
const dismissAdaptive = () => router.post('/me/goals/adaptive/dismiss', {}, {preserveScroll: true});

const activityOptions = [
    {value: 'sedentary', label: 'Sedentar (birou, puțină mișcare)'},
    {value: 'light', label: 'Ușor activ (1–3 antrenamente/săpt.)'},
    {value: 'moderate', label: 'Activ (3–5 antrenamente/săpt.)'},
    {value: 'active', label: 'Foarte activ (6–7 antrenamente/săpt.)'},
];

const goalOptions = [
    {value: 'lose', label: 'Slăbesc'},
    {value: 'maintain', label: 'Mă mențin'},
    {value: 'gain', label: 'Iau în masă'},
];

const inputClass = 'mt-1 h-12 w-full rounded-xl border border-white/10 bg-white/5 px-4 text-base font-bold text-white focus:border-lime focus:ring-0';

// api key
const apiKeyForm = useForm({apiKey: ''});

const saveApiKey = () => apiKeyForm.put('/me/api-key', {preserveScroll: true, onSuccess: () => apiKeyForm.reset()});
const removeApiKey = () => router.delete('/me/api-key', {preserveScroll: true});

// reminders
const pushError = ref(null);
const remindersOn = ref({meals: props.reminders.meals, water: props.reminders.water, calorieLimit: props.reminders.calorieLimit, challenge: props.reminders.challenge});

async function setReminders(next) {
    pushError.value = null;
    const turningOn = (next.meals && !remindersOn.value.meals)
        || (next.water && !remindersOn.value.water)
        || (next.calorieLimit && !remindersOn.value.calorieLimit)
        || (next.challenge && !remindersOn.value.challenge);

    if (isNativeApp()) {
        const result = await syncNativeReminders(next);
        if (!result.ok) {
            pushError.value = result.reason;
            return;
        }
    } else if (turningOn) {
        const result = await enablePush(props.pushKey);
        if (!result.ok) {
            pushError.value = result.reason;
            return;
        }
    }

    remindersOn.value = next;
    router.put('/me/reminders', next, {preserveScroll: true});

    if (!isNativeApp() && !next.meals && !next.water && !next.calorieLimit && !next.challenge) await disablePush();
}

const canPush = computed(() => isNativeApp() || (pushSupported() && !!props.pushKey));

// account
const deleteSheet = ref(false);
const deleteForm = useForm({password: ''});

const deleteAccount = () => deleteForm.delete('/me', {preserveScroll: true});
const logout = () => router.post('/logout');
</script>

<template>
    <Head title="Profil"/>
    <FitLayout title="Profil" back="/today">
        <section class="flex items-center gap-4 rounded-[2rem] border border-white/10 bg-gradient-to-b from-panel2 to-panel p-5">
            <div class="flex size-16 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-lime to-aqua text-2xl font-extrabold text-ink">
                {{ user.name.trim().charAt(0).toUpperCase() }}
            </div>
            <div class="min-w-0">
                <p class="truncate text-xl font-extrabold leading-tight">{{ user.name }}</p>
                <p class="truncate text-sm text-white/50">{{ user.email }}</p>
            </div>
        </section>

        <Link href="/weight" class="mt-4 flex items-center gap-3 rounded-[1.5rem] border border-white/10 bg-panel p-4 active:scale-[0.99]">
            <span class="flex size-10 items-center justify-center rounded-full bg-rose/15 text-rose"><ScaleIcon class="size-5"/></span>
            <span class="flex-1 font-bold">Greutate și evoluție</span>
            <ChevronRightIcon class="size-5 text-white/35"/>
        </Link>

        <section v-if="adaptiveSuggestion" class="mt-4 rounded-[1.5rem] border border-aqua/25 bg-aqua/[0.06] p-4">
            <p class="text-xs font-semibold uppercase tracking-wider text-aqua">Ajustare recomandată</p>
            <p class="mt-2 text-sm text-white/80">{{ adaptiveSuggestion.message }}</p>
            <div class="mt-3 flex gap-2">
                <button type="button" class="h-12 flex-1 rounded-2xl bg-aqua font-extrabold text-ink active:scale-[0.98]" @click="applyAdaptive">
                    Da, ajustează
                </button>
                <button type="button" class="h-12 flex-1 rounded-2xl bg-white/10 font-bold text-white active:scale-[0.98]" @click="dismissAdaptive">
                    Nu, mulțumesc
                </button>
            </div>
        </section>

        <Section title="Calculează-ți obiectivele" :open="!suggestion" color="lime">
            <template #icon><CalculatorIcon class="size-5"/></template>
        <form class="space-y-3" @submit.prevent="saveBody">
            <div class="grid grid-cols-2 gap-3">
                <label class="block">
                    <span class="text-xs font-semibold uppercase tracking-wider text-white/50">Sex</span>
                    <select v-model="bodyForm.sex" :class="inputClass">
                        <option value="" disabled>Alege</option>
                        <option value="f">Feminin</option>
                        <option value="m">Masculin</option>
                    </select>
                </label>
                <label class="block">
                    <span class="text-xs font-semibold uppercase tracking-wider text-white/50">Data nașterii</span>
                    <input v-model="bodyForm.birthDate" type="date" :class="inputClass"/>
                </label>
                <label class="block">
                    <span class="text-xs font-semibold uppercase tracking-wider text-white/50">Înălțime (cm)</span>
                    <input v-model="bodyForm.heightCm" type="number" inputmode="numeric" :class="inputClass"/>
                </label>
                <label class="block">
                    <span class="text-xs font-semibold uppercase tracking-wider text-white/50">Greutate (kg)</span>
                    <input v-model="bodyForm.weightKg" type="text" inputmode="decimal" :class="inputClass"/>
                </label>
            </div>
            <label class="block">
                <span class="text-xs font-semibold uppercase tracking-wider text-white/50">Nivel de activitate</span>
                <select v-model="bodyForm.activityLevel" :class="inputClass">
                    <option v-for="option in activityOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                </select>
            </label>
            <div>
                <span class="text-xs font-semibold uppercase tracking-wider text-white/50">Scop</span>
                <div class="mt-1 flex gap-1.5 rounded-2xl bg-white/5 p-1">
                    <button v-for="option in goalOptions" :key="option.value" type="button"
                            class="h-10 flex-1 rounded-xl text-xs font-bold transition"
                            :class="bodyForm.goalType === option.value ? 'bg-lime text-ink' : 'text-white/55'"
                            @click="bodyForm.goalType = option.value">
                        {{ option.label }}
                    </button>
                </div>
            </div>
            <template v-for="key in Object.keys(bodyForm.errors)" :key="key">
                <p class="text-sm text-rose">{{ bodyForm.errors[key] }}</p>
            </template>
            <button type="submit" :disabled="bodyForm.processing || !bodyForm.isDirty"
                    class="h-12 w-full rounded-2xl bg-white/10 font-bold text-white active:scale-[0.98] disabled:opacity-40">
                Salvează datele
            </button>
        </form>

        <section v-if="suggestion" class="mt-3 rounded-[1.5rem] border border-lime/25 bg-lime/[0.06] p-4">
            <p class="text-xs font-semibold uppercase tracking-wider text-lime">Recomandare</p>
            <p class="mt-1 text-3xl font-extrabold leading-none">{{ suggestion.calories.toLocaleString('ro-RO') }} <span class="text-base font-semibold text-white/55">kcal / zi</span></p>
            <p class="mt-2 text-sm text-white/70">
                Proteine {{ suggestion.proteinG }} g · Carbohidrați {{ suggestion.carbsG }} g · Grăsimi {{ suggestion.fatG }} g · Apă {{ (suggestion.waterMl / 1000).toLocaleString('ro-RO') }} L
            </p>
            <p class="mt-1 text-xs text-white/40">Metabolism bazal {{ suggestion.bmr }} kcal, consum total estimat {{ suggestion.tdee }} kcal. Estimare orientativă, nu sfat medical.</p>
            <button type="button" class="mt-3 h-12 w-full rounded-2xl bg-lime font-extrabold text-ink active:scale-[0.98]" @click="applySuggestion">
                Aplică recomandarea
            </button>
        </section>
        <p v-else class="mt-3 text-xs text-white/40">Completează toate câmpurile, inclusiv greutatea, ca să primești o recomandare.</p>
        </Section>

        <h2 class="mb-3 mt-6 text-lg font-extrabold tracking-tight">Obiective zilnice</h2>
        <form class="space-y-3" @submit.prevent="save">
            <label v-for="field in fields" :key="field.key" class="block rounded-[1.5rem] border border-white/5 bg-panel p-4">
                <span class="text-xs font-semibold uppercase tracking-wider text-white/50">{{ field.label }}</span>
                <span class="mt-2 flex items-center gap-3">
                    <input v-model.number="form[field.key]" type="number" inputmode="numeric"
                           class="h-12 min-w-0 flex-1 rounded-xl border border-white/10 bg-white/5 px-4 text-xl font-extrabold text-white focus:ring-0"
                           :class="field.color"/>
                    <span class="w-10 text-sm font-semibold text-white/45">{{ field.unit }}</span>
                </span>
                <span v-if="form.errors[field.key]" class="mt-1.5 block text-sm text-rose">{{ form.errors[field.key] }}</span>
            </label>

            <div class="rounded-[1.5rem] border border-white/5 bg-panel p-4">
                <span class="text-xs font-semibold uppercase tracking-wider text-white/50">Macronutrienți (opțional, grame pe zi)</span>
                <div class="mt-2 grid grid-cols-3 gap-2">
                    <label v-for="field in macroFields" :key="field.key" class="block">
                        <span class="text-[11px] text-white/45">{{ field.label }}</span>
                        <input v-model.number="form[field.key]" type="number" inputmode="numeric" placeholder="—"
                               class="mt-1 h-11 w-full rounded-xl border border-white/10 bg-white/5 px-3 text-base font-bold text-white placeholder:text-white/25 focus:ring-0"
                               :class="field.color"/>
                        <span v-if="form.errors[field.key]" class="mt-1 block text-xs text-rose">{{ form.errors[field.key] }}</span>
                    </label>
                </div>
            </div>

            <button type="submit" :disabled="form.processing || !form.isDirty"
                    class="h-14 w-full rounded-2xl bg-lime text-base font-extrabold text-ink active:scale-[0.98] disabled:opacity-40">
                {{ form.processing ? 'Se salvează…' : 'Salvează obiectivele' }}
            </button>
        </form>

        <Section title="Cheie API Anthropic" :open="!apiKeyHint" color="sun">
            <template #icon><KeyIcon class="size-5"/></template>
        <p class="text-sm text-white/60">
            Scanarea pozelor, descrierea meselor în text, asistentul și antrenorul folosesc cheia ta API Anthropic.
            O creezi din <a href="https://console.anthropic.com/settings/keys" target="_blank" rel="noopener" class="font-bold text-sun underline">consola Anthropic</a>.
        </p>
        <div v-if="apiKeyHint" class="mt-3 flex items-center justify-between gap-3 rounded-2xl bg-white/5 p-3">
            <span>
                <span class="block font-bold">Cheie setată</span>
                <span class="block font-mono text-xs text-white/45">sk-ant-{{ apiKeyHint }}</span>
            </span>
            <button type="button" class="h-10 rounded-xl px-3 text-sm font-semibold text-rose/80 active:bg-rose/10" @click="removeApiKey">
                Șterge
            </button>
        </div>
        <form class="mt-3 space-y-3" @submit.prevent="saveApiKey">
            <input v-model="apiKeyForm.apiKey" type="password" autocomplete="off" spellcheck="false"
                   :placeholder="apiKeyHint ? 'Înlocuiește cheia' : 'sk-ant-…'"
                   :class="inputClass"/>
            <p v-if="apiKeyForm.errors.apiKey" class="text-sm text-rose">{{ apiKeyForm.errors.apiKey }}</p>
            <button type="submit" :disabled="apiKeyForm.processing || !apiKeyForm.apiKey"
                    class="h-12 w-full rounded-2xl bg-sun font-extrabold text-ink active:scale-[0.98] disabled:opacity-40">
                Salvează cheia
            </button>
        </form>
        </Section>

        <Section title="Notificări" color="aqua">
            <template #icon><BellIcon class="size-5"/></template>
        <div class="space-y-4">
            <label v-for="item in [{key: 'meals', label: 'Reminder pentru mese', hint: 'La 13:00 dacă nu ai mâncat și la 20:00 dacă ești sub jumătate din obiectiv'}, {key: 'water', label: 'Reminder pentru apă', hint: 'La 11:00, 15:00 și 19:00 când ești în urma obiectivului'}, {key: 'calorieLimit', label: 'Reminder limită calorii', hint: 'Te anunț când o masă te duce aproape de obiectivul zilnic sau îl depășește'}, {key: 'challenge', label: 'Reminder provocare', hint: 'Te anunț dacă nu te-ai cântărit, dacă ești în afara ritmului sau la un prag important'}]"
                   :key="item.key" class="flex items-start justify-between gap-4">
                <span>
                    <span class="block font-bold">{{ item.label }}</span>
                    <span class="block text-xs text-white/45">{{ item.hint }}</span>
                </span>
                <input type="checkbox" :checked="remindersOn[item.key]" :disabled="!canPush"
                       class="mt-1 size-6 shrink-0 rounded-md border-white/20 bg-white/5 text-lime focus:ring-0 disabled:opacity-40"
                       @change="setReminders({...remindersOn, [item.key]: $event.target.checked})"/>
            </label>
            <p v-if="!canPush" class="text-xs text-white/40">
                {{ pushKey ? 'Browserul acesta nu suportă notificări. Pe iPhone, adaugă mai întâi aplicația pe ecranul principal.' : 'Notificările nu sunt configurate pe server.' }}
            </p>
            <p v-if="pushError" class="text-sm text-rose">{{ pushError }}</p>
        </div>
        </Section>

        <Section title="Datele tale" color="rose">
            <template #icon><FolderIcon class="size-5"/></template>
        <a href="/me/export" class="flex items-center gap-3 rounded-2xl bg-white/5 p-3 active:scale-[0.99]">
            <span class="flex size-10 items-center justify-center rounded-full bg-aqua/15 text-aqua"><ArrowDownTrayIcon class="size-5"/></span>
            <span class="flex-1">
                <span class="block font-bold">Exportă datele</span>
                <span class="block text-xs text-white/45">Mese, pași, apă, greutate și obiective, într-un fișier JSON</span>
            </span>
        </a>
        <button type="button" class="mt-2 h-12 w-full rounded-2xl text-sm font-semibold text-rose/80 active:bg-rose/10" @click="deleteSheet = true">
            Șterge contul
        </button>
        </Section>

        <button type="button"
                class="mt-3 flex h-14 w-full items-center justify-center gap-2 rounded-2xl border border-rose/30 text-base font-bold text-rose active:scale-[0.98]"
                @click="logout">
            <ArrowRightStartOnRectangleIcon class="size-5"/> Ieși din cont
        </button>

        <BottomSheet :open="deleteSheet" title="Ștergi contul?" @close="deleteSheet = false">
            <p class="text-sm text-white/60">Se șterg definitiv contul, mesele, pozele, greutatea și restul datelor. Nu se poate anula.</p>
            <input v-model="deleteForm.password" type="password" autocomplete="current-password" placeholder="Parola ta"
                   class="mt-4 h-14 w-full rounded-2xl border border-white/10 bg-white/5 px-4 text-base text-white placeholder:text-white/30 focus:border-rose focus:ring-0"
                   @keyup.enter="deleteAccount"/>
            <p v-if="deleteForm.errors.password" class="mt-2 text-sm text-rose">{{ deleteForm.errors.password }}</p>
            <button type="button" :disabled="deleteForm.processing"
                    class="mt-4 h-14 w-full rounded-2xl bg-rose text-base font-extrabold text-white active:scale-[0.98] disabled:opacity-50"
                    @click="deleteAccount">
                Șterge definitiv
            </button>
        </BottomSheet>
    </FitLayout>
</template>
