<?php

namespace App\Http\Controllers\Administration\Roles;

use Spatie\Permission\Models\Role;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Redirect;

class Store extends Controller
{
    public function __invoke()
    {
        Request::validate([
            'name' => ['required'],
            'guard_name' => ['required']
        ], [
            'required' => 'Campul este obligatoriu!'
        ]);

        $role = Role::create([
            'name' => Request::get('name'),
            'guard_name' => Request::get('guard_name'),
        ]);

        $checkIds = request('permissionIds');
        $ids = [];
        if(!empty($checkIds)){
            foreach($checkIds as $checkId){
                array_push($ids,$checkId);
            }

            $role->permissions()->sync($ids);
        }else{
            $role->permissions()->sync([]);
        }


        return Redirect::to('administration/roles/'.$role->id.'/edit')->with(['success'=> ['message'=> 'Rolul a fost creat cu succes!']]);
    }
}
