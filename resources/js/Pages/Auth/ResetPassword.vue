<script setup>
import {Head, Link, useForm} from '@inertiajs/vue3';
import AuthShell from '@/Components/Fit/AuthShell.vue';
import AuthInput from '@/Components/Fit/AuthInput.vue';

const props = defineProps({
    email: String,
    token: String,
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('password.update'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Parolă nouă"/>
    <AuthShell title="Alege o parolă nouă" subtitle="Setează parola cu care te vei conecta de acum.">
        <form class="space-y-4" @submit.prevent="submit">
            <AuthInput v-model="form.email" type="email" label="Email" autocomplete="username"
                       :error="form.errors.email"/>
            <AuthInput v-model="form.password" type="password" label="Parola nouă" placeholder="Minim 8 caractere"
                       autocomplete="new-password" :error="form.errors.password" autofocus/>
            <AuthInput v-model="form.password_confirmation" type="password" label="Confirmă parola"
                       placeholder="Repetă parola" autocomplete="new-password"
                       :error="form.errors.password_confirmation"/>

            <button type="submit" :disabled="form.processing"
                    class="mt-2 h-14 w-full rounded-2xl bg-gradient-to-r from-lime to-[#7DE86B] text-base font-extrabold text-ink shadow-[0_12px_30px_-10px_rgba(184,243,74,0.7)] transition active:scale-[0.98] disabled:opacity-60">
                {{ form.processing ? 'Se salvează…' : 'Salvează parola' }}
            </button>
        </form>

        <template #footer>
            <Link href="/login" class="font-bold text-lime">Înapoi la conectare</Link>
        </template>
    </AuthShell>
</template>
