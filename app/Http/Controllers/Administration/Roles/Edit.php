<?php

namespace App\Http\Controllers\Administration\Roles;

use Inertia\Inertia;
use Spatie\Permission\Models\Role;
use App\Http\Controllers\Controller;
use Spatie\Permission\Models\Permission;

class Edit extends Controller
{
    public function __invoke(Role $role)
    {
        $var = [];
        $permissionsAdmin = [];
        $permissions = Permission::all();
        $filtersLabel = $role->permissions()->pluck('name');

        foreach ($filtersLabel as $filter) {
            $test = explode(" ", $filter);
            array_push($var, $test[1]);
        }

        return Inertia::render('Administration/Roles/Edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'guard_name' => $role->guard_name
            ],
            'permissions' => $permissions,
            'date' => $role->permissions()->pluck('id'),
            'options' => array_unique($var),
            'permissionGroups' => Permission::all()->pluck('group')->unique(),
            'admins' => Permission::where('permissions.parent','!=','')->pluck('group')->unique(),
            'clients' => Permission::where('permissions.parent' ,'=','clients')->pluck('group')->unique()
        ]);
    }
}
