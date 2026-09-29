<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;

class Impersonate extends Controller
{
    public function impersonate($id)
    {
        /** @var User $admin */
        $admin = Auth::user();
        $target = User::findOrFail($id);

        // only admins who can edit users, never into yourself, another admin, or from inside an impersonation
        abort_unless($admin->can('edit user'), 403);
        abort_if(Session::has('impersonate') || $target->is($admin) || $target->can('view administration'), 403);

        Session::put('impersonate', $admin->id);

        Auth::login($target);

        return Redirect::to('/dashboard');
    }

    public function exitImpersonate()
    {
        abort_unless(Session::has('impersonate'), 403);

        Auth::loginUsingId(Session::pull('impersonate'));

        return Redirect::to('/administration/users');
    }
}
