<?php

namespace App\Http\Controllers\Administration\Users;

use App\Models\User;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Redirect;

class Store extends Controller
{
    public function __invoke()
    {
        Request::validate([
            'name' => ['required'],
            'email' => ['required', 'max:50', Rule::unique('users'),'email:rfc,dns'],
            'role_id' => ['required'],
            'phone' => ['nullable'],
            'obs' => ['nullable'],
        ], [
            'required' => 'Campul este obligatoriu',
            'unique' => 'Username-ul este deja ales.'
        ]);

        $user =  User::create([
            'name' => Request::get('name'),
            'email' => Request::get('email'),
            'phone'=> Request::get('phone'),
            'password' => Hash::make('12345'),
            'obs'=> Request::get('obs'),
        ]);

        if (request()->get('role_id')) {
            $user->syncRoles(Role::find(request()->get('role_id')));
        }


        return Redirect::to('/administration/users')->with(['success'=> ['message'=> 'Utilizatorul a fost creat cu succes!']]);
    }
}
