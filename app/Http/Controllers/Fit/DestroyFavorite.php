<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DestroyFavorite extends Controller
{
    public function __invoke(Request $request, int $favorite): RedirectResponse
    {
        $request->user()->favoriteFoods()->findOrFail($favorite)->delete();

        return back();
    }
}
