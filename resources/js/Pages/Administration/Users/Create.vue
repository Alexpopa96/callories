<template>
    <Layout title="Adaugare utilizator" :breadcrumbs="['Administrare', 'Utilizatori', 'Adaugare utilizator']">
        <Head title="Adaugare utilizator" />
        <Title>Adauga utilizator</Title>
        <div>
            <Form @submit.prevent="store" :loading="sending" >
                <div class="grid grid-cols-1 md:grid-cols-6 lg:grid-cols-12 gap-4 auto-cols-min">
                    <div class="md:col-span-1 lg:col-span-4">
                        <text-input v-model="form.name" :error="errors.name" label="Nume" />
                    </div>
                    <div class="md:col-span-1 lg:col-span-3">
                        <text-input v-model="form.email" :error="errors.email" label="Email" />
                    </div>
                    <div class="md:col-span-1 lg:col-span-2">
                        <text-input v-model="form.phone" :error="errors.phone" label="Telefon" />
                    </div>
                    <div class="md:col-span-1 lg:col-span-2">
                        <Label>Rol</Label>
                        <select-input v-model="form.role_id" >
                            <option :value="null">Alege</option>
                            <option v-for="role in roles" :key="role.id" :value="role.id">{{role.name}}</option>
                        </select-input>
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
import { Head } from '@inertiajs/vue3'

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
    },

    data() {
        return {
            form: this.$inertia.form({
                email: null,
                name: null,
                phone: null,
                obs: null,
                role_id: null,
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
                setTimeout(()=> this.sending = false, 2000)
            }
        }
    }
};
</script>
