<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Administration\Users\Edit;
use App\Http\Controllers\Administration\Users\Index;
use App\Http\Controllers\Administration\Users\Store;
use App\Http\Controllers\Administration\Users\Create;
use App\Http\Controllers\Administration\Users\Update;

Route::middleware(['auth'])
    ->group(function () {
        Route::namespace('')
            ->prefix('administration/users')
            ->as('administration.users.')
            ->group(function () {
                Route::get('', Index::class)->name('')->middleware('can:view users');
                Route::get('create', Create::class)->name('create')->middleware('can:create user');
                Route::post('store', Store::class)->name('administration.users.store')->middleware('can:create user');
                Route::get('{user}/edit', Edit::class)->name('administration.users.edit')->middleware('can:edit user');
                Route::put('{user}/update', Update::class)->name('administration.users.update')->middleware('can:edit user');
            });
    });
