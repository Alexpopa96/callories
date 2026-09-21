<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UpdateWater extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'delta' => ['required', 'integer', 'between:-5000,5000'],
        ]);

        $log = $request->user()->dailyLogs()->firstOrCreate(['date' => $data['date']]);
        $log->water_ml = max(0, min(20000, $log->water_ml + $data['delta']));
        $log->save();

        return back();
    }
}
