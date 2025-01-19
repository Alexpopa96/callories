<template>
    <div :class="$attrs.class" class="flex flex-col">
        <label v-if="label" class="block text-sm font-medium dark:text-textInputDark text-gray-800" :for="id">{{ label }}</label>
        <textarea :id="id" ref="input" v-bind="{ ...$attrs, class: null }" class="w-full pr-3 sm:py-1 md:py-2 rounded-lg dark:text-textInputDark dark:bg-inputDark dark:border-borderInputDark border-gray-200 focus:outline-none focus:ring-0 focus:border-primaryColor sm:text-[11px] md:text-sm" :class="{ error: error }" :value="modelValue" @input="$emit('update:modelValue', $event.target.value)" />
        <div v-if="error" class="mt-1 text-red-500 sm:text-[10px] sm:text-sm lg:text-sm">{{ error }}</div>
    </div>
</template>

<script>
import { v4 as uuid } from 'uuid'

export default {
    inheritAttrs: false,
    props: {
        id: {
            type: String,
            default() {
                return `textarea-input-${uuid()}`
            },
        },
        error: String,
        label: String,
        modelValue: String,
    },
    emits: ['update:modelValue'],
    methods: {
        focus() {
            this.$refs.input.focus()
        },
        select() {
            this.$refs.input.select()
        },
    },
}
</script>

