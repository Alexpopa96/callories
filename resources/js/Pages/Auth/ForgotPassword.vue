<script setup>
import {Head, Link, useForm} from '@inertiajs/vue3';
import AuthShell from '@/Components/Fit/AuthShell.vue';
import AuthInput from '@/Components/Fit/AuthInput.vue';

defineProps({
    status: String,
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <Head title="Parolă uitată"/>
    <AuthShell title="Parolă uitată?" subtitle="Îți trimitem un link pentru a alege o parolă nouă.">
        <p v-if="status" class="mb-4 rounded-2xl bg-lime/10 px-4 py-3 text-sm font-medium text-lime">{{ status }}</p>

        <form class="space-y-4" @submit.prevent="submit">
            <AuthInput v-model="form.email" type="email" label="Email" placeholder="nume@exemplu.ro"
                       autocomplete="username" inputmode="email" :error="form.errors.email" autofocus/>

            <button type="submit" :disabled="form.processing"
                    class="mt-2 h-14 w-full rounded-2xl bg-gradient-to-r from-lime to-[#7DE86B] text-base font-extrabold text-ink shadow-[0_12px_30px_-10px_rgba(184,243,74,0.7)] transition active:scale-[0.98] disabled:opacity-60">
                {{ form.processing ? 'Se trimite…' : 'Trimite linkul' }}
            </button>
        </form>

        <template #footer>
            <Link href="/login" class="font-bold text-lime">Înapoi la conectare</Link>
        </template>
    </AuthShell>
</template>
