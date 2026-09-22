<?php

namespace App\Providers;

use Anthropic\Client;
use App\Services\Calories\FoodPhotoAnalyzer;
use App\Services\Calories\FoodTextAnalyzer;
use App\Services\Fit\NutritionAssistant;
use App\Services\Fit\WorkoutCoach;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Client::class, fn () => new Client(
            apiKey: (string) config('services.anthropic.key'),
        ));

        $this->app->singleton(FoodPhotoAnalyzer::class, fn ($app) => new FoodPhotoAnalyzer(
            $app->make(Client::class),
            config('services.anthropic.model'),
        ));

        $this->app->singleton(FoodTextAnalyzer::class, fn ($app) => new FoodTextAnalyzer(
            $app->make(Client::class),
            config('services.anthropic.model'),
        ));

        $this->app->singleton(NutritionAssistant::class, fn ($app) => new NutritionAssistant(
            $app->make(Client::class),
            config('services.anthropic.model'),
        ));

        $this->app->singleton(WorkoutCoach::class, fn ($app) => new WorkoutCoach(
            $app->make(Client::class),
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
