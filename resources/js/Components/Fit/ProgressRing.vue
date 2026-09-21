<script setup>
import {computed} from 'vue';

const props = defineProps({
    value: {type: Number, default: 0},
    max: {type: Number, default: 1},
    size: {type: Number, default: 120},
    stroke: {type: Number, default: 10},
    color: {type: String, default: '#B8F34A'},
    warnOver: {type: Boolean, default: false},
});

const radius = computed(() => (props.size - props.stroke) / 2);
const circumference = computed(() => 2 * Math.PI * radius.value);
const ratio = computed(() => (props.max > 0 ? Math.min(props.value / props.max, 1) : 0));
const stroke = computed(() => (props.warnOver && props.value > props.max ? '#FF5D8F' : props.color));
</script>

<template>
    <div class="relative shrink-0" :style="{width: `${size}px`, height: `${size}px`}">
        <svg :width="size" :height="size" class="-rotate-90">
            <circle :cx="size / 2" :cy="size / 2" :r="radius" fill="none" stroke="rgba(255,255,255,0.08)"
                    :stroke-width="stroke"/>
            <circle :cx="size / 2" :cy="size / 2" :r="radius" fill="none" :stroke="stroke" :stroke-width="stroke"
                    stroke-linecap="round" :stroke-dasharray="circumference"
                    :stroke-dashoffset="circumference * (1 - ratio)"
                    class="transition-[stroke-dashoffset] duration-700 ease-out"/>
        </svg>
        <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
            <slot/>
        </div>
    </div>
</template>
