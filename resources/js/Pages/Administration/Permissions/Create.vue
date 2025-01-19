<template>
    <Layout title="Adaugare permisii" :breadcrumbs="['Administrare', 'Permisii', 'Adaugare permisii']">
        <Head title="Adaugare permisii" />
        <Title>Adauga o permisie</Title>
        <div class="">
            <form  @submit.prevent="store" :loading="sending" >
                <div v-for="(form,index) in forms" :key="index" class="relative rounded animate__animated animate__fadeIn animate__faster shadow-md bg-gray-100 align-middle p-6 mt-4 grid grid-cols-1 md:grid-cols-6 lg:grid-cols-12 gap-4 auto-cols-min">
                    <span class="col-span-12">* - Camp obligatoriu </span>
                    <span class="col-span-12 font-black m-auto text-xl">Formular #{{index+1}}</span>
                    <div class="md:col-span-6 lg:col-span-6">
                        <text-input v-model="form.name" label="Denumire permisie *" />
                    </div>
                    <div class="md:col-span-6 lg:col-span-6">
                        <text-input v-model="form.group" label="Denumire grupa" />
                    </div>
                    <div class="md:col-span-6 lg:col-span-6">
                        <text-input v-model="form.parent" value=" " label="Denumire parinte" />
                    </div>
                    <div class="md:col-span-6 lg:col-span-6">
                        <text-input v-model="form.guard_name" label="Guard name *" />
                    </div>
                    <button v-if="forms.length > 1" @click.prevent="deleteForm(index)"
                            class="px-4 py-2 border bg-red-500 border-transparent shadow-sm leading-4 text-white  font-bold rounded-md focus:outline-none">
                        <div class="inline-flex items-center">
                            <i class="fas fa-minus"></i>
                        </div>
                    </button>
                </div>
                <div class="flex justify-between mt-4">
                    <button @click.prevent="addForm"
                            class="px-4 py-2 border bg-green-500 border-transparent shadow-sm leading-4 text-white  font-bold rounded-md focus:outline-none">
                        <div class="inline-flex items-center">
                            <i class="fa-solid fa-plus"></i>
                        </div>
                    </button>
                    <button @click.prevent="store"
                            class="px-4 py-2 border bg-green-500 border-transparent shadow-sm leading-4 text-white  font-bold rounded-md focus:outline-none">
                        <div class="inline-flex items-center">
                            Salveaza
                        </div>
                    </button>
                </div>
            </form>
            <div v-if="counter > 0 && alert" class="animate__animated animate__fadeIn animate__faster mt-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                <strong class="font-bold">Au aparut erori in forumarele {{arr.toString()}} ! Verificati ca toate datele necesare sa fie introduse</strong>
                <span class="absolute top-0 bottom-0 right-0 px-4 py-3">
					<svg @click.prevent="alert = false" class="fill-current h-6 w-6 text-red-500" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/></svg>
				</span>
            </div>
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
        roles: Array,
        errors: Object,
        permissions: Array
    },

    data() {
        return {
            form: this.$inertia.form({
                name: null,
                group: ' ',
                parent: null,
                guard_name: null
            }),
            sending: false,
            isDisabled: true,
            forms:[{
                name: '',
                group: '',
                parent: '',
                guard_name: ''
            }],
            counter: 0,
            alert: false,
            arr: []
        }
    },

    methods: {
        store() {
            this.arr = [];
            this.counter = 0;
            this.check();
            if(this.counter == 0 ){
                for(let i=0;i<this.forms.length;i++){
                    this.$inertia.post(('store'), this.forms[i], {
                        inline: 'default',
                        onStart: () => this.sending = true,
                        onSuccess: () => {
                            this.$toast.success(this.$page.props.toast.success.message);
                            this.sending = false;
                        },
                    })
                }
            }

        },
        check(){
            for(let i=0;i<this.forms.length;i++){
                if(this.forms[i].name == '' || this.forms[i].guard_name == ''){
                    this.arr.push(i+1);
                    this.counter++;
                    this.alert = true;
                }
            }
        },
        addForm(){
            this.forms.push({
                name: "",
                group: this.forms[0].group,
                parent: "",
                guard_name: this.forms[0].guard_name
            });
        },
        deleteForm(index){
            this.forms.splice(index,1)
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
