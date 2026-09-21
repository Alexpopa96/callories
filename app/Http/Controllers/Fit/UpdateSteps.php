<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UpdateSteps extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'steps' => ['required', 'integer', 'between:0,200000'],
        ]);

        $request->user()->dailyLogs()->updateOrCreate(
            ['date' => $data['date']],
            ['steps' => $data['steps']],
        );

        return back();
    }
}
