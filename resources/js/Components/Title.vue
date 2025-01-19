<template>
    <div class="py-2 ">
        <h1 :class="$attrs.class" class="text-lg font-medium text-gray-800 dark:text-white">{{title}} <slot></slot> </h1>
    </div>
</template>
<script>
export default {
    props: {
        title: String
    }
}
</script>
