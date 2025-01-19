<?php

namespace App\Http\Controllers\Administration\Users;

use App\Models\User;
use Illuminate\Validation\Rule;

use Spatie\Permission\Models\Role;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Redirect;

class Update extends Controller
{
    public function __invoke(User $user)
    {
        $user->update(
            Request::validate([
                'name' => ['required', 'max:50'],
                'email' => ['required', 'max:50', Rule::unique('users')->ignore($user->id),'email:rfc,dns'],
                'phone' => ['nullable'],
                'status' => ['boolean'],
                'obs' => ['nullable'],
            ],[
                'required' => 'Campul este obligatoriu',
                'email' => 'Adresa de email nu este valida',
            ])
        );

        if (request()->get('role_id')) {
            $user->syncRoles([Role::find(request()->get('role_id'))->name]);
        }

        return Redirect::to('/administration/users')->with(['success'=> ['message' => 'Utilizator actualizat cu success!']]);
    }
}

