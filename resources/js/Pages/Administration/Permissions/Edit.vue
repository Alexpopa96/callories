<template>
    <Layout title="Editare permisie" :breadcrumbs="['Administrare', 'Permisii', 'Editare permisie']">
        <Head title="Editare permisie" />
        <Title>Editeaza permisia</Title>
        <div class="">
            <Form @submit.prevent="update" :loading="sending">
                <div class="grid grid-cols-1 md:grid-cols-12 lg:grid-cols-12 gap-4 auto-cols-min">
                    <div class="md:col-span-6 lg:col-span-4">
                        <text-input v-model="form.name" :error="errors.name" label="Denumire permisie" />
                    </div>
                    <div class="md:col-span-6 lg:col-span-4">
                        <text-input v-model="form.group" :error="errors.group" label="Denumire grupa" />
                    </div>
                    <div class="md:col-span-6 lg:col-span-4">
                        <text-input v-model="form.parent" :error="errors.parent" label="Denumire parinte" />
                    </div>
                    <div class="md:col-span-6 lg:col-span-4">
                        <text-input v-model="form.guard_name" :error="errors.guard_name" label="Guard name" />
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
import Layout from '@/Layouts/Layout.vue';
import Form from "@/Components/Form.vue";
import {Head} from '@inertiajs/vue3'



export default {
    components: {
        Form,
        TextInput,
        SelectInput,
        TextareaInput,
        Title,
        Label,
        Head,
        Layout
    },

    props: {
        permission: Object,
        errors: Object,
        title: String,
    },
    remember: "form",

    data() {
        return {
            form: this.$inertia.form({
                _method: "put",
                name: this.permission.name,
                group: this.permission.group,
                parent: this.permission.parent,
                guard_name: this.permission.guard_name,

            }),
            sending: false,
            isDisabled: true,
            showfilter: false,
        }
    },

    methods: {
        update() {
            this.form.put(`/administration/permissions/${this.permission.id}/update`, {
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
                setTimeout(()=> this.sending = false, 10000)
            }
        }
    }
};
</script>
