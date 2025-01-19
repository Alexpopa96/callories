<?php

namespace App\Http\Controllers\Administration\Roles;

use Inertia\Inertia;
use App\Models\Client;
use App\Models\Location;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Redirect;

class Update extends Controller
{
    public function __invoke(Role $role)
    {
        Request::validate([
            'name' => ['required'],
            'guard_name' => ['required'],
        ]);

        $role->update([
            'name' => Request::get('name'),
            'guard_name' => Request::get('guard_name'),
        ]);

        $ids = [];
        $oldPermissionIds = $role->permissions->pluck('id');
        $checkIds = request('selected');
        if(!empty($checkIds)){
            foreach($checkIds as $checkId){
                array_push($ids,$checkId);
            }

            $role->permissions()->sync($ids);
        }
        if(empty($checkIds) && !empty($oldPermissionIds)){
            $role->permissions()->sync([]);
        }
        return Redirect::back()->with(['success'=> ['message' => 'Rolul a fost actualizat cu succes!']]);
    }
}
