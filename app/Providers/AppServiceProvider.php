<?php

namespace App\Providers;

use Anthropic\Client;
use App\Services\Calories\FoodPhotoAnalyzer;
use App\Services\Calories\FoodTextAnalyzer;
use App\Services\Calories\MealRefiner;
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
        // every user is billed on their own key, so the client is built per request, never shared
        $this->app->bind(Client::class, function () {
            $key = auth()->user()?->anthropic_api_key;

            // an empty key makes the SDK auto-detect credentials from the server, which must never happen
            abort_if(blank($key), 403, 'Setează-ți cheia API Anthropic în Profil ca să folosești funcțiile AI.');

            return new Client(apiKey: $key);
        });

        $this->app->bind(FoodPhotoAnalyzer::class, fn ($app) => new FoodPhotoAnalyzer(
            $app->make(Client::class),
            auth()->user()?->anthropicModel() ?? config('services.anthropic.model'),
        ));

        $this->app->bind(FoodTextAnalyzer::class, fn ($app) => new FoodTextAnalyzer(
            $app->make(Client::class),
            auth()->user()?->anthropicModel() ?? config('services.anthropic.model'),
        ));

        $this->app->bind(MealRefiner::class, fn ($app) => new MealRefiner(
            $app->make(Client::class),
            auth()->user()?->anthropicModel() ?? config('services.anthropic.model'),
        ));

        $this->app->bind(NutritionAssistant::class, fn ($app) => new NutritionAssistant(
            $app->make(Client::class),
            auth()->user()?->anthropicModel() ?? config('services.anthropic.model'),
        ));

        $this->app->bind(WorkoutCoach::class, fn ($app) => new WorkoutCoach(
            $app->make(Client::class),
            auth()->user()?->anthropicModel() ?? config('services.anthropic.model'),
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
