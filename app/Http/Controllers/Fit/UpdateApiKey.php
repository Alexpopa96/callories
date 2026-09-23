<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UpdateApiKey extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'apiKey' => ['required', 'string', 'starts_with:sk-ant-', 'max:255'],
        ], [
            'apiKey.required' => 'Introdu cheia API.',
            'apiKey.starts_with' => 'Cheia API Anthropic începe cu „sk-ant-”.',
        ]);

        $request->user()->forceFill(['anthropic_api_key' => trim($data['apiKey'])])->save();

        return back();
    }
}
