<template>
    <Layout title="Editare utilizator" :breadcrumbs="['Administration', 'Utilizatori', 'Editare utilizator']">
        <Head title="Editare utilizator" />
        <div>
            <Title>Editeaza utilizator</Title>
            <Form @submit.prevent="update" :loading="sending">
                <div class="grid grid-cols-1 md:grid-cols-6 lg:grid-cols-12 gap-4 auto-cols-min">
                    <div class="md:col-span-1 lg:col-span-4">
                        <text-input v-model="form.name" :error="errors.name" label="Nume" />
                    </div>
                    <div class="md:col-span-1 lg:col-span-4">
                        <text-input v-model="form.email" :error="errors.email" label="Email" />
                    </div>
                    <div class="md:col-span-1 lg:col-span-2">
                        <text-input v-model="form.phone" :error="errors.phone" label="Telefon" />
                    </div>
                    <div class="md:col-span-1 lg:col-span-2">
                        <select-input v-model="form.role_id" :error="form.errors.role_id" label="Rol">
                            <option :value="null">Alege</option>
                            <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option>
                        </select-input>
                    </div>
                    <div class="col-span-1">
                        <label class="block text-sm font-medium dark:text-textInputDark text-gray-800"
                               for="switcher">Activ</label>
                        <Switcher id="switcher" v-model="form.status" />
                    </div>
                    <div class="md:col-span-1 lg:col-span-12">
                        <textarea-input v-model="form.obs" :error="errors.obs" label="Observatii" />
                    </div>
                </div>
            </Form>
        </div>
    </Layout>
</template>

<script>
import Label from '@/Components/Label.vue'
import Title from '@/Components/Title.vue'
import TextInput from '@/Components/TextInput.vue'
import SelectInput from '@/Components/SelectInput.vue'
import TextareaInput from '@/Components/TextareaInput.vue'
import Layout from "@/Layouts/Layout.vue";
import Form from "@/Components/Form.vue";
import { Head } from '@inertiajs/vue3';
import Switcher from '@/Components/Switcher.vue';

export default {
    components: {
        Form,
        TextInput,
        SelectInput,
        TextareaInput,
        Title,
        Label,
        Head,
        Switcher,
        Layout
    },

    props: {
        roles: Array,
        user: Object,
        errors: Object,
    },

    remember: "form",

    data() {
        return {
            form: this.$inertia.form({
                _method: "put",
                name: this.user.name,
                email: this.user.email,
                phone: this.user.phone,
                obs: this.user.obs,
                role_id: this.user.role_id,
                status: this.user.status,
                containers_id: this.user.containers,
            }),
            sending: false,
            isDisabled: true,
        }
    },

    methods: {
        update() {
            this.form.put(`/administration/users/${this.user.id}/update`, {
                inline: 'default',
                onStart: () => this.sending = true,
                onSuccess: () => {
                    this.$toast.success(this.$page.props.toast.success.message);
                    this.sending = false;
                },
            });
        },
    },
    watch: {
        sending: {
            deep: true,
            handler() {
                setTimeout(() => this.sending = false, 1000)
            }
        }
    }
};
</script>
