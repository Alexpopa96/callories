<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UpdateExercise extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'calories' => ['required', 'integer', 'between:0,10000'],
        ]);

        $request->user()->dailyLogs()->updateOrCreate(
            ['date' => $data['date']],
            ['exercise_calories' => $data['calories']],
        );

        return back();
    }
}
