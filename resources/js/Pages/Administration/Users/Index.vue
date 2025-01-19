<template>
    <Layout title="Utilizatori" :breadcrumbs="['Administrare', 'Utilizatori']">
        <Head title="Utilizatori" />
        <div class="sm:rounded-lg animate__animated animate__fadeIn">
            <div class="mt-4">
                <section>
                    <div>
                        <div class="flex items-center gap-x-3">
                            <h2 class="text-lg font-medium text-gray-800 dark:text-white">Utilizatori</h2>
                            <span class="px-3 py-1 text-xs text-blue-600 dark:bg-textTableDark bg-blue-200 rounded-full">{{
                                    totalUsers }}</span>
                        </div>
                    </div>
                    <hr class="mt-2 border border-gray-200">
                    <div class="relative inline-block text-left w-14 mt-2">
                        <select v-model.number="params.perPage"
                                class="w-full form-control rounded-lg dark:bg-tableDark dark:text-textInputDark dark:border-borderInputDark border border-gray-300 shadow-sm px-2 py-2 bg-white text-sm font-medium text-gray-700 focus:outline-none focus:placeholder-gray-400 focus:ring-0 focus:border-gray-600">
                            <option :value="10" class="dark:bg-tableDark dark:text-textTableDark">10</option>
                            <option :value="15" class="dark:bg-tableDark dark:text-textTableDark">15</option>
                            <option :value="25" class="dark:bg-tableDark dark:text-textTableDark">25</option>
                        </select>
                    </div>
                    <div class="mt-4 md:flex sm:space-y-4 md:space-y-0 md:items-center md:justify-between flex-wrap">
                        <div class="relative flex items-center mt-4 md:mt-0">
							<span class="absolute">
								<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                     stroke="currentColor" class="w-5 h-5 mx-3 text-gray-400 dark:text-textTableDark">
									<path stroke-linecap="round" stroke-linejoin="round"
                                          d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
								</svg>
							</span>
                            <input type="text" v-model="params.search" placeholder="Cauta utilizator..."
                                   class="block w-full py-1.5 pr-5 dark:text-textTableDark text-gray-700 bg-white border border-gray-200 rounded-lg md:w-80 placeholder-gray-400/70 pl-11 rtl:pr-11 rtl:pl-5 dark:bg-tableDark placeholder:dark:text-textTableDark dark:border-gray-600 focus:border-blue-400 dark:focus:border-blue-300 focus:ring-blue-300 focus:outline-none focus:ring focus:ring-opacity-40">
                        </div>
                        <div
                            class="inline-flex overflow-hidden bg-white border divide-x rounded-lg dark:bg-gray-900 rtl:flex-row-reverse dark:border-gray-700 dark:divide-gray-700">
                            <button @click.prevent="params.status = null"
                                    :class="params.status === null ? 'bg-gray-100' : ''"
                                    class="px-3 py-1 text-xs font-medium text-gray-600 transition-colors duration-200 sm:text-sm dark:bg-gray-800 dark:text-gray-200">
                                Toti
                            </button>
                            <button @click.prevent="params.status = 1" :class="params.status === 1 ? 'bg-gray-100' : ''"
                                    class="px-3 py-1 text-xs font-medium text-gray-600 transition-colors duration-200 sm:text-sm dark:hover:bg-gray-800 dark:text-gray-200 hover:bg-gray-100">
                                Activi
                            </button>
                            <button @click.prevent="params.status = 2" :class="params.status === 2 ? 'bg-gray-100' : ''"
                                    class="px-3 py-1 text-xs font-medium text-gray-600 transition-colors duration-200 sm:text-sm dark:hover:bg-gray-800 dark:text-gray-200 hover:bg-gray-100">
                                Inactivi
                            </button>
                        </div>
                        <div class="flex items-center gap-x-3">
                            <button @click.prevent="exportExcel"
                                    class="flex items-center justify-center w-1/2 px-5 py-2 text-sm text-gray-700 transition-colors duration-200 bg-white border rounded-lg gap-x-2 sm:w-auto dark:hover:bg-gray-600 dark:bg-tableDark hover:bg-gray-100 dark:text-gray-200 dark:border-gray-700">
                                <Spinner v-if="exporting" />
                                <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                     stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M12 9.75v6.75m0 0l-3-3m3 3l3-3m-8.25 6a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.233-2.33 3 3 0 013.758 3.848A3.752 3.752 0 0118 19.5H6.75z" />
                                </svg>
                                <span>Export</span>
                            </button>
                            <Link href="/administration/users/create"
                                  class="flex items-center justify-center w-1/2 px-5 py-2 text-sm tracking-wide text-white transition-colors duration-200 bg-blue-500 rounded-lg shrink-0 sm:w-auto gap-x-2 hover:bg-blue-600 dark:hover:bg-blue-500 dark:bg-blue-600">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                     stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M12 9v6m3-3H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span>Adauga utilizator</span>
                            </Link>
                        </div>
                    </div>
                    <div class="flex flex-col mt-4">
                        <div class="-my-2 overflow-x-auto ">
                            <div class="inline-block min-w-full py-2 align-middle">
                                <div class="overflow-hidden border border-gray-200 dark:border-gray-700 md:rounded-lg">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead class="bg-gray-50 dark:bg-tableDark">
                                        <tr>
                                            <th scope="col"
                                                class="py-3.5 text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-textTableDark">
                                                <Heading multi-column :direction="filters.direction"
                                                         :selected="filters.field === 'name'" @removeSort="removeSort"
                                                         @sort="sort('name')">
                                                    Nume
                                                </Heading>
                                            </th>
                                            <th scope="col"
                                                class="text-left px-4 text-sm leading-4 dark:text-textTableDark text-gray-700 font-bold tracking-wider">
                                                Status
                                            </th>
                                            <th scope="col"
                                                class="text-left px-4 text-sm leading-4 dark:text-textTableDark text-gray-700 font-bold tracking-wider">
                                                Email
                                            </th>
                                            <th scope="col"
                                                class="text-left px-4 text-sm leading-4 dark:text-textTableDark text-gray-700 font-bold tracking-wider">
                                                Telefon
                                            </th>
                                            <th scope="col"
                                                class="text-left px-4 text-sm leading-4 dark:text-textTableDark text-gray-700 font-bold tracking-wider">
                                                Rol
                                            </th>
                                            <th scope="col"
                                                class="px-4 text-right text-sm leading-4 dark:text-textTableDark text-gray-700 font-bold tracking-wider">
                                                Actiuni
                                            </th>
                                        </tr>
                                        </thead>
                                        <tbody
                                            class="bg-white divide-y divide-gray-200 dark:divide-gray-700 dark:bg-tableDark">
                                        <tr v-if="users.data.length === 0">
                                            <td colspan="6">
                                                <div
                                                    class="dark:text-textTableDark text-gray-600 text-center py-2 text-sm">
                                                    Nu au fost gasite inregistrari
                                                </div>
                                            </td>
                                        </tr>
                                        <tr class="hover:bg-gray-100 hover:shadow-md" v-else v-for="user in users.data"
                                            :key="user.id">
                                            <td class="px-4 py-3 text-sm font-medium whitespace-nowrap">
                                                <div class="flex space-x-4 items-center">
                                                    <i :class="cacheUsers.includes(user.id) ? 'fas fa-circle text-green-500' : 'fas fa-circle text-red-500'"
                                                       class="text-md"></i>
                                                    <button v-if="$page.props.auth.user.roles[0].id == 1"
                                                            @click.prevent="impersonate(user.id)"
                                                            class="hover:underline font-medium text-gray-800 dark:text-textTableDark">{{
                                                            user.name }}</button>
                                                    <button v-else
                                                            class="hover:underline font-medium text-gray-800 dark:text-textTableDark">{{
                                                            user.name }}</button>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-sm font-medium whitespace-nowrap text-left">
                                                <div :class="user.status == 1 ? 'dark:bg-green-700 dark:text-textTableDark bg-green-100 text-green-500' : 'dark:bg-red-700 dark:text-textTableDark bg-red-100 text-red-500'"
                                                     class="inline px-3 py-1 text-sm text-left font-normal rounded-full">
                                                    {{ user.status == 1 ? 'Activ' : 'Inactiv' }}
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-sm whitespace-nowrap text-left">
                                                <div>
                                                    <h4 class="text-gray-700 dark:text-textTableDark">{{ user.email }}
                                                    </h4>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-sm whitespace-nowrap text-left">
                                                <div class="">
                                                    <h4 class="text-gray-700 dark:text-textTableDark">{{ user.phone ?
                                                        user.phone : '-' }}</h4>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-sm whitespace-nowrap text-left">
                                                <div class="">
                                                    <h4 class="text-gray-700 dark:text-textTableDark">{{ user.role }}</h4>
                                                </div>
                                            </td>
                                            <td class="py-3 text-sm whitespace-nowrap relative text-right">
                                                <div class="flex space-x-2 justify-end text-right px-2">
                                                    <Link :href="`/administration/users/${user.id}/edit`">
                                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none"
                                                             viewBox="0 0 24 24" stroke-width="1.5"
                                                             class="dark:stroke-textTableDark stroke-blue-500 w-6 h-6">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                  d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                                        </svg>
                                                    </Link>
                                                    <button>
                                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none"
                                                             viewBox="0 0 24 24" stroke-width="1.5" stroke="red"
                                                             class="w-6 h-6">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                  d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
                <div class="p-4">
                    <pagination :name="users" :links="users.links" v-if="users.total > perPage" />
                </div>
            </div>
        </div>
    </Layout>
</template>

<script>
import Layout from '@/Layouts/Layout.vue';
import Heading from '@/Components/Table/Heading.vue'
import Title from '@/Components/Title.vue';
import Pagination from "@/Components/Pagination.vue"
import { Head, Link } from '@inertiajs/vue3'
import pickBy from "lodash/pickBy";
import throttle from "lodash/throttle";
import axiosLink from '@/axiosLink';
import Spinner from '@/Components/Spinner.vue';

export default {
    components: {
        Head, Link, Pagination, Heading, Title, Spinner, Layout
    },
    data() {
        return {
            params: {
                search: this.filters.search,
                field: this.filters.field,
                direction: this.filters.direction,
                perPage: this.perPage,
                status: this.filters.status
            },
            isEditable: null,
            exporting: false
        };
    },

    props: {
        users: Object,
        filters: Object,
        perPage: Number,
        cacheUsers: Array,
        totalUsers: Number
    },

    methods: {
        sort(field) {
            this.params.field = field;
            this.params.direction = this.params.direction === 'asc' ? 'desc' : 'asc';
        },
        removeSort() {
            this.filters.direction = null;
            this.filters.field = null;
            this.params.direction = null;
            this.params.field = null;

        },
        async exportExcel() {
            this.exporting = true;
            await axiosLink('/administration/users/export', 'users.xlsx');
            this.exporting = false;
        },
        impersonate(id) {
            this.$inertia.post('/impersonate/' + id, { id: id }, {

            });
        }
    },
    watch: {
        params: {
            deep: true,
            handler: throttle(function () {
                let query = pickBy(this.params)
                this.$inertia.get('users', Object.keys(query).length ? query : { remember: 'forget' }, { preserveState: true })
            }, 150),
        },
    },
};
</script>
