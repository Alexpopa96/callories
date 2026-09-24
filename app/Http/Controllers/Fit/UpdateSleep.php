<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UpdateSleep extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'minutes' => ['required', 'integer', 'between:0,1440'],
        ]);

        $request->user()->dailyLogs()->updateOrCreate(
            ['date' => $data['date']],
            ['sleep_minutes' => $data['minutes']],
        );

        return back();
    }
}
