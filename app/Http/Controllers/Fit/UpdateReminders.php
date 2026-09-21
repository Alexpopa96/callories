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
        ]);

        $request->user()->update(['remind_meals' => $data['meals'], 'remind_water' => $data['water']]);

        return back();
    }
}
