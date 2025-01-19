<template>
    <Layout title="Adaugare rol" :breadcrumbs="['Administrare', 'Roluri', 'Adaugare rol']">
        <Head title="Adaugare rol"/>
        <div>
            <Title>Adauga un rol</Title>
            <Form @submit.prevent="store" :loading="sending" >
                <div class="grid grid-cols-1 md:grid-cols-6 lg:grid-cols-12 gap-4 auto-cols-min">
                    <div class="md:col-span-1 lg:col-span-6">
                        <text-input v-model="form.name" :error="errors.name" label="Denumire rol"/>
                    </div>
                    <div class="md:col-span-1 lg:col-span-6">
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
import Layout from "@/Layouts/Layout.vue";
import Form from "@/Components/Form.vue";
import {Head, Link} from '@inertiajs/vue3'

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
        roles: Array,
        errors: Object,
        permissions: Array
    },

    data() {
        return {
            form: this.$inertia.form({
                name: null,
                guard_name: null,
                permissionId: null,
            }),
            sending: false,
            isDisabled: true,
        }
    },

    methods: {
        store() {
            this.$inertia.post(('store'), this.form, {
                inline: 'default',
                onStart: () => this.sending = true,
                onSuccess: () => {
                    this.$toast.success(this.$page.props.toast.success.message);
                    this.sending = false;
                },
            })
        },
    },
    watch: {
        sending: {
            deep:true,
            handler() {
                setTimeout(()=> this.sending = false, 1000)
            }
        }
    }
};
</script>
