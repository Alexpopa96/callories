<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StoreWorkout extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'title' => ['required', 'string', 'max:120'],
            'exercises' => ['required', 'array', 'min:1', 'max:10'],
            'exercises.*.name' => ['required', 'string', 'max:120'],
            'exercises.*.sets' => ['required', 'integer', 'min:1', 'max:20'],
            'exercises.*.reps' => ['required', 'string', 'max:30'],
            'exercises.*.notes' => ['nullable', 'string', 'max:300'],
        ]);

        $request->user()->workouts()->create([
            'date' => $data['date'],
            'title' => $data['title'],
            'exercises' => $data['exercises'],
        ]);

        return back()->with('success', 'Antrenamentul a fost salvat.');
    }
}
