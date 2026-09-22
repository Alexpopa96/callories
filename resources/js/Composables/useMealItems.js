import {computed, ref} from 'vue';

const KEYS = ['calories', 'protein_g', 'carbs_g', 'fat_g', 'fiber_g'];
const round1 = (value) => Math.round(value * 10) / 10;

/**
 * Keeps the values for the original portion in `_base`, so changing the grams
 * always rescales from it and repeated edits never drift.
 */
export function toEditable(item) {
    const base = {grams: Number(item.portion_grams) || 0};
    KEYS.forEach((key) => (base[key] = Number(item[key]) || 0));

    return {
        name: item.name,
        portion_grams: base.grams,
        ...Object.fromEntries(KEYS.map((key) => [key, base[key]])),
        _base: base,
    };
}

export function rescale(item, grams) {
    const next = Math.min(5000, Math.max(0, Number(grams) || 0));
    const factor = item._base.grams > 0 ? next / item._base.grams : 1;

    item.portion_grams = next;

    if (item._base.grams > 0) {
        item.calories = Math.round(item._base.calories * factor);
        ['protein_g', 'carbs_g', 'fat_g', 'fiber_g'].forEach((key) => (item[key] = round1(item._base[key] * factor)));
    }
}

export function toPayload(item) {
    return {
        name: item.name,
        portion_grams: item.portion_grams,
        calories: item.calories,
        protein_g: item.protein_g,
        carbs_g: item.carbs_g,
        fat_g: item.fat_g,
        fiber_g: item.fiber_g,
    };
}

export function useMealItems(initial = []) {
    const items = ref(initial.map(toEditable));

    const totals = computed(() => {
        const sum = (key) => items.value.reduce((acc, item) => acc + Number(item[key] || 0), 0);

        return {
            calories: Math.round(sum('calories')),
            protein: round1(sum('protein_g')),
            carbs: round1(sum('carbs_g')),
            fat: round1(sum('fat_g')),
            fiber: round1(sum('fiber_g')),
        };
    });

    return {
        items,
        totals,
        set: (list) => (items.value = list.map(toEditable)),
        add: (item) => {
            const editable = toEditable(item);
            items.value.push(editable);
            return editable;
        },
        remove: (index) => items.value.splice(index, 1),
        removeItems: (refs) => (items.value = items.value.filter((item) => !refs.includes(item))),
        setGrams: (index, grams) => rescale(items.value[index], grams),
        payload: () => items.value.map(toPayload),
    };
}
