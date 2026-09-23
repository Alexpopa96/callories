<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DestroyApiKey extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $request->user()->forceFill(['anthropic_api_key' => null])->save();

        return back();
    }
}
