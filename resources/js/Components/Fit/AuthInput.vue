<script setup>
import {computed, ref} from 'vue';
import {EyeIcon, EyeSlashIcon} from '@heroicons/vue/24/outline/index.js';

const props = defineProps({
    modelValue: {type: String, default: ''},
    type: {type: String, default: 'text'},
    label: {type: String, required: true},
    placeholder: {type: String, default: ''},
    error: {type: String, default: ''},
    autocomplete: {type: String, default: 'off'},
    inputmode: {type: String, default: undefined},
    autofocus: {type: Boolean, default: false},
});

defineEmits(['update:modelValue']);

const reveal = ref(false);
const inputType = computed(() => (props.type === 'password' && reveal.value ? 'text' : props.type));
</script>

<template>
    <label class="block">
        <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-white/50">{{ label }}</span>
        <span class="relative block">
            <input :value="modelValue" :type="inputType" :placeholder="placeholder" :autocomplete="autocomplete"
                   :inputmode="inputmode" :autofocus="autofocus"
                   class="h-14 w-full rounded-2xl border bg-white/5 px-4 text-base text-white placeholder:text-white/30 focus:border-lime focus:bg-white/[0.07] focus:ring-0"
                   :class="[error ? 'border-rose' : 'border-white/10', type === 'password' ? 'pr-12' : '']"
                   @input="$emit('update:modelValue', $event.target.value)"/>
            <button v-if="type === 'password'" type="button" tabindex="-1"
                    class="absolute inset-y-0 right-0 flex w-12 items-center justify-center text-white/45"
                    :aria-label="reveal ? 'Ascunde parola' : 'Arată parola'" @click="reveal = !reveal">
                <EyeSlashIcon v-if="reveal" class="size-5"/>
                <EyeIcon v-else class="size-5"/>
            </button>
        </span>
        <span v-if="error" class="mt-1.5 block text-sm text-rose">{{ error }}</span>
    </label>
</template>
