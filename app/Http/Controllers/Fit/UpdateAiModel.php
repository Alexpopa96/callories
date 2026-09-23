<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use App\Services\Anthropic\Models;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UpdateAiModel extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'model' => ['required', Rule::in(array_keys(Models::options()))],
        ], [
            'model.in' => 'Alege un model din listă.',
        ]);

        $request->user()->forceFill(['anthropic_model' => $data['model']])->save();

        return back();
    }
}
