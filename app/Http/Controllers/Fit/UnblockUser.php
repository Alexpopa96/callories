<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UnblockUser extends Controller
{
    public function __invoke(Request $request, int $user): RedirectResponse
    {
        $request->user()->blocks()->where('blocked_id', $user)->firstOrFail()->delete();

        return back()->with('success', 'Utilizatorul a fost deblocat.');
    }
}
