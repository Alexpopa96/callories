<?php

namespace App\Http\Controllers\Administration\Permissions;

use Inertia\Inertia;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Redirect;

class Update extends Controller
{
    public function __invoke(Permission $permission)
    {
        Request::validate([
            'name' => ['required'],
            'group' => ['nullable'],
            'parent' => ['nullable'],
            'guard_name' => ['required'],
        ]);

        $permission->update([
            'name' => Request::get('name'),
            'group' => Request::get('group'),
            'parent' => Request::get('parent'),
            'guard_name' => Request::get('guard_name'),
        ]);

        return Redirect::to('/administration/permissions')->with(['success'=> ['message' => 'Permisia a fost actualizata cu succes!']]);
    }
}
