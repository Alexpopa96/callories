<script setup>
import {MinusIcon, PlusIcon, StarIcon as StarOutline, TrashIcon} from '@heroicons/vue/24/outline/index.js';
import {StarIcon as StarSolid} from '@heroicons/vue/24/solid/index.js';

defineProps({
    items: {type: Array, required: true},
    favoriteNames: {type: Array, default: () => []},
    allowFavorite: {type: Boolean, default: false},
});

const emit = defineEmits(['grams', 'remove', 'favorite']);

const step = (item, index, factor) => emit('grams', index, Math.max(1, Math.round(item.portion_grams * factor)));

const round1 = (value) => Math.round(value * 10) / 10;

/** Bucăți afișate pentru porția curentă, calculate din raportul g/bucată stabilit pentru acest aliment. */
const piecesValue = (item) => (item._pieceRatio ? round1(item.portion_grams / item._pieceRatio) : '');

function setPieces(item, index, value) {
    const pieces = Number(value);
    if (!(pieces > 0)) return;

    if (item._pieceRatio === undefined) {
        // Prima completare doar stabilește raportul (ex: „100 g împărțite la 6 bucăți”), fără să schimbe cantitatea.
        item._pieceRatio = item.portion_grams / pieces;
        return;
    }

    emit('grams', index, Math.max(1, round1(pieces * item._pieceRatio)));
}

const stepPieces = (item, index, delta) => setPieces(item, index, Math.max(1, (piecesValue(item) || 0) + delta));
</script>

<template>
    <ul class="space-y-2">
        <li v-for="(item, index) in items" :key="index" class="rounded-2xl border border-white/5 bg-panel p-3.5">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="truncate font-bold leading-tight">{{ item.name }}</p>
                    <p class="mt-0.5 text-[11px] text-white/45">
                        P {{ item.protein_g }} · C {{ item.carbs_g }} · G {{ item.fat_g }} · F {{ item.fiber_g }}
                    </p>
                </div>
                <span class="shrink-0 text-sm font-extrabold">{{ Math.round(item.calories) }} kcal</span>
            </div>

            <div class="mt-2.5 flex items-center gap-2">
                <button type="button" aria-label="Mai puțin" class="flex size-11 items-center justify-center rounded-xl bg-white/5 text-white/70 active:scale-90"
                        @click="step(item, index, 0.9)">
                    <MinusIcon class="size-4"/>
                </button>
                <label class="flex h-11 items-center gap-1.5 rounded-xl bg-white/5 px-3">
                    <input :value="item.portion_grams" type="number" inputmode="decimal" min="0" max="5000"
                           aria-label="Porție în grame"
                           class="w-16 border-0 bg-transparent p-0 text-center text-base font-bold text-white focus:ring-0"
                           @change="emit('grams', index, $event.target.value)"/>
                    <span class="text-xs font-semibold text-white/45">g</span>
                </label>
                <button type="button" aria-label="Mai mult" class="flex size-11 items-center justify-center rounded-xl bg-white/5 text-white/70 active:scale-90"
                        @click="step(item, index, 1.1)">
                    <PlusIcon class="size-4"/>
                </button>

                <span class="flex-1"></span>

                <button v-if="allowFavorite" type="button" aria-label="Favorit"
                        class="flex size-11 items-center justify-center rounded-full transition active:scale-90"
                        :class="favoriteNames.includes(item.name) ? 'text-sun' : 'text-white/35'"
                        @click="emit('favorite', index)">
                    <StarSolid v-if="favoriteNames.includes(item.name)" class="size-5"/>
                    <StarOutline v-else class="size-5"/>
                </button>
                <button type="button" aria-label="Elimină" class="flex size-11 items-center justify-center rounded-full text-white/35 active:scale-90 active:text-rose"
                        @click="emit('remove', index)">
                    <TrashIcon class="size-5"/>
                </button>
            </div>

            <div class="mt-2 flex items-center gap-2">
                <template v-if="item._pieceRatio !== undefined">
                    <button type="button" aria-label="O bucată mai puțin" class="flex size-9 items-center justify-center rounded-xl bg-white/5 text-white/60 active:scale-90"
                            @click="stepPieces(item, index, -1)">
                        <MinusIcon class="size-3.5"/>
                    </button>
                    <label class="flex h-9 items-center gap-1.5 rounded-xl bg-white/5 px-2.5">
                        <input :value="piecesValue(item)" type="number" inputmode="decimal" min="0" step="1"
                               aria-label="Bucăți"
                               class="w-12 border-0 bg-transparent p-0 text-center text-sm font-bold text-white focus:ring-0"
                               @change="setPieces(item, index, $event.target.value)"/>
                        <span class="text-[11px] font-semibold text-white/45">buc</span>
                    </label>
                    <button type="button" aria-label="O bucată în plus" class="flex size-9 items-center justify-center rounded-xl bg-white/5 text-white/60 active:scale-90"
                            @click="stepPieces(item, index, 1)">
                        <PlusIcon class="size-3.5"/>
                    </button>
                    <span class="text-[11px] text-white/35">≈ {{ round1(item._pieceRatio) }} g/bucată</span>
                </template>
                <template v-else-if="item._piecesEditing">
                    <label class="flex h-9 items-center gap-1.5 rounded-xl bg-white/5 px-2.5">
                        <input type="number" inputmode="decimal" min="0" step="1" placeholder="ex: 6" autofocus
                               aria-label="Câte bucăți sunt în porția curentă"
                               class="w-14 border-0 bg-transparent p-0 text-center text-sm font-bold text-white placeholder:text-white/30 focus:ring-0"
                               @change="setPieces(item, index, $event.target.value)"/>
                        <span class="text-[11px] font-semibold text-white/45">buc</span>
                    </label>
                    <span class="text-[11px] text-white/35">câte bucăți sunt în cele {{ Math.round(item.portion_grams) }} g?</span>
                </template>
                <button v-else type="button" class="text-[11px] font-semibold text-white/40 underline underline-offset-2"
                        @click="item._piecesEditing = true">
                    + are bucăți (ex: felii, ouă)
                </button>
            </div>
        </li>
    </ul>
</template>
