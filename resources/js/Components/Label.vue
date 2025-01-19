<script setup>
defineProps(['value']);
</script>

<template>
    <label class="form-label block text-sm font-medium leading-5 dark:text-textInputDark text-gray-800">
        <span v-if="value">{{ value }}</span>
        <span v-else><slot /></span>
    </label>
</template>
