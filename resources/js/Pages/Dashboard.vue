<script setup>
import Welcome from '@/Components/Welcome.vue';
import Layout from "@/Layouts/Layout.vue";
import {Head} from "@inertiajs/vue3";
</script>

<template>
    <Head title="Dashboard"/>
    <Layout :breadcrumbs="['Dashboard']" title="Dashboard">

        <div class="max-w-lg mx-auto bg-white rounded-lg p-6">
            <h1 class="text-2xl font-bold text-gray-800 mb-4">User Logged In</h1>

            <div class="flex items-center space-x-4">
                <!-- Imagine de profil -->
                <img
                    :src="$page.props.auth.user.profile_photo_url"
                    alt="User Avatar"
                    class="w-16 h-16 rounded-full border-2 border-gray-300"
                />

                <div>
                    <p class="text-lg font-semibold text-gray-700">{{ $page.props.auth.user.name }}</p>
                    <p class="text-gray-500">{{ $page.props.auth.user.email }}</p>
                </div>
            </div>
        </div>

    </Layout>
</template>
