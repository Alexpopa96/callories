<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StoreWeight extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'weight_kg' => ['required', 'numeric', 'between:25,400'],
        ]);

        $request->user()->weightLogs()->updateOrCreate(
            ['date' => $data['date']],
            ['weight_kg' => round((float) $data['weight_kg'], 1)],
        );

        return back()->with('success', 'Greutatea a fost salvată.');
    }
}
