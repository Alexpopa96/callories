<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DestroyMeal extends Controller
{
    public function __invoke(Request $request, int $meal): RedirectResponse
    {
        $meal = $request->user()->meals()->findOrFail($meal);

        if ($meal->photo_path) {
            Storage::disk('public')->delete($meal->photo_path);
        }

        $meal->delete();

        return back()->with('success', 'Masa a fost ștearsă.');
    }
}
