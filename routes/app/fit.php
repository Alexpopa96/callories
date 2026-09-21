<?php

use App\Http\Controllers\Fit\AbandonChallenge;
use App\Http\Controllers\Fit\AddMeal;
use App\Http\Controllers\Fit\Analyze;
use App\Http\Controllers\Fit\ApplyGoals;
use App\Http\Controllers\Fit\DestroyAccount;
use App\Http\Controllers\Fit\DestroyFavorite;
use App\Http\Controllers\Fit\DestroyMeal;
use App\Http\Controllers\Fit\DestroyWeight;
use App\Http\Controllers\Fit\EditMeal;
use App\Http\Controllers\Fit\ExportData;
use App\Http\Controllers\Fit\History;
use App\Http\Controllers\Fit\Home;
use App\Http\Controllers\Fit\LookupBarcode;
use App\Http\Controllers\Fit\Profile;
use App\Http\Controllers\Fit\Scan;
use App\Http\Controllers\Fit\ShowChallenge;
use App\Http\Controllers\Fit\StoreChallenge;
use App\Http\Controllers\Fit\StoreCustomBarcode;
use App\Http\Controllers\Fit\StoreFavorite;
use App\Http\Controllers\Fit\StoreMeal;
use App\Http\Controllers\Fit\StoreWeight;
use App\Http\Controllers\Fit\SubscribePush;
use App\Http\Controllers\Fit\UnsubscribePush;
use App\Http\Controllers\Fit\UpdateBody;
use App\Http\Controllers\Fit\UpdateGoals;
use App\Http\Controllers\Fit\UpdateMeal;
use App\Http\Controllers\Fit\UpdateReminders;
use App\Http\Controllers\Fit\UpdateSteps;
use App\Http\Controllers\Fit\UpdateWater;
use App\Http\Controllers\Fit\Weight;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('/', '/today');

    Route::get('today', Home::class)->name('fit.today');
    Route::get('history', History::class)->name('fit.history');
    Route::get('scan', Scan::class)->name('fit.scan');
    Route::post('scan/analyze', Analyze::class)->name('fit.analyze');

    Route::get('meals/create', AddMeal::class)->name('fit.meals.create');
    Route::post('meals', StoreMeal::class)->name('fit.meals.store');
    Route::get('meals/{meal}/edit', EditMeal::class)->name('fit.meals.edit');
    Route::put('meals/{meal}', UpdateMeal::class)->name('fit.meals.update');
    Route::delete('meals/{meal}', DestroyMeal::class)->name('fit.meals.destroy');

    Route::post('favorites', StoreFavorite::class)->name('fit.favorites.store');
    Route::delete('favorites/{favorite}', DestroyFavorite::class)->name('fit.favorites.destroy');
    Route::get('barcode/{code}', LookupBarcode::class)->name('fit.barcode');
    Route::post('barcode/{code}', StoreCustomBarcode::class)->name('fit.barcode.store');

    Route::put('log/water', UpdateWater::class)->name('fit.water');
    Route::put('log/steps', UpdateSteps::class)->name('fit.steps');

    Route::get('weight', Weight::class)->name('fit.weight');
    Route::put('log/weight', StoreWeight::class)->name('fit.weight.store');
    Route::delete('weight/{weight}', DestroyWeight::class)->name('fit.weight.destroy');

    Route::get('challenge', ShowChallenge::class)->name('fit.challenge.show');
    Route::post('challenge', StoreChallenge::class)->name('fit.challenge.store');
    Route::delete('challenge/{challenge}', AbandonChallenge::class)->name('fit.challenge.abandon');

    Route::get('me', Profile::class)->name('fit.profile');
    Route::put('me/goals', UpdateGoals::class)->name('fit.goals');
    Route::post('me/goals/apply', ApplyGoals::class)->name('fit.goals.apply');
    Route::put('me/body', UpdateBody::class)->name('fit.body');
    Route::put('me/reminders', UpdateReminders::class)->name('fit.reminders');
    Route::get('me/export', ExportData::class)->name('fit.export');
    Route::delete('me', DestroyAccount::class)->name('fit.account.destroy');

    Route::post('push/subscribe', SubscribePush::class)->name('fit.push.subscribe');
    Route::post('push/unsubscribe', UnsubscribePush::class)->name('fit.push.unsubscribe');
});
