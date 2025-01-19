<?php

namespace App\Http\Controllers\Administration\Permissions;

use Spatie\Permission\Models\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Redirect;

class Store extends Controller
{
    public function __invoke()
    {
        Request::validate([
            'name' => ['required'],
            'group' => ['nullable'],
            'parent' => ['nullable'],
            'guard_name' => ['required']
        ], [
            'required' => 'Campul este obligatoriu!'
        ]);

        $permission = Permission::create([
            'name' => Request::get('name'),
            'group' => Request::get('group'),
            'parent' => Request::get('parent'),
            'guard_name' => Request::get('guard_name'),
        ]);

        return Redirect::to('/administration/permissions/')->with(['success'=> ['message'=> 'Permisia a fost creata cu succes!']]);
    }
}

