<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import DeleteUserForm from '@/Pages/Profile/Partials/DeleteUserForm.vue';
import LogoutOtherBrowserSessionsForm from '@/Pages/Profile/Partials/LogoutOtherBrowserSessionsForm.vue';
import SectionBorder from '@/Components/SectionBorder.vue';
import TwoFactorAuthenticationForm from '@/Pages/Profile/Partials/TwoFactorAuthenticationForm.vue';
import UpdatePasswordForm from '@/Pages/Profile/Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from '@/Pages/Profile/Partials/UpdateProfileInformationForm.vue';
import Layout from "@/Layouts/Layout.vue";

defineProps({
    confirmsTwoFactorAuthentication: Boolean,
    sessions: Array,
});
</script>

<template>
    <Layout title="Informatii profil" :breadcrumbs="['Setari','Profil']">
        <div v-if="$page.props.jetstream.canUpdateProfileInformation">
            <UpdateProfileInformationForm :user="$page.props.auth.user"/>

            <SectionBorder/>
        </div>

        <div v-if="$page.props.jetstream.canUpdatePassword">
            <UpdatePasswordForm class="mt-10 sm:mt-0"/>

            <SectionBorder/>
        </div>

        <div v-if="$page.props.jetstream.canManageTwoFactorAuthentication">
            <TwoFactorAuthenticationForm
                :requires-confirmation="confirmsTwoFactorAuthentication"
                class="mt-10 sm:mt-0"
            />

            <SectionBorder/>
        </div>

        <LogoutOtherBrowserSessionsForm :sessions="sessions" class="mt-10 sm:mt-0"/>

        <div v-if="$page.props.jetstream.hasAccountDeletionFeatures">
            <SectionBorder/>

            <DeleteUserForm class="mt-10 sm:mt-0"/>
        </div>
    </Layout>
</template>
