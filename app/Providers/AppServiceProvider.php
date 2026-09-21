<?php

namespace App\Providers;

use Anthropic\Client;
use App\Services\Calories\FoodPhotoAnalyzer;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(FoodPhotoAnalyzer::class, fn () => new FoodPhotoAnalyzer(
            new Client(apiKey: (string) config('services.anthropic.key')),
            config('services.anthropic.model'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
