<template>
    <Layout title="Editare rol" :breadcrumbs="['Administrare', 'Roluri', 'Editare rol']">
        <Head title="Editare rol" />
        <Title>Editare rol</Title>
        <div>
            <Form @submit.prevent="update" :loading="sending">
                <div class="grid grid-cols-1 md:grid-cols-12 lg:grid-cols-12 gap-4 auto-cols-min">
                    <div class="md:col-span-6 lg:col-span-4">
                        <text-input v-model="form.name" :error="errors.name" label="Denumire rol" />
                    </div>
                    <div class="md:col-span-6 lg:col-span-4">
                        <text-input v-model="form.guard_name" :error="errors.guard_name" label="Guard name" />
                    </div>
                </div>
                <div class="rounded-md p-4 mt-6 shadow-md text-sm dark:bg-layoutDark bg-white border dark:border-borderInputDark">
                    <div class="md:col-span-1 lg:col-span-12">
                        <div class="text-center mb-4">
                            Permisii
                        </div>
                    </div>
                    <div class="flex space-x-2 overflow-x-auto">
                        <div v-for="group in permissionGroups"
                             class="dark:text-textInputDark sp-1 rounded-md">
                            <div class="text-center">
                                {{ group }}
                            </div>
                            <div v-for="permission in permissions" :key="permission.id"
                                 class="">
                                <div v-if="permission.group == group"
                                     class="flex items-center m-1 dark:bg-tableDark bg-white border-2 dark:border-borderInputDark border-primaryColor px-2 py-1 rounded-md shadow-md text-hoverColor font-bold">
                                    <input class="rounded-full mr-2 font-bold border-gray-300 text-ps3purple shadow-sm focus:outline-none focus:ring-0"
                                           type="checkbox"
                                           :id="'permission-'+permission.id"
                                           :value="permission.id"
                                           v-model="form.selected">
                                    <label :for="'permission-'+permission.id"
                                           class="cursor-pointer">
                                        {{ permission.name }}
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- <div class="flex flex-wrap">
                        <div v-for="permission in permissions" :key="permission.id"
                            class="">
                            <div>
                                <input type="checkbox" :id="'permission-'+permission.id" :value="permission.id" v-model="form.selected">
                                <label :for="'permission-'+permission.id">
                                    {{ permission.name }}
                                </label>
                            </div>
                        </div>
                    </div> -->
                    <!-- <Accordion title="Administrare">
                        <Accordion v-for="(admin,index) in admins" :key="index" :title="admin">
                            <div v-for="(permission) in permissions" :key="permission.id" class="mt-4 text-center sm:w-full md:w-full lg:w-1/2 m-auto shadow-md rounded">
                                <div v-show="permission.group === admin" class="flex text-white justify-between p-4 hover:shadow-lg hover:shadow-blue-300/50 sm:p-2">
                                    <label class="switch">
                                        <input type="checkbox" :value="permission.id" v-model="form.selected">
                                        <div class="slider round"></div>
                                    </label>

                                    <span :class="showFirstWord(permission.name) == 'view' ? 'bg-blue-400 p-2 rounded'
                                        : showFirstWord(permission.name) == 'edit' ? 'bg-green-400 p-2 rounded'
                                        : showFirstWord(permission.name) == 'create' ? 'bg-yellow-400 p-2 rounded'
                                        : showFirstWord(permission.name) == 'show' ? 'bg-gray-400 p-2 rounded' : ''" style="width:4rem;">
                                        {{showFirstWord(permission.name).toUpperCase()}}
                                    </span>

                                </div>
                            </div>
                        </Accordion>
                    </Accordion> -->
                    <!-- <Accordion v-for="(group,index) in permissionGroups" :key="index" :title="group">
                        <div v-for="(permission) in permissions" :key="permission.id" class="mt-4 text-center lg:w-1/2 m-auto shadow-md rounded">
                            <div v-show="permission.group === group" class="text-center text-white flex justify-between px-8 hover:shadow-lg p-4 sm:p-2">
                                <label class="switch">
                                    <input type="checkbox" :value="permission.id" v-model="form.selected">
                                    <div class="slider round"></div>
                                </label>

                                <span :class="showFirstWord(permission.name) == 'view' ? 'bg-green-400 p-2 rounded'
                                    : showFirstWord(permission.name) == 'edit' ? 'bg-blue-400 p-2 rounded'
                                    : showFirstWord(permission.name) == 'create' ? 'bg-yellow-400 p-2 rounded'
                                    : showFirstWord(permission.name) == 'show' ? 'bg-gray-400 p-2 rounded' : ''" style="width:4rem;">

                                    {{showFirstWord(permission.name).toUpperCase()}}
                                </span>
                            </div>
                        </div>
                    </Accordion>         -->
                </div>
            </Form>
        </div>
    </Layout>
</template>

<script>
import Layout from '@/Layouts/Layout.vue';
import Label from '@/Components/Label.vue'
import Title from '@/Components/Title.vue'
import TextInput from '@/Components/TextInput.vue'
import SelectInput from '@/Components/SelectInput.vue'
import TextareaInput from '@/Components/TextareaInput.vue'
import Form from "@/Components/Form.vue";
import {Head} from '@inertiajs/vue3'
import VueMultiselect from "vue-multiselect";
import Checkbox from "@/Components/Checkbox.vue";


export default {
    components: {
        Form,
        TextInput,
        SelectInput,
        TextareaInput,
        Title,
        Label,
        Head,
        VueMultiselect,
        Checkbox,
        Layout
    },

    computed: {
    },

    props: {
        role: Object,
        permissions: Object,
        permissionGroups: Object,
        errors: Object,
        date: Array,
        filtersLabel: Array,
        options: Object,
        title: String,
        permissionsAdmin: Object,
        admins: Object,
        parents: Object,
        clients: Object,
    },
    remember: "form",
    data() {
        return {
            form: this.$inertia.form({
                _method: "put",
                name: this.role.name,
                guard_name: this.role.guard_name,
                selected: this.date

            }),
            sending: false,
            isDisabled: true,
            show: false,
            showfilter: false,
        }
    },

    methods: {
        update() {
            this.form.put(`/administration/roles/${this.role.id}/update`, {
                inline: 'default',
                onStart: () => this.sending = true,
                onSuccess: () => {
                    this.$toast.success(this.$page.props.toast.success.message);
                    this.sending = false;
                },
            });
        },
        showPermissions(){
            this.show = !this.show;
        },
        hide(){
            this.show = false;
        },
        showFilters(){
            this.showfilter = !this.showfilter;
        },
        onlyUnique(value, index, self){
            return self.indexOf(value) === index;
        },
        showFirstWord(str){
            return str.split(' ').shift();
        }
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

<style>
.switch {
    position: relative;
    display: inline-block;
    width: 90px;
    height: 34px;
}

.switch input {display:none;}

.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ca2222;
    -webkit-transition: .4s;
    transition: .4s;
    border-radius: 34px;
}

.slider:before {
    position: absolute;
    content: "";
    height: 26px;
    width: 26px;
    left: 4px;
    bottom: 4px;
    background-color: white;
    -webkit-transition: .4s;
    transition: .4s;
    border-radius: 50%;
}

input:checked + .slider {
    background-color: #2ab934;
}

input:focus + .slider {
    box-shadow: 0 0 1px #2196F3;
}

input:checked + .slider:before {
    -webkit-transform: translateX(26px);
    -ms-transform: translateX(26px);
    transform: translateX(55px);
}

/*------ ADDED CSS ---------*/
.slider:after
{
    content:'OFF';
    color: white;
    display: block;
    position: absolute;
    transform: translate(-50%,-50%);
    top: 50%;
    left: 50%;
    font-size: 10px;
    font-family: Verdana, sans-serif;
}

input:checked + .slider:after
{
    content:'ON';
}

</style>
