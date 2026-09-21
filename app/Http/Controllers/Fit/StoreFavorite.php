<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StoreFavorite extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'portion_grams' => ['required', 'numeric', 'min:0', 'max:5000'],
            'calories' => ['required', 'numeric', 'min:0', 'max:5000'],
            'protein_g' => ['required', 'numeric', 'min:0', 'max:500'],
            'carbs_g' => ['required', 'numeric', 'min:0', 'max:1000'],
            'fat_g' => ['required', 'numeric', 'min:0', 'max:500'],
            'fiber_g' => ['required', 'numeric', 'min:0', 'max:200'],
        ]);

        $user = $request->user();

        if ($user->favoriteFoods()->count() >= 100 && ! $user->favoriteFoods()->where('name', $data['name'])->exists()) {
            return back()->with('error', 'Poți salva maxim 100 de favorite.');
        }

        $user->favoriteFoods()->updateOrCreate(
            ['name' => $data['name']],
            collect($data)->except('name')->map(fn ($value) => round((float) $value, 1))->all(),
        );

        return back()->with('success', 'Salvat la favorite.');
    }
}
