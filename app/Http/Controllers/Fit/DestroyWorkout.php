<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DestroyWorkout extends Controller
{
    public function __invoke(Request $request, int $workout): RedirectResponse
    {
        $request->user()->workouts()->findOrFail($workout)->delete();

        return back()->with('success', 'Antrenamentul a fost șters.');
    }
}
