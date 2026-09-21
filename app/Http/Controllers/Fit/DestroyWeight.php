<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DestroyWeight extends Controller
{
    public function __invoke(Request $request, int $weight): RedirectResponse
    {
        $request->user()->weightLogs()->findOrFail($weight)->delete();

        return back();
    }
}
