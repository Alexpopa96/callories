<template>
    <div class="absolute w-full bg-gradient-to-tr from-sky-500 to-sky-900 dark:hidden min-h-75 overflow-hidden">
        <div id="wave">
            <svg width="100%" height="150px" fill="none">
                <path fill="white">
                    <animate
                        repeatCount="indefinite"
                        fill="freeze"
                        attributeName="d" dur="43200s"
                        values="
            M0 25.9086C277 84.5821 433 65.736 720 25.9086C934.818 -3.9019 1214.06 -5.23669 1442 8.06597C2079 45.2421 2208 63.5007 2560 25.9088V171.91L0 171.91V25.9086Z;
            M0 86.3149C316 86.315 444 159.155 884 51.1554C1324 -56.8446 1320.29 34.1214 1538 70.4063C1814 116.407 2156 188.408 2560 86.315V232.317L0 232.316V86.3149Z;
            M0 53.6584C158 11.0001 213 0 363 0C513 0 855.555 115.001 1154 115.001C1440 115.001 1626 -38.0004 2560 53.6585V199.66L0 199.66V53.6584Z;
            M0 25.9086C277 84.5821 433 65.736 720 25.9086C934.818 -3.9019 1214.06 -5.23669 1442 8.06597C2079 45.2421 2208 63.5007 2560 25.9088V171.91L0 171.91V25.9086Z">
                    </animate>
                </path>
            </svg>
        </div>
    </div>


    <!-- sidenav  -->
    <aside :class="expandSideBar ? 'translate-x-5' : ''"
           class="fixed inset-y-0  flex-wrap items-center justify-between block w-full p-0 my-4 overflow-y-auto antialiased transition-transform duration-200 -translate-x-full bg-white border-0 shadow-lg dark:shadow-none dark:bg-slate-850 max-w-64 ease-nav-brand z-990 xl:ml-6 rounded-2xl xl:left-0 xl:translate-x-0">
        <div class="h-19">
            <Link href="/" class="flex justify-center text-sm whitespace-nowrap dark:text-white text-slate-700" target="_blank">
                <img src="/assets/logo.png"
                     class="h-full max-w-full mt-5 transition-all duration-200 ease-nav-brand max-h-8"
                     alt="main_logo"/>
            </Link>
        </div>
        <hr class="h-px mt-0 bg-transparent bg-gradient-to-r from-transparent via-black/40 to-transparent dark:bg-gradient-to-r"/>
        <div class="items-center block w-auto max-h-screen overflow-auto grow basis-full ">
            <ul class="flex flex-col pl-0 mb-0 ">
                <MainMenu @closeSideBar="expandSideBar = false"/>
            </ul>
        </div>
        <div class="px-4 absolute bottom-5 w-full">
            <div>
                <div v-if="$page.props.impersonate" class="mb-2">
                    <div class="flex justify-center items-center">
                        <button @click.prevent="exitImpersonate"
                                class="bg-orange-500 px-4 py-1 hover:bg-orange-600 transition-all text-white font-extrabold rounded-lg flex justify-center items-center space-x-2">
                            <p>Iesi din impersonare</p>
                            <i class="fa-solid fa-arrow-right-from-bracket font-sm"></i>
                        </button>
                    </div>
                </div>
            </div>
            <Link href="/user/profile"
                  class="flex items-center justify-between w-full  rounded-xl">
                <div class="flex items-center gap-3">
                    <div class="shrink-0">
                        <img class="rounded-full w-12 h-12" :src="$page.props.auth.user.profile_photo_url"/>
                    </div>
                    <div>
                        <h4 class="text-md font-semibold">{{ $page.props.auth.user.name }}</h4>
                        <p class="text-gray-500 text-xs">{{ $page.props.auth.user.email }}</p>
                    </div>
                </div>
                <Link as="button" href="/logout" method="POST">
                    <ArrowRightStartOnRectangleIcon class="w-5 h-5 text-gray-600 text-red-500"/>
                </Link>
            </Link>
        </div>


    </aside>

    <main class="relative h-full max-h-screen transition-all duration-200 ease-in-out xl:ml-68 rounded-xl">
        <nav
            class="relative flex-wrap px-0 py-2 mx-6 transition-all ease-in shadow-none duration-250 rounded-2xl lg:flex-nowrap lg:justify-start"
            navbar-main navbar-scroll="false">
            <div class="flex items-center justify-end w-full sm:px-4 py-1 mx-auto flex-wrap-inherit">
                <nav>
                    <ol v-if="breadcrumbs && breadcrumbs.length > 1"
                        class="hidden md:flex flex-wrap items-center pt-1 bg-transparent rounded-lg sm:mr-16">
                        <li v-for="(breadcrumb, index) in breadcrumbs" :key="breadcrumb"
                            class="text-sm leading-normal text-white opacity-50 flex items-center">
                            {{ breadcrumb }}
                            <span v-if="index < breadcrumbs.length - 1" class="mx-2">/</span>
                        </li>
                    </ol>
                    <h6 v-if="title" class="mb-0 font-bold text-white capitalize">{{ title }}</h6>
                </nav>

                <div class="flex justify-end items-center mt-2 grow sm:mt-0 sm:mr-6 md:mr-0 lg:flex lg:basis-auto">
                    <ul>
                        <li class="xl:hidden">
                            <button type="button" @click="expandSideBar = !expandSideBar"
                                    :class="{'active': expandSideBar}"
                                    class="block p-0 text-sm text-white transition-all ease-nav-brand acetrnt-toggle">
                                <div class="overflow-hidden">
                                    <i class="ease mb-0.75 relative block h-0.5 rounded-sm bg-white transition-all line-1"></i>
                                    <i class="ease mb-0.75 relative block h-0.5 rounded-sm bg-white transition-all line-2"></i>
                                    <i class="ease relative block h-0.5 rounded-sm bg-white transition-all line-3"></i>
                                </div>
                            </button>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
        <div class="py-12">
            <div class="max-w-[120rem] mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-lg sm:rounded-3xl p-5">
                    <slot/>
                </div>
            </div>
        </div>
    </main>
</template>
<script>
import MainMenu from "@/Layouts/MainMenu.vue";
import {Link} from "@inertiajs/vue3";
import {ArrowRightStartOnRectangleIcon} from "@heroicons/vue/24/solid/index.js";

export default {
    components: {MainMenu, Link, ArrowRightStartOnRectangleIcon},

    props: {
        breadcrumbs: Array,
        title: String,
    },

    data() {
        return {
            expandSideBar: false,
        }
    },

    methods: {
        exitImpersonate() {
            this.$inertia.post('/exitimpersonate');
        }
    }

}
</script>
<style scoped>
body {
    background: blue;
}

.acetrnt-toggle {
    cursor: pointer;
    padding: 15px;
    position: relative;
}

.line-1, .line-2, .line-3 {
    cursor: pointer;
    border-radius: 4px;
    height: 2px;
    width: 20px;
    background: #ffffff;
    position: absolute;
    left: 0;
    transition: all 300ms ease-in-out;
}

.line-1 {
    top: 5px;
}

.line-2 {
    top: 10px;
}

.line-3 {
    top: 15px;
}

.acetrnt-toggle:hover span {
    background: #e7e7e7;
}

.acetrnt-toggle.active .line-2 {
    background-color: transparent;
}

.acetrnt-toggle.active .line-1 {
    transform: rotate(45deg);
    top: 10px;
}

.acetrnt-toggle.active .line-3 {
    transform: rotate(-45deg);
    top: 10px;
}

#wave {
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 150px;
    overflow: hidden;
}


</style>
