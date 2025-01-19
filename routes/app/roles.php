<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Administration\Roles\Index;
use App\Http\Controllers\Administration\Roles\Edit;
use App\Http\Controllers\Administration\Roles\Store;
use App\Http\Controllers\Administration\Roles\Create;
use App\Http\Controllers\Administration\Roles\Update;

Route::middleware(['auth'])
    ->group(function () {
        Route::namespace('')
            ->prefix('administration/roles')
            ->as('administration.roles.')
            ->group(function () {
                Route::get('', Index::class)->middleware('can:view roles');
                Route::get('create', Create::class)->middleware('can:create role');
                Route::post('store', Store::class)->middleware('can:create role');
                Route::get('{role}/edit', Edit::class)->middleware('can:edit role');
                Route::put('{role}/update', Update::class)->middleware('can:edit role');
            });
    });
