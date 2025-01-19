<?php

namespace App\Http\Controllers\Administration\Roles;

use Inertia\Inertia;
use App\Http\Controllers\Controller;
use Spatie\Permission\Models\Permission;

class Create extends Controller
{
    public function __invoke()
    {

        return Inertia::render('Administration/Roles/Create');
    }
}
