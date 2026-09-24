import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import Toast from "vue-toastification";
import { useToast } from "vue-toastification";
import 'animate.css';
import { isNativeApp, listenForNotificationTaps } from './Composables/useNativeReminders';
import { Keyboard } from '@capacitor/keyboard';
import "vue-toastification/dist/index.css";
import "vue-multiselect/dist/vue-multiselect.css";

listenForNotificationTaps();

// Hide the ˄ ˅ ✓ bar iOS shows above the keyboard in web views.
if (isNativeApp()) Keyboard.setAccessoryBarVisible({ isVisible: false });

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        const inertiaApp =   createApp({render: () => h(App, props)})
            .use(plugin)
            .use(Toast)
            .component('useToast', useToast)
            .use(ZiggyVue);
            inertiaApp.config.globalProperties.$toast = useToast();
            inertiaApp.mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
