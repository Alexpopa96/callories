<script setup>
import Welcome from '@/Components/Welcome.vue';
import Layout from "@/Layouts/Layout.vue";
import {Head} from "@inertiajs/vue3";
</script>

<template>
    <Head title="Dashboard"/>
    <Layout :breadcrumbs="['Dashboard']" title="Dashboard">

        <Welcome/>

    </Layout>
</template>
