<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Administration\Permissions\Edit;
use App\Http\Controllers\Administration\Permissions\Index;
use App\Http\Controllers\Administration\Permissions\Store;
use App\Http\Controllers\Administration\Permissions\Create;
use App\Http\Controllers\Administration\Permissions\Update;

Route::middleware(['auth'])
    ->group(function () {
        Route::namespace('')
            ->prefix('administration/permissions')
            ->as('administration.permissions.')
            ->group(function () {
                Route::get('', Index::class)->middleware('can:view permissions');
                Route::get('create', Create::class)->middleware('can:create permission');
                Route::post('store', Store::class)->middleware('can:create permission');
                Route::get('{permission}/edit', Edit::class)->middleware('can:edit permission');
                Route::put('{permission}/update', Update::class)->middleware('can:edit permission');
            });
    });
