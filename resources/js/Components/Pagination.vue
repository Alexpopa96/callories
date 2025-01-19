<template>
    <div class="flex justify-between items-center" v-if="links.length > 2">
        <div class="">
            <p class="sm:text-[10px] md:text-sm text-gray-700">
                De la
                <span class="font-medium">{{ name.from }}</span>
                la
                <span class="font-medium">{{ name.to }}</span>
                din
                <span class="font-medium">{{ name.total }}</span>
                inregistrari
            </p>
        </div>
        <div class="sm:m-auto md:m-0 flex items-center justify-center">
            <Link v-if="links[0].url" :href="links[0].url"
                  class="bg-sky-500 text-white rounded-full sm:text-[8px] md:text-sm inline-flex items-center justify-center sm:w-6 md:w-8 sm:h-6 md:h-8">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                     stroke="currentColor" class="w-6 h-6">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M19.5 12h-15m0 0l6.75 6.75M4.5 12l6.75-6.75"/>
                </svg>
            </Link>
            <nav class="isolate inline-flex items-center space-x-2 rounded-md shadow-sm p-2 rounded-full"
                 aria-label="Pagination">
                <template v-for="(link,key) in links" :key="key">
                    <Link v-if="key > 0 && key < links.length - 1" :href="link.url" aria-current="page"
                          :class="link.active ? 'bg-sky-500 text-white shadow-none' : 'bg-white'"
                          class="relative sm:w-6 md:w-8 sm:h-6 md:h-8 z-10 inline-flex items-center justify-center leading-4 border border-gray-300 px-2 py-2 sm:text-[8px] md:text-sm font-medium shadow-md focus:z-20 rounded-full"
                          v-html="link.label"></Link>
                </template>
            </nav>
            <Link v-if="links[links.length - 1].url" :href="links[links.length-1].url"
                  class="bg-sky-500 text-white rounded-full sm:text-[8px] md:text-sm inline-flex items-center justify-center sm:w-6 md:w-8 sm:h-6 md:h-8">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                     stroke="currentColor" class="w-6 h-6">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M4.5 12h15m0 0l-6.75-6.75M19.5 12l-6.75 6.75"/>
                </svg>
            </Link>
        </div>
    </div>
</template>

<script>
import {Link} from '@inertiajs/vue3'

export default {
    components: {
        Link,
    },
    props: {
        links: Array,
        name: Object
    },
}
</script>
