<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UpdateGoals extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'calories' => ['required', 'integer', 'between:800,6000'],
            'steps' => ['required', 'integer', 'between:1000,50000'],
            'waterMl' => ['required', 'integer', 'between:500,8000'],
        ]);

        $request->user()->update([
            'calorie_goal' => $data['calories'],
            'steps_goal' => $data['steps'],
            'water_goal_ml' => $data['waterMl'],
        ]);

        return back()->with('success', 'Obiectivele au fost salvate.');
    }
}
