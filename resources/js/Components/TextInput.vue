<template>
    <div :class="$attrs.class">
        <label v-if="label" class="block text-sm font-medium dark:text-textInputDark text-gray-800" :for="id">{{ label }}</label>
        <input :id="id" ref="input" v-bind="{ ...$attrs, class: null }" class="w-full pr-3 sm:py-1 md:py-2 rounded-lg dark:text-textInputDark dark:bg-inputDark dark:border-borderInputDark border-gray-200 focus:outline-none focus:ring-0 focus:border-primaryColor sm:text-[11px] md:text-sm"
               :class="[error ? ' @apply border-red-500' : ''] " :type="type" :placeholder="placeholder"
               :value="modelValue" @input="$emit('update:modelValue', $event.target.value)">
        <slot/>
        <div v-if="error" class="mt-1 text-red-500 sm:text-[10px] sm:text-sm lg:text-sm">{{ error }}</div>
    </div>
</template>

<script>
export default {
    props: {
        id: {
            type: String,
            default() {
                return `select-input-${Math.random() * 1000}`;
            },
        },
        type: {
            type: String,
            default: 'text',
        },
        modelValue: String|Number,
        label: String,
        error: String,
        placeholder: {
            type: String,
            default: ''
        }
    },
    methods: {
        focus() {
            this.$refs.input.focus()
        },
        select() {
            this.$refs.input.select()
        },
        setSelectionRange(start, end) {
            this.$refs.input.setSelectionRange(start, end)
        },
    },
}
</script>
