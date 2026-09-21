<script setup>
import {Head, Link, useForm} from '@inertiajs/vue3';
import AuthShell from '@/Components/Fit/AuthShell.vue';
import AuthInput from '@/Components/Fit/AuthInput.vue';

defineProps({
    canResetPassword: Boolean,
    status: String,
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.transform((data) => ({
        ...data,
        remember: form.remember ? 'on' : '',
    })).post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Conectare"/>
    <AuthShell title="Bine ai revenit" subtitle="Intră în cont și vezi cum a fost ziua ta.">
        <p v-if="status" class="mb-4 rounded-2xl bg-lime/10 px-4 py-3 text-sm font-medium text-lime">{{ status }}</p>

        <form class="space-y-4" @submit.prevent="submit">
            <AuthInput v-model="form.email" type="email" label="Email" placeholder="nume@exemplu.ro"
                       autocomplete="username" inputmode="email" :error="form.errors.email" autofocus/>
            <AuthInput v-model="form.password" type="password" label="Parola" placeholder="Parola ta"
                       autocomplete="current-password" :error="form.errors.password"/>

            <div class="flex items-center justify-between pt-1">
                <label class="flex cursor-pointer items-center gap-3 text-sm text-white/70">
                    <input v-model="form.remember" type="checkbox" class="peer sr-only"/>
                    <span class="relative h-6 w-11 rounded-full bg-white/10 transition peer-checked:bg-lime after:absolute after:left-0.5 after:top-0.5 after:size-5 after:rounded-full after:bg-white after:transition peer-checked:after:translate-x-5 peer-checked:after:bg-ink"></span>
                    Ține-mă minte
                </label>
                <Link v-if="canResetPassword" href="/forgot-password" class="text-sm font-semibold text-lime">
                    Ai uitat parola?
                </Link>
            </div>

            <button type="submit" :disabled="form.processing"
                    class="mt-2 h-14 w-full rounded-2xl bg-gradient-to-r from-lime to-[#7DE86B] text-base font-extrabold text-ink shadow-[0_12px_30px_-10px_rgba(184,243,74,0.7)] transition active:scale-[0.98] disabled:opacity-60">
                {{ form.processing ? 'Se conectează…' : 'Conectează-mă' }}
            </button>
        </form>

        <template #footer>
            Nu ai cont?
            <Link href="/register" class="font-bold text-lime">Creează unul</Link>
        </template>
    </AuthShell>
</template>
