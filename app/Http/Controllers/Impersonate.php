<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;

class Impersonate extends Controller
{
    public function impersonate($id) {
        Session::put('impersonate', Auth::user()->id);

        Auth::loginUsingId($id);

        return Redirect::to('/dashboard');
    }

    public function exitImpersonate() {
        Auth::loginUsingId(Session::get('impersonate'));
        Session::forget('impersonate');
        return Redirect::to('/administration/users');
    }
}
