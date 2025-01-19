<template>
    <form action="#" method="POST">
        <div class="shadow sm:rounded-md border dark:border-gray-600">
            <div class="space-y-6 bg-gray-50 dark:bg-tableDark px-4 py-5 p-2 md:p-4">
                <slot></slot>
            </div>
            <div v-if="buttons" class="dark:bg-layoutDark bg-gray-100 px-2 py-3 text-right sm:px-6 flex justify-between">
                <Link href="" @click="back"
                      class="cursor-pointer inline-flex items-center px-4 py-2 border border-transparent shadow-sm leading-4 font-bold rounded-md text-white bg-gray-400 hover:bg-gray-500 focus:outline-none">
                    Inapoi
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5"
                         stroke="currentColor" class="w-5 h-5 ml-2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" />
                    </svg>
                </Link>
                <button v-if="save" type="submit" :disabled="loading"
                        class="px-4 py-2 flex space-x-2 items-center border bg-primaryColor hover:bg-hoverColor border-transparent shadow-sm leading-4 text-white font-bold rounded-md focus:outline-none">
                    <svg v-if="!loading" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                         stroke-width="2.5" stroke="currentColor" class="w-5 h-5 mr-3">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <spinner class="mr-3" v-if="loading" />
                    Salveaza
                </button>
            </div>
        </div>
    </form>
</template>

<script>
import { Link } from '@inertiajs/vue3';
import Spinner from '@/Components/Spinner.vue';

export default {

    components: {
        Link,
        Spinner
    },
    props: {
        type: {
            type: String,
            default: 'submit',
        },
        buttons: {
            type: Boolean,
            default: true,
        },
        save: {
            type: Boolean,
            default: true,
        },
        loading: Boolean

    },
    methods: {
        back() {
            window.history.back();
        }
    }
}
</script>

