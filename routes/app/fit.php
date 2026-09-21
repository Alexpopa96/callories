<?php

use App\Http\Controllers\Fit\Analyze;
use App\Http\Controllers\Fit\DestroyMeal;
use App\Http\Controllers\Fit\History;
use App\Http\Controllers\Fit\Home;
use App\Http\Controllers\Fit\Profile;
use App\Http\Controllers\Fit\Scan;
use App\Http\Controllers\Fit\StoreMeal;
use App\Http\Controllers\Fit\UpdateGoals;
use App\Http\Controllers\Fit\UpdateSteps;
use App\Http\Controllers\Fit\UpdateWater;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('/', '/today');

    Route::get('today', Home::class)->name('fit.today');
    Route::get('history', History::class)->name('fit.history');
    Route::get('scan', Scan::class)->name('fit.scan');
    Route::post('scan/analyze', Analyze::class)->name('fit.analyze');

    Route::post('meals', StoreMeal::class)->name('fit.meals.store');
    Route::delete('meals/{meal}', DestroyMeal::class)->name('fit.meals.destroy');

    Route::put('log/water', UpdateWater::class)->name('fit.water');
    Route::put('log/steps', UpdateSteps::class)->name('fit.steps');

    Route::get('me', Profile::class)->name('fit.profile');
    Route::put('me/goals', UpdateGoals::class)->name('fit.goals');
});
