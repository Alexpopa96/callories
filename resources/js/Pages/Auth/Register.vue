<script setup>
import {Head, Link, useForm} from '@inertiajs/vue3';
import AuthShell from '@/Components/Fit/AuthShell.vue';
import AuthInput from '@/Components/Fit/AuthInput.vue';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    terms: true,
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Creare cont"/>
    <AuthShell title="Începe azi" subtitle="Creează-ți contul și urmărește ce mănânci, câți pași faci și cât bei.">
        <form class="space-y-4" @submit.prevent="submit">
            <AuthInput v-model="form.name" label="Nume" placeholder="Cum te cheamă?" autocomplete="name"
                       :error="form.errors.name" autofocus/>
            <AuthInput v-model="form.email" type="email" label="Email" placeholder="nume@exemplu.ro"
                       autocomplete="username" inputmode="email" :error="form.errors.email"/>
            <AuthInput v-model="form.password" type="password" label="Parola" placeholder="Minim 8 caractere"
                       autocomplete="new-password" :error="form.errors.password"/>
            <AuthInput v-model="form.password_confirmation" type="password" label="Confirmă parola"
                       placeholder="Repetă parola" autocomplete="new-password"
                       :error="form.errors.password_confirmation"/>

            <button type="submit" :disabled="form.processing"
                    class="mt-2 h-14 w-full rounded-2xl bg-gradient-to-r from-lime to-[#7DE86B] text-base font-extrabold text-ink shadow-[0_12px_30px_-10px_rgba(184,243,74,0.7)] transition active:scale-[0.98] disabled:opacity-60">
                {{ form.processing ? 'Se creează…' : 'Creează cont' }}
            </button>
        </form>

        <template #footer>
            Ai deja cont?
            <Link href="/login" class="font-bold text-lime">Conectează-te</Link>
        </template>
    </AuthShell>
</template>
