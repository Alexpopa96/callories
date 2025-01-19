<template>
    <div :class="$attrs.class">
        <label v-if="label" class="block text-sm leading-5 dark:text-textInputDark text-gray-800" :for="id">{{ label }}</label>
        <select :id="id" ref="input" v-model="selected" v-bind="{ ...$attrs, class: null }" class="w-full dark:bg-inputDark border border-gray-200 dark:text-textInputDark dark:border-borderInputDark focus:outline-none focus:border-primaryColor focus:ring-1 focus:ring-primaryColor block transition duration-150 ease-in-out text-sm sm:leading-5 rounded-lg" :class="[error ? '@apply border-red-500' : '']">
            <slot/>
        </select>
        <div v-if="error" class="mt-1 text-red-500 sm:text-[10px] md:text-sm lg:text-sm">{{ error }}</div>
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
                return `select-input-${uuid()}`
            },
        },
        error: String,
        label: String,
        modelValue: [String, Number, Boolean],

    },
    emits: ['update:modelValue'],
    data() {
        return {
            selected: this.modelValue,
        }
    },
    watch: {
        selected(selected) {
            this.$emit('update:modelValue', selected)
        },
    },
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

<style>

select {
    -webkit-appearance: none;
    -moz-appearance: none;
    -ms-appearance: none;
    appearance: none;
    outline: 0;
    box-sizing: none;
    box-shadow:none;
    border-color:#d1d5db;
    background: white;
    background-image: none;
    color:black;
    font-weight: normal;
    border-radius:3px !important;
}
select:focus {
    border-color:black !important;
}
select option {
    background-color: white;
    color:black;
    font-weight: normal;
    border:2px solid black;
}
select option:hover {
    text-decoration: green !important;
}


</style>
