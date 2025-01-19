<?php

namespace App\Http\Controllers\Administration\Permissions;

use Inertia\Inertia;
use Spatie\Permission\Models\Role;
use App\Http\Controllers\Controller;
use Spatie\Permission\Models\Permission;

class Edit extends Controller
{
    public function __invoke(Permission $permission)
    {
        return Inertia::render('Administration/Permissions/Edit', [
            'permission' => [
                'id' => $permission->id,
                'name' => $permission->name,
                'group' => $permission->group,
                'parent' => $permission->parent,
                'guard_name' => $permission->guard_name
            ],
        ]);
    }
}
