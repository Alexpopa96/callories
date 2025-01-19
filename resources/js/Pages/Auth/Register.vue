<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    terms: false,
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Register"/>
    <section>
        <div
            class="bg-top relative flex items-start pt-16 pb-56 m-4 overflow-hidden bg-cover min-h-screen md:min-h-[50vh] rounded-xl bg-[url('https://raw.githubusercontent.com/creativetimofficial/public-assets/master/argon-dashboard-pro/assets/img/signup-cover.jpg')]">
            <span
                class="absolute top-0 left-0 w-full h-full bg-center bg-cover bg-gradient-to-tl from-zinc-800 to-zinc-700 opacity-60"></span>
            <div class="container z-10">
                <div class="flex flex-wrap justify-center -mx-3">
                    <div class="w-full max-w-full px-3 mx-auto mt-0 text-center lg:flex-0 shrink-0 lg:w-5/12">
                        <h1 class="mt-12 mb-2 text-white text-4xl font-semibold">Welcome!</h1>
                        <p class="text-white text-lg font-light">Use these awesome forms to login or create a new
                            account in your project for free.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Section -->
        <div class="container">
            <div class="flex flex-wrap -mx-3 -mt-48 md:-mt-56 lg:-mt-48">
                <div class="w-full max-w-full px-3 mx-auto mt-0 md:flex-0 shrink-0 md:w-7/12 lg:w-5/12 xl:w-4/12">
                    <div
                        class="relative z-0 flex flex-col min-w-0 break-words bg-white border-0 shadow-xl rounded-3xl bg-clip-border">
                        <div class="flex justify-center items-center">
                            <img src="/assets/logo.png" class="w-64 mt-10"/>
                        </div>

                        <div class="px-6 mt-5 mb-0 text-center bg-white border-b-0 rounded-t-3xl">
                            <h5 class="text-2xl font-bold text-gray-700">Creare cont</h5>
                        </div>

                        <div class="flex-auto p-6">
                            <form @submit.prevent="submit" class="space-y-6">
                                <div>
                                    <input v-model="form.name" type="text"
                                           :class="{'border-red-500': form.errors.name}"
                                           class="w-full p-4 text-lg bg-gray-100 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none transition-all"
                                           placeholder="Nume"/>
                                    <InputError class="mt-2 text-sm text-red-500" :message="form.errors.name"/>
                                </div>

                                <div>
                                    <input v-model="form.email" type="email"
                                           :class="{'border-red-500': form.errors.email}"
                                           class="w-full p-4 text-lg bg-gray-100 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none transition-all"
                                           placeholder="Email"/>
                                    <InputError class="mt-2 text-sm text-red-500" :message="form.errors.email"/>
                                </div>

                                <div>
                                    <input v-model="form.password" type="password"
                                           :class="{'border-red-500': form.errors.password}"
                                           class="w-full p-4 text-lg bg-gray-100 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none transition-all"
                                           placeholder="Parola"/>
                                    <InputError class="mt-2 text-sm text-red-500" :message="form.errors.password"/>
                                </div>

                                <div>
                                    <input v-model="form.password_confirmation" type="password"
                                           :class="{'border-red-500': form.errors.password_confirmation}"
                                           class="w-full p-4 text-lg bg-gray-100 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none transition-all"
                                           placeholder="Confirmare parola"/>
                                    <InputError class="mt-2 text-sm text-red-500"
                                                :message="form.errors.password_confirmation"/>
                                </div>

                                <div v-if="$page.props.jetstream.hasTermsAndPrivacyPolicyFeature"
                                     class="flex items-center space-x-2">
                                    <input type="checkbox" id="terms"
                                           class="w-6 h-6 text-blue-600 rounded-md focus:ring-0 transition-all"
                                           checked/>
                                    <label for="terms" class="text-sm text-gray-600">
                                        Sunt de acord cu <a href="javascript:;" class="text-blue-500 font-bold">Termenii
                                        și Condițiile</a>
                                    </label>
                                    <InputError class="mt-2 text-sm text-red-500" :message="form.errors.terms"/>
                                </div>

                                <div class="text-center">
                                    <button type="submit"
                                            class="w-full py-3 bg-gradient-to-r from-blue-500 to-indigo-500 text-white font-bold rounded-xl shadow-lg hover:bg-indigo-600 focus:ring-4 focus:ring-indigo-300 transition-all ease-in-out">
                                        Inregistreaza-ma
                                    </button>
                                </div>

                                <p class="text-center text-sm text-gray-600 mt-4">Ai deja un cont?
                                    <Link href="/login"
                                          class="font-semibold text-transparent bg-clip-text bg-gradient-to-tl from-blue-500 to-sky-500">
                                        Conecteaza-ma
                                    </Link>
                                </p>
                            </form>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
