<template>
    <div :class="modelValue ? 'bg-green-600' : 'bg-gray-300'" @click="toggle" class="cursor-pointer w-12 transition-all duration-500 h-6 flex items-center rounded-full p-1">
        <div :class="{'translate-x-6' : modelValue }" class="w-4 h-4 transition-all duration-200 transform bg-white rounded-full shadow-md">
            <svg v-if="!modelValue" class="h-4 w-4 text-black" fill="none" viewBox="0 0 12 12">
                <path d="M4 8l2-2m0 0l2-2M6 6L4 4m2 2l2 2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            <svg v-else="modelValue" class="h-4 w-4 text-green-600" fill="currentColor" viewBox="0 0 12 12">
                <path d="M3.707 5.293a1 1 0 00-1.414 1.414l1.414-1.414zM5 8l-.707.707a1 1 0 001.414 0L5 8zm4.707-3.293a1 1 0 00-1.414-1.414l1.414 1.414zm-7.414 2l2 2 1.414-1.414-2-2-1.414 1.414zm3.414 2l4-4-1.414-1.414-4 4 1.414 1.414z" />
            </svg>
        </div>
    </div>
</template>
<script setup>


const props = defineProps({
    modelValue:{
        type: Boolean
    }
});

const emit = defineEmits(['update:modelValue']);

function toggle(){
    emit('update:modelValue', !props.modelValue);
}
</script>
