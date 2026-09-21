<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class Profile extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Fit/Profile', [
            'goals' => $user->goals(),
            'canAdmin' => $user->can('view dashboard'),
        ]);
    }
}
