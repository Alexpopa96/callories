<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UpdateReminders extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'meals' => ['required', 'boolean'],
            'water' => ['required', 'boolean'],
            'calorieLimit' => ['nullable', 'boolean'],
            'challenge' => ['nullable', 'boolean'],
        ]);

        $request->user()->update([
            'remind_meals' => $data['meals'],
            'remind_water' => $data['water'],
            'remind_calorie_limit' => $data['calorieLimit'] ?? false,
            'remind_challenge' => $data['challenge'] ?? false,
        ]);

        return back();
    }
}
