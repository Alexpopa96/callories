<template>
    <OnClickOutside @trigger="onClose">
        <Label>{{ label }}</Label>
        <transition>
            <div ref="target" class="relative">
                <div class="overflow-hidden shadow-sm sm:rounded-lg relative">
                    <div class="relative flex justify-between items-center border border-gray-300 bg-white rounded-lg">
                        <div v-if="!multiple" class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <svg aria-hidden="true" class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </div>
                        <Combobox v-if="!multiple">
                            <div @click.prevent="enabledSearch = true; focusInput()" v-if="!enabledSearch" class="block w-full p-2 pl-10 text-sm text-gray-600">
                                {{ modelValue ? options.find(el => el.id == modelValue).name : placeholder}}
                            </div>
                            <ComboboxInput v-else :key="renderKey" @change="findItems" type="text" id="box" class="block w-full p-2 pl-10 text-sm text-gray-600  rounded-lg bg-white focus:outline-none focus:ring-0 outline-none ring-0 ring-offset-0" :placeholder="placeholder"/>
                        </Combobox>
                        <div v-else class="block w-full p-2 pl-3 text-sm text-gray-900">
                            <button @click.prevent="enabledSearch = true" v-if="modelValue.length === 0 && !enabledSearch" class="text-red-500">
                                <span>{{ placeholder }}</span>
                            </button>
                            <div v-if="modelValue.length > 0"  class="flex flex-wrap gap-2 items-center p-2 rounded-md">
                                <div v-for="item,index in options.filter(el => modelValue.includes(el.id))" :key="index" class="text-sm bg-sky-100 text-sky-700 border border-sky-400 font-semibold rounded-md px-2 flex space-x-4 items-center">
                                    <span>{{ item.name }}</span>
                                    <svg @click.prevent="select(item.id, item.name)" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 cursor-pointer text-gray-500 hover:text-gray-700">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </div>
                            </div>
                            <Combobox v-if="enabledSearch" class="mt-2">
                                <ComboboxInput :key="renderKey" @change="findItems" type="text" id="box" class="w-full text-sm text-gray-900 rounded-lg" :placeholder="placeholder"/>
                            </Combobox>
                        </div>
                        <div class="float-right">
                            <svg @click.prevent="toggleSearch" id="rotate" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 cursor-pointer text-gray-500 hover:text-gray-700">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                        </div>
                    </div>
                </div>
                <div v-if="error" class="mt-1 text-red-500 sm:text-[10px] md:text-sm">{{ error }}</div>
                <div class="w-full max-h-[300px] bg-white border border-gray-300 flex flex-col overflow-auto animate-fade-in-down absolute left-0 rounded-lg z-30" v-if="filtered.length > 0 && enabledSearch">
                    <ul v-if="!multiple" role="listbox">
                        <li @click.prevent="select(date.id)" :class="date.id == modelValue ? 'bg-sky-100 text-sky-700 hover:bg-red-100 hover:text-red-700' : 'bg-white hover:bg-sky-500 hover:text-white'" class="cursor-pointer text-left flex items-center space-x-2 px-4 py-2 sm:text-[11px] md:text-sm" v-for="date in filtered" :key="date.id" id="buton">
                            <span class="font-semibold">{{ date.name }}</span>
                        </li>
                    </ul>
                    <ul v-else role="listbox">
                        <li @click.prevent="select(date.id)" :class="modelValue.includes(date.id) ? 'bg-sky-100 text-sky-700 hover:bg-red-100 hover:text-red-700' : 'bg-white hover:bg-sky-500 hover:text-white'" class="cursor-pointer text-left flex items-center space-x-2 px-4 py-2 sm:text-[11px] md:text-sm" v-for="date in filtered" :key="date.id" id="buton">
                            <Checkbox :checked="modelValue.includes(date.id)" disabled :id="`checked${date.id}`"/>
                            <span class="font-semibold">{{ date.name }}</span>
                        </li>
                    </ul>
                </div>
                <div v-else-if="enabledSearch && filtered.length == 0" class="text-center p-2 text-gray-500 w-full max-h-[300px] bg-white border border-gray-300 flex flex-col overflow-auto animate-fade-in-down absolute left-0 rounded-lg z-30">
                    {{ result }}
                </div>
            </div>
        </transition>
    </OnClickOutside>
</template>

<script>
import {
    Combobox,
    ComboboxInput,
} from '@headlessui/vue';
import { OnClickOutside } from '@vueuse/components'
import Label from '@/Components/Label.vue';
import Checkbox from '@/Components/Checkbox.vue';

export default {
    components: {
        Combobox,ComboboxInput,OnClickOutside,Label,Checkbox
    },

    props: {
        options: Object,
        modelValue: Number|Object,
        multiple: {
            type: Boolean,
            default: false
        },
        placeholder: String,
        error: {
            type: String,
            default: null
        },
        label: {
            type: String,
            default: null
        },
        result: {
            type: String,
            default: "Nu exista inregistrari"
        }
    },
    data() {
        return {
            renderKey: 1,
            enabledSearch: false,
            selected: this.modelValue,
            filtered: this.options,
        }
    },

    methods: {
        toggleSearch() {
            if(this.modelValue) {
                this.enabledSearch = !this.enabledSearch;
                this.focusInput();
                this.rotate();
                return;
            }
            this.enabledSearch = !this.enabledSearch;
            this.focusInput();
            this.rotate();
        },
        rotate() {
            let el = document.getElementById('rotate');
            el.style.transform = this.enabledSearch ? 'rotate(45deg)' : 'rotate(0deg)';
        },
        select(id) {
            if(!this.multiple) {
                if(id == this.modelValue) {
                    this.renderKey++;
                    this.$emit('update:modelValue', null);
                    this.enabledSearch = false;
                    return;
                }
                this.renderKey++;
                this.enabledSearch = false;
                this.$emit('update:modelValue', id);
                this.filtered = this.options;
            } else {
                this.selectMultiple(id);
                this.$emit('update:modelValue', this.selected);
            }
        },
        selectMultiple(id) {
            if(this.selected.includes(id)) {
                let idx = this.selected.findIndex(el => el == id);
                this.selected.splice(idx,1);
                return;
            }
            this.selected.push(id);
            this.filtered = this.options;
            this.renderKey++;

        },
        findItems(event) {
            this.filtered = this.options.filter((date) => {
                return this.removeDiacritics(date.name).toLowerCase().includes(event.target.value.toLowerCase())
            });
        },
        remove() {
            if(this.multiple) {
                this.selected = [];
            }
            this.filtered = this.options;
            this.renderKey++;
            this.enabledSearch = true
            this.$emit('update:modelValue', null);
        },
        removeDiacritics(str) {
            return str.normalize("NFD").replace(/[\u0300-\u036f]/g, "");
        },
        async focusInput() {
            if(this.enabledSearch) {
                await new Promise(r => setTimeout(r,200));
                let input = document.getElementById('box');
                input.focus();
            }
        },
        onClose() {
            this.enabledSearch = false;
        },
    },
    watch: {
        options: {
            deep: true,
            handler() {
                this.filtered = this.options;
            }
        }
    }
}

</script>

