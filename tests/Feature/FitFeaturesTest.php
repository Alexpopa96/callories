<?php

namespace Tests\Feature;

use App\Models\CustomBarcode;
use App\Models\User;
use App\Services\Calories\FoodPhotoAnalyzer;
use App\Services\Fit\AssistantException;
use App\Services\Fit\GoalCalculator;
use App\Services\Fit\NutritionAssistant;
use App\Services\Fit\PushSender;
use App\Services\Fit\ReminderPlanner;
use App\Services\Fit\WorkoutCoach;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FitFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function user(array $attributes = []): User
    {
        $user = User::factory()->create(['status' => true, 'anthropic_api_key' => 'sk-ant-test-key-1234', ...$attributes]);
        $user->assignRole(Role::findOrCreate('user', 'web'));

        // reload so columns filled by DB defaults (goals, status) are present on the model
        return $user->fresh();
    }

    private function item(array $override = []): array
    {
        return [
            'name' => 'Paste carbonara', 'portion_grams' => 300, 'calories' => 520,
            'protein_g' => 22, 'carbs_g' => 60, 'fat_g' => 20, 'fiber_g' => 3, ...$override,
        ];
    }

    private function meal(User $user, array $override = [])
    {
        return $user->meals()->create([
            'eaten_on' => CarbonImmutable::today()->toDateString(), 'title' => 'Paste carbonara',
            'items' => [$this->item()], 'calories' => 520, 'protein_g' => 22, 'carbs_g' => 60,
            'fat_g' => 20, 'fiber_g' => 3, ...$override,
        ]);
    }

    // --- scan quota -----------------------------------------------------------------------

    public function test_photo_analysis_stops_at_the_daily_limit(): void
    {
        config(['services.anthropic.daily_scan_limit' => 2]);

        $this->mock(FoodPhotoAnalyzer::class, function ($mock) {
            $mock->shouldReceive('analyze')->twice()->andReturn([
                'is_food' => true, 'items' => [$this->item()], 'totals' => [], 'confidence' => 'high', 'notes' => '',
            ]);
        });

        $user = $this->user();
        $photo = fn () => ['photo' => UploadedFile::fake()->image('meal.jpg')];

        $this->actingAs($user)->postJson('/scan/analyze', $photo())->assertOk()->assertJsonPath('scans_left', 1);
        $this->actingAs($user)->postJson('/scan/analyze', $photo())->assertOk()->assertJsonPath('scans_left', 0);
        $this->actingAs($user)->postJson('/scan/analyze', $photo())->assertStatus(429)->assertJsonPath('scans_left', 0);

        $this->actingAs($user)->get('/scan')->assertInertia(fn ($page) => $page->where('scansLeft', 0));
    }

    public function test_the_limit_is_per_user(): void
    {
        config(['services.anthropic.daily_scan_limit' => 1]);

        $this->mock(FoodPhotoAnalyzer::class, function ($mock) {
            $mock->shouldReceive('analyze')->twice()->andReturn([
                'is_food' => true, 'items' => [], 'totals' => [], 'confidence' => 'low', 'notes' => '',
            ]);
        });

        $photo = fn () => ['photo' => UploadedFile::fake()->image('meal.jpg')];

        $this->actingAs($this->user())->postJson('/scan/analyze', $photo())->assertOk();
        $this->actingAs($this->user())->postJson('/scan/analyze', $photo())->assertOk();
    }

    // --- editing and manual meals ---------------------------------------------------------

    public function test_a_meal_can_be_edited_and_totals_are_recomputed(): void
    {
        $user = $this->user();
        $meal = $this->meal($user);

        $this->actingAs($user)->get("/meals/{$meal->id}/edit")
            ->assertInertia(fn ($page) => $page->component('Fit/EditMeal')->where('meal.id', $meal->id)->has('meal.items', 1));

        $this->actingAs($user)->put("/meals/{$meal->id}", [
            'title' => 'Prânz',
            'items' => [$this->item(['portion_grams' => 150, 'calories' => 260, 'protein_g' => 11]), $this->item(['name' => 'Pâine', 'calories' => 100, 'protein_g' => 4])],
        ])->assertRedirect('/today?date='.CarbonImmutable::today()->toDateString());

        $meal->refresh();
        $this->assertSame('Prânz', $meal->title);
        $this->assertSame(360, $meal->calories);
        $this->assertSame(15.0, $meal->protein_g);
        $this->assertCount(2, $meal->items);
    }

    public function test_the_meal_photo_is_replaced_or_removed_only_when_saving(): void
    {
        Storage::fake('public');
        $user = $this->user();
        $meal = $this->meal($user);

        $this->actingAs($user)->post("/meals/{$meal->id}", [
            '_method' => 'put',
            'items' => [$this->item()],
            'photo' => UploadedFile::fake()->image('a.jpg'),
        ])->assertRedirect();
        $first = $meal->refresh()->photo_path;
        Storage::disk('public')->assertExists($first);

        // saving without touching the photo keeps it
        $this->actingAs($user)->put("/meals/{$meal->id}", ['items' => [$this->item()]]);
        $this->assertSame($first, $meal->refresh()->photo_path);

        $this->actingAs($user)->post("/meals/{$meal->id}", [
            '_method' => 'put',
            'items' => [$this->item()],
            'photo' => UploadedFile::fake()->image('b.jpg'),
        ]);
        $second = $meal->refresh()->photo_path;
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);

        $this->actingAs($user)->put("/meals/{$meal->id}", ['items' => [$this->item()], 'remove_photo' => true]);
        $this->assertNull($meal->refresh()->photo_path);
        Storage::disk('public')->assertMissing($second);

        $this->actingAs($user)->put("/meals/{$meal->id}", [
            'items' => [$this->item()],
            'photo' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors('photo');
    }

    public function test_other_users_meals_cannot_be_edited(): void
    {
        $meal = $this->meal($this->user());

        $this->actingAs($this->user())->get("/meals/{$meal->id}/edit")->assertNotFound();
        $this->actingAs($this->user())->put("/meals/{$meal->id}", ['items' => [$this->item()]])->assertNotFound();
    }

    public function test_add_meal_page_lists_recent_meals_and_favorites(): void
    {
        $user = $this->user();
        $this->meal($user);
        $this->meal($user, ['title' => 'Paste carbonara']);
        $this->meal($user, ['title' => 'Fără itemi', 'items' => []]);
        $this->meal($this->user(), ['title' => 'Al altcuiva']);

        $this->actingAs($user)->post('/favorites', $this->item(['name' => 'Iaurt']))->assertRedirect();

        $this->actingAs($user)->get('/meals/create')
            ->assertInertia(fn ($page) => $page
                ->component('Fit/AddMeal')
                ->has('recent', 1)
                ->where('recent.0.title', 'Paste carbonara')
                ->has('favorites', 1)
                ->where('favorites.0.name', 'Iaurt'));
    }

    public function test_favorites_are_unique_per_name_and_deletable(): void
    {
        $user = $this->user();

        $this->actingAs($user)->post('/favorites', $this->item(['name' => 'Iaurt', 'calories' => 100]));
        $this->actingAs($user)->post('/favorites', $this->item(['name' => 'Iaurt', 'calories' => 120]));

        $favorite = $user->favoriteFoods()->sole();
        $this->assertSame(120.0, $favorite->calories);

        $this->actingAs($this->user())->delete("/favorites/{$favorite->id}")->assertNotFound();
        $this->actingAs($user)->delete("/favorites/{$favorite->id}")->assertRedirect();
        $this->assertSame(0, $user->favoriteFoods()->count());
    }

    // --- barcode --------------------------------------------------------------------------

    public function test_barcode_lookup_returns_a_meal_item(): void
    {
        Cache::flush();
        Http::fake(['world.openfoodfacts.org/*' => Http::response([
            'status' => 1,
            'product' => [
                'product_name' => 'Iaurt grecesc', 'brands' => 'Olympus,Altceva', 'serving_quantity' => 200,
                'nutriments' => ['energy-kcal_100g' => 65, 'proteins_100g' => 10, 'carbohydrates_100g' => 4, 'fat_100g' => 2, 'fiber_100g' => 0],
            ],
        ])]);

        $this->actingAs($this->user())->getJson('/barcode/5941234567890')
            ->assertOk()
            ->assertJsonPath('name', 'Iaurt grecesc — Olympus')
            ->assertJsonPath('portion_grams', 200)
            ->assertJsonPath('calories', 130)
            ->assertJsonPath('protein_g', 20)
            ->assertJsonPath('per100.calories', 65);
    }

    public function test_barcode_lookup_handles_unknown_and_invalid_codes(): void
    {
        Cache::flush();
        Http::fake(['world.openfoodfacts.org/*' => Http::response(['status' => 0, 'status_verbose' => 'product not found'])]);

        $this->actingAs($this->user())->getJson('/barcode/1234567890123')->assertNotFound();
        $this->actingAs($this->user())->getJson('/barcode/abc')->assertUnprocessable();
    }

    public function test_barcode_lookup_falls_back_to_a_saved_custom_product(): void
    {
        Cache::flush();
        Http::fake(['world.openfoodfacts.org/*' => Http::response(['status' => 0])]);
        CustomBarcode::create([
            'code' => '1234567890123', 'name' => 'Brânză Lidl', 'portion_grams' => 100,
            'calories' => 300, 'protein_g' => 20, 'carbs_g' => 2, 'fat_g' => 24, 'fiber_g' => 0,
        ]);

        $this->actingAs($this->user())->getJson('/barcode/1234567890123')
            ->assertOk()
            ->assertJsonPath('name', 'Brânză Lidl')
            ->assertJsonPath('calories', 300)
            ->assertJsonPath('per100.calories', 300);
    }

    public function test_an_unknown_barcode_can_be_saved_and_is_then_recognised(): void
    {
        Cache::flush();
        Http::fake(['world.openfoodfacts.org/*' => Http::response(['status' => 0])]);
        $user = $this->user();

        $this->actingAs($user)->getJson('/barcode/1234567890123')->assertNotFound();

        $this->actingAs($user)->postJson('/barcode/1234567890123', [
            'name' => 'Brânză Lidl', 'portion_grams' => 100, 'calories' => 300,
            'protein_g' => 20, 'carbs_g' => 2, 'fat_g' => 24, 'fiber_g' => 0,
        ])->assertOk()->assertJsonPath('name', 'Brânză Lidl')->assertJsonPath('per100.calories', 300);

        $this->assertSame(1, CustomBarcode::count());
        $this->assertSame($user->id, CustomBarcode::sole()->created_by);

        $this->actingAs($user)->getJson('/barcode/1234567890123')->assertOk()->assertJsonPath('name', 'Brânză Lidl');
    }

    public function test_saving_a_custom_barcode_is_validated(): void
    {
        $user = $this->user();

        $this->actingAs($user)->postJson('/barcode/abc', ['name' => 'X', 'portion_grams' => 100, 'calories' => 1])->assertUnprocessable();
        $this->actingAs($user)->postJson('/barcode/1234567890123', ['portion_grams' => 100, 'calories' => 1])->assertUnprocessable();
        $this->assertSame(0, CustomBarcode::count());
    }

    // --- goals ----------------------------------------------------------------------------

    public function test_goal_calculator_uses_mifflin_st_jeor(): void
    {
        $user = new User(['sex' => 'm', 'birth_date' => '1996-09-21', 'height_cm' => 180, 'activity_level' => 'moderate', 'goal_type' => 'lose']);

        $suggestion = (new GoalCalculator)->suggest($user, 80.0, CarbonImmutable::parse('2026-09-21'));

        // BMR = 10*80 + 6.25*180 - 5*30 + 5 = 1780; TDEE = 1780 * 1.55 = 2759; cut 500 -> 2259 -> 2250
        $this->assertSame(1780, $suggestion['bmr']);
        $this->assertSame(2759, $suggestion['tdee']);
        $this->assertSame(2250, $suggestion['calories']);
        $this->assertSame(160, $suggestion['proteinG']);
        $this->assertSame(2800, $suggestion['waterMl']);
    }

    public function test_goal_calculator_needs_a_complete_profile(): void
    {
        $this->assertNull((new GoalCalculator)->suggest(new User(['sex' => 'f']), 60.0));
        $this->assertNull((new GoalCalculator)->suggest(new User(['sex' => 'f', 'birth_date' => '1990-01-01', 'height_cm' => 165]), null));
    }

    public function test_body_stats_are_saved_and_recommendation_applied(): void
    {
        $user = $this->user();

        $this->actingAs($user)->post('/me/goals/apply')->assertSessionHas('error');

        $this->actingAs($user)->put('/me/body', [
            'sex' => 'f', 'birthDate' => '1995-05-10', 'heightCm' => 168,
            'activityLevel' => 'light', 'goalType' => 'maintain', 'weightKg' => 62.5,
        ])->assertRedirect();

        $user->refresh();
        $this->assertSame('f', $user->sex);
        $this->assertSame(62.5, $user->latestWeight()->weight_kg);

        $this->actingAs($user)->get('/me')->assertInertia(fn ($page) => $page
            ->component('Fit/Profile')
            ->where('body.heightCm', 168)
            ->where('body.weightKg', 62.5)
            ->has('suggestion.calories'));

        $this->actingAs($user)->post('/me/goals/apply')->assertSessionHas('success');

        $user->refresh();
        $this->assertNotSame(2000, $user->calorie_goal);
        $this->assertNotNull($user->protein_goal_g);
        $this->assertNotNull($user->carbs_goal_g);
        $this->assertNotNull($user->fat_goal_g);
    }

    public function test_body_stats_are_validated(): void
    {
        $this->actingAs($this->user())->put('/me/body', [
            'sex' => 'x', 'birthDate' => '2026-01-01', 'heightCm' => 20,
            'activityLevel' => 'nope', 'goalType' => 'nope',
        ])->assertSessionHasErrors(['sex', 'birthDate', 'heightCm', 'activityLevel', 'goalType']);
    }

    public function test_macro_goals_are_optional_and_can_be_cleared(): void
    {
        $user = $this->user();
        $goals = ['calories' => 2200, 'steps' => 9000, 'waterMl' => 2500];

        $this->actingAs($user)->put('/me/goals', [...$goals, 'proteinG' => 150, 'carbsG' => 250, 'fatG' => 70])->assertRedirect();
        $this->assertSame([150, 250, 70], [$user->fresh()->protein_goal_g, $user->fresh()->carbs_goal_g, $user->fresh()->fat_goal_g]);

        $this->actingAs($user)->put('/me/goals', [...$goals, 'proteinG' => null, 'carbsG' => null, 'fatG' => null])->assertRedirect();
        $this->assertNull($user->fresh()->protein_goal_g);
    }

    // --- weight ---------------------------------------------------------------------------

    public function test_weight_is_logged_once_per_day_and_charted(): void
    {
        $user = $this->user();
        $today = CarbonImmutable::today();

        $this->actingAs($user)->put('/log/weight', ['date' => $today->subDays(10)->toDateString(), 'weight_kg' => 82.4])->assertRedirect();
        $this->actingAs($user)->put('/log/weight', ['date' => $today->toDateString(), 'weight_kg' => 80.1])->assertRedirect();
        $this->actingAs($user)->put('/log/weight', ['date' => $today->toDateString(), 'weight_kg' => 80.0])->assertRedirect();

        $this->assertSame(2, $user->weightLogs()->count());

        $this->actingAs($user)->get('/weight')->assertInertia(fn ($page) => $page
            ->component('Fit/Weight')
            ->has('logs', 2)
            ->where('latest', 80)
            ->where('change', -2.4));

        $this->actingAs($user)->put('/log/weight', ['date' => $today->toDateString(), 'weight_kg' => 5])->assertSessionHasErrors('weight_kg');
        $this->actingAs($user)->put('/log/weight', ['date' => $today->addDay()->toDateString(), 'weight_kg' => 80])->assertSessionHasErrors('date');
    }

    public function test_weight_entries_are_private(): void
    {
        $owner = $this->user();
        $log = $owner->weightLogs()->create(['date' => CarbonImmutable::today()->toDateString(), 'weight_kg' => 70]);

        $this->actingAs($this->user())->delete("/weight/{$log->id}")->assertNotFound();
        $this->actingAs($owner)->delete("/weight/{$log->id}")->assertRedirect();
        $this->assertSame(0, $owner->weightLogs()->count());
    }

    // --- history --------------------------------------------------------------------------

    public function test_history_reports_goal_hits_and_30_day_averages(): void
    {
        $user = $this->user();
        $today = CarbonImmutable::today();

        $this->meal($user, ['calories' => 2000, 'protein_g' => 100]);
        $this->meal($user, ['eaten_on' => $today->subDays(20)->toDateString(), 'calories' => 3500]);
        $user->dailyLogs()->create(['date' => $today->toDateString(), 'steps' => 12000, 'water_ml' => 2600]);

        $this->actingAs($user)->get('/history')->assertInertia(fn ($page) => $page
            ->where('hits', ['calories' => 1, 'steps' => 1, 'waterMl' => 1, 'sleepMinutes' => 0])
            ->where('averages.calories', 2000)
            ->where('averages30.calories', 2750)
            ->where('days.0.protein', 100));
    }

    // --- challenge --------------------------------------------------------------------------

    public function test_a_challenge_survives_adding_a_meal(): void
    {
        $user = $this->user(['sex' => 'm', 'birth_date' => '1990-01-01', 'height_cm' => 180]);
        $userId = $user->id;

        $this->actingAs($user)->post('/challenge', [
            'weightKg' => 90, 'targetWeightKg' => 85, 'days' => 30, 'goal' => 'lose_weight',
        ])->assertRedirect('/challenge');

        // re-fetch the user for every request below, exactly like separate real HTTP requests would:
        // reusing the same PHP object across actingAs() calls hides bugs behind stale cached relations.
        $this->actingAs(User::find($userId))->post('/meals', [
            'date' => CarbonImmutable::today()->toDateString(),
            'items' => [$this->item()],
        ])->assertRedirect();

        $this->actingAs(User::find($userId))->get('/today')->assertInertia(fn ($page) => $page
            ->where('challenge.goal', 'lose_weight')
            ->where('challenge.daysElapsed', 1));

        $this->assertSame('active', User::find($userId)->activeChallenge->status);
    }

    // --- assistant --------------------------------------------------------------------------

    public function test_assistant_page_shows_todays_remaining_macros(): void
    {
        $user = $this->user(['calorie_goal' => 2000, 'protein_goal_g' => 150]);
        $this->meal($user, ['calories' => 800, 'protein_g' => 30]);

        $this->actingAs($user)->get('/assistant')->assertInertia(fn ($page) => $page
            ->component('Fit/Assistant')
            ->where('remaining.calories', 1200)
            ->where('remaining.proteinG', 120)
            ->where('remaining.carbsG', null));
    }

    public function test_assistant_answers_with_context_and_respects_the_daily_limit(): void
    {
        config(['services.anthropic.daily_assistant_limit' => 1]);
        $user = $this->user(['protein_goal_g' => 150]);
        $this->meal($user, ['calories' => 800, 'protein_g' => 30]);
        $user->favoriteFoods()->create(['name' => 'Iaurt grecesc', 'portion_grams' => 200, 'calories' => 130, 'protein_g' => 20, 'carbs_g' => 8, 'fat_g' => 2, 'fiber_g' => 0]);

        $this->mock(NutritionAssistant::class, function ($mock) {
            $mock->shouldReceive('ask')->once()->withArgs(function (array $context, array $history, string $message) {
                return $context['ramas_azi']['proteine_g'] === 120.0
                    && $context['alimente_favorite'][0]['name'] === 'Iaurt grecesc'
                    && $history === []
                    && $message === 'Ce mănânc pentru proteine?';
            })->andReturn(['reply' => 'Încearcă iaurtul grecesc.', 'suggestions' => [
                ['name' => 'Iaurt grecesc', 'portion_grams' => 200, 'calories' => 130, 'protein_g' => 20, 'carbs_g' => 8, 'fat_g' => 2, 'fiber_g' => 0],
            ]]);
        });

        $this->actingAs($user)->postJson('/assistant/ask', ['message' => 'Ce mănânc pentru proteine?'])
            ->assertOk()
            ->assertJsonPath('reply', 'Încearcă iaurtul grecesc.')
            ->assertJsonPath('suggestions.0.name', 'Iaurt grecesc')
            ->assertJsonPath('questions_left', 0);

        $this->actingAs($user)->postJson('/assistant/ask', ['message' => 'Și altceva?'])
            ->assertStatus(429)
            ->assertJsonPath('questions_left', 0);
    }

    public function test_assistant_validates_the_message_and_history(): void
    {
        $user = $this->user();

        $this->actingAs($user)->postJson('/assistant/ask', ['message' => ''])->assertJsonValidationErrors('message');
        $this->actingAs($user)->postJson('/assistant/ask', ['message' => str_repeat('a', 301)])->assertJsonValidationErrors('message');
        $this->actingAs($user)->postJson('/assistant/ask', ['message' => 'bună', 'history' => [['role' => 'nope', 'content' => 'x']]])
            ->assertJsonValidationErrors('history.0.role');
    }

    public function test_assistant_failures_return_a_readable_error(): void
    {
        $user = $this->user();

        $this->mock(NutritionAssistant::class, function ($mock) {
            $mock->shouldReceive('ask')->andThrow(new AssistantException('Asistentul nu este disponibil momentan.'));
        });

        $this->actingAs($user)->postJson('/assistant/ask', ['message' => 'Salut'])
            ->assertStatus(502)
            ->assertJsonPath('message', 'Asistentul nu este disponibil momentan.');
    }

    // --- workout coach ----------------------------------------------------------------------

    public function test_workout_page_shows_recent_history(): void
    {
        $user = $this->user();
        $user->workouts()->create([
            'date' => CarbonImmutable::today()->toDateString(), 'title' => 'Spate și umeri',
            'exercises' => [['name' => 'Lat Pulldown', 'sets' => 3, 'reps' => '10-12', 'notes' => '']],
        ]);

        $this->actingAs($user)->get('/workout')->assertInertia(fn ($page) => $page
            ->component('Fit/Workout')
            ->where('history.0.title', 'Spate și umeri')
            ->where('history.0.exercises.0.name', 'Lat Pulldown'));
    }

    public function test_workout_coach_answers_with_recent_history_and_respects_the_daily_limit(): void
    {
        config(['services.anthropic.daily_workout_limit' => 1]);
        $user = $this->user();
        $user->workouts()->create([
            'date' => CarbonImmutable::yesterday()->toDateString(), 'title' => 'Picioare',
            'exercises' => [['name' => 'Squat', 'sets' => 4, 'reps' => '8', 'notes' => '']],
        ]);

        $this->mock(WorkoutCoach::class, function ($mock) {
            $mock->shouldReceive('ask')->once()->withArgs(function (array $context, array $history, string $message) {
                return $context['antrenamente_recente'][0]['titlu'] === 'Picioare'
                    && $history === []
                    && $message === 'Spate și umeri azi';
            })->andReturn(['reply' => 'Iată un plan.', 'plan' => [
                'title' => 'Spate și umeri', 'exercises' => [
                    ['name' => 'Lat Pulldown', 'sets' => 3, 'reps' => '10-12', 'notes' => 'Trage cu coatele.'],
                ],
            ]]);
        });

        $this->actingAs($user)->postJson('/workout/ask', ['message' => 'Spate și umeri azi'])
            ->assertOk()
            ->assertJsonPath('reply', 'Iată un plan.')
            ->assertJsonPath('plan.exercises.0.name', 'Lat Pulldown')
            ->assertJsonPath('questions_left', 0);

        $this->actingAs($user)->postJson('/workout/ask', ['message' => 'Și altceva?'])
            ->assertStatus(429)
            ->assertJsonPath('questions_left', 0);
    }

    public function test_workout_coach_validates_the_message_and_history(): void
    {
        $user = $this->user();

        $this->actingAs($user)->postJson('/workout/ask', ['message' => ''])->assertJsonValidationErrors('message');
        $this->actingAs($user)->postJson('/workout/ask', ['message' => str_repeat('a', 301)])->assertJsonValidationErrors('message');
    }

    public function test_a_workout_plan_can_be_saved_and_deleted(): void
    {
        $user = $this->user();
        $exercises = [['name' => 'Lat Pulldown', 'sets' => 3, 'reps' => '10-12', 'notes' => 'Trage cu coatele.']];

        $this->actingAs($user)->post('/workout', [
            'date' => CarbonImmutable::today()->toDateString(), 'title' => 'Spate și umeri', 'exercises' => $exercises,
        ])->assertRedirect();

        $workout = $user->workouts()->sole();
        $this->assertSame('Spate și umeri', $workout->title);
        $this->assertSame('Lat Pulldown', $workout->exercises[0]['name']);

        $this->actingAs($this->user())->delete("/workout/{$workout->id}")->assertNotFound();

        $this->actingAs($user)->delete("/workout/{$workout->id}")->assertRedirect();
        $this->assertDatabaseMissing('workouts', ['id' => $workout->id]);
    }

    public function test_a_workout_cannot_be_saved_for_a_future_day(): void
    {
        $user = $this->user();

        $this->actingAs($user)->post('/workout', [
            'date' => CarbonImmutable::tomorrow()->toDateString(), 'title' => 'Spate',
            'exercises' => [['name' => 'Lat Pulldown', 'sets' => 3, 'reps' => '10-12']],
        ])->assertSessionHasErrors('date');
    }

    // --- day strip ------------------------------------------------------------------------

    public function test_day_strip_is_the_calendar_week_monday_to_sunday(): void
    {
        $user = $this->user();
        $today = CarbonImmutable::today();
        $monday = $today->startOfWeek(CarbonInterface::MONDAY);

        $this->actingAs($user)->get('/today')->assertInertia(fn ($page) => $page
            ->has('strip', 7)
            ->where('strip.0.date', $monday->toDateString())
            ->where('strip.0.weekday', 'lun')
            ->where('strip.6.date', $monday->addDays(6)->toDateString())
            ->where('strip.6.weekday', 'dum')
            ->where('week.prev', $today->subWeek()->toDateString())
            ->where('week.next', null));

        // the days of the current week that have not happened yet cannot be picked
        $future = $monday->addDays(6)->gt($today);
        $this->actingAs($user)->get('/today')->assertInertia(fn ($page) => $page
            ->where('strip.6.future', $future));
    }

    public function test_day_strip_follows_the_selected_week_and_can_move_forward(): void
    {
        $user = $this->user();
        $today = CarbonImmutable::today();
        $old = $today->subWeeks(3);
        $monday = $old->startOfWeek(CarbonInterface::MONDAY);

        $this->actingAs($user)->get('/today?date='.$old->toDateString())->assertInertia(fn ($page) => $page
            ->where('strip.0.date', $monday->toDateString())
            ->where('strip.6.date', $monday->addDays(6)->toDateString())
            ->where('strip.6.future', false)
            ->where('week.prev', $old->subWeek()->toDateString())
            ->where('week.next', $old->addWeek()->toDateString()));

        // moving forward never goes past today
        $lastWeek = $today->subWeek()->startOfWeek(CarbonInterface::MONDAY)->addDays(6);
        $this->actingAs($user)->get('/today?date='.$lastWeek->toDateString())->assertInertia(fn ($page) => $page
            ->where('week.next', $lastWeek->addWeek()->min($today)->toDateString()));
    }

    // --- reminders ------------------------------------------------------------------------

    public function test_meal_reminders_depend_on_what_was_logged(): void
    {
        $user = $this->user(['remind_meals' => true]);
        $planner = new ReminderPlanner;
        $at = fn (int $hour) => CarbonImmutable::today()->setTime($hour, 0);

        $this->assertCount(1, $planner->messagesFor($user, $at(13)));
        $this->assertSame([], $planner->messagesFor($user, $at(9)));

        $this->meal($user, ['calories' => 400]);
        $this->assertSame([], $planner->messagesFor($user, $at(13)));
        $this->assertCount(1, $planner->messagesFor($user, $at(20)));

        $this->meal($user, ['calories' => 900]);
        $this->assertSame([], $planner->messagesFor($user, $at(20)));
    }

    public function test_water_reminders_only_fire_when_behind(): void
    {
        $user = $this->user(['remind_water' => true, 'water_goal_ml' => 2000]);
        $planner = new ReminderPlanner;
        $noon = CarbonImmutable::today()->setTime(11, 0);

        $this->assertCount(1, $planner->messagesFor($user, $noon));

        $user->dailyLogs()->create(['date' => $noon->toDateString(), 'water_ml' => 600]);
        $this->assertSame([], $planner->messagesFor($user, $noon));

        $off = $this->user(['remind_water' => false]);
        $this->assertSame([], $planner->messagesFor($off, $noon));
    }

    public function test_reminder_command_sends_only_to_subscribed_users(): void
    {
        config(['services.webpush.public_key' => 'pub', 'services.webpush.private_key' => 'priv']);
        $this->travelTo(CarbonImmutable::today()->setTime(13, 0));

        $subscribed = $this->user(['remind_meals' => true]);
        $subscribed->pushSubscriptions()->create(['endpoint_hash' => 'a', 'endpoint' => 'https://push.test/a', 'public_key' => 'k', 'auth_token' => 't']);
        $this->user(['remind_meals' => true]);

        $this->mock(PushSender::class, function ($mock) use ($subscribed) {
            $mock->shouldReceive('configured')->andReturn(true);
            $mock->shouldReceive('send')->once()->withArgs(fn (User $user, array $message) => $user->is($subscribed) && $message['url'] === '/scan')->andReturn(1);
        });

        $this->artisan('fit:send-reminders')->expectsOutput('Sent 1 notification(s).')->assertSuccessful();
    }

    public function test_push_subscriptions_are_stored_per_endpoint(): void
    {
        $user = $this->user();
        $payload = ['endpoint' => 'https://push.test/abc', 'keys' => ['p256dh' => 'key', 'auth' => 'secret']];

        $this->actingAs($user)->postJson('/push/subscribe', $payload)->assertOk();
        $this->actingAs($user)->postJson('/push/subscribe', $payload)->assertOk();
        $this->assertSame(1, $user->pushSubscriptions()->count());

        $this->actingAs($user)->postJson('/push/subscribe', ['endpoint' => 'nope'])->assertUnprocessable();

        $this->actingAs($user)->postJson('/push/unsubscribe', ['endpoint' => $payload['endpoint']])->assertOk();
        $this->assertSame(0, $user->pushSubscriptions()->count());
    }

    public function test_reminder_preferences_are_saved(): void
    {
        $user = $this->user();

        $this->actingAs($user)->put('/me/reminders', ['meals' => true, 'water' => false, 'calorieLimit' => true])->assertRedirect();

        $this->assertTrue($user->fresh()->remind_meals);
        $this->assertFalse($user->fresh()->remind_water);
        $this->assertTrue($user->fresh()->remind_calorie_limit);
    }

    // --- calorie limit reminder -------------------------------------------------------------

    public function test_calorie_limit_message_fires_once_when_crossing_a_threshold(): void
    {
        $planner = new ReminderPlanner;

        $this->assertNull($planner->calorieLimitMessage(1000, 1500, 2000));
        $this->assertSame('Te apropii de limita zilnică', $planner->calorieLimitMessage(1500, 1850, 2000)['title']);
        $this->assertNull($planner->calorieLimitMessage(1850, 1900, 2000));
        $this->assertSame('Ai depășit limita zilnică', $planner->calorieLimitMessage(1900, 2050, 2000)['title']);
        $this->assertNull($planner->calorieLimitMessage(2050, 2200, 2000));
        $this->assertNull($planner->calorieLimitMessage(0, 100, 0));
    }

    public function test_saving_a_meal_sends_a_push_when_it_crosses_the_calorie_limit(): void
    {
        $user = $this->user(['remind_calorie_limit' => true, 'calorie_goal' => 2000]);

        $this->mock(PushSender::class, function ($mock) {
            $mock->shouldReceive('send')->once()
                ->withArgs(fn (User $u, array $message) => $message['title'] === 'Ai depășit limita zilnică')
                ->andReturn(1);
        });

        $this->actingAs($user)->post('/meals', [
            'date' => CarbonImmutable::today()->toDateString(),
            'items' => [$this->item(['calories' => 2100])],
        ])->assertRedirect();
    }

    public function test_no_calorie_limit_push_when_the_reminder_is_off(): void
    {
        $user = $this->user(['remind_calorie_limit' => false, 'calorie_goal' => 2000]);

        $this->mock(PushSender::class, function ($mock) {
            $mock->shouldNotReceive('send');
        });

        $this->actingAs($user)->post('/meals', [
            'date' => CarbonImmutable::today()->toDateString(),
            'items' => [$this->item(['calories' => 2100])],
        ])->assertRedirect();
    }

    public function test_no_calorie_limit_push_for_a_meal_logged_on_a_past_day(): void
    {
        $user = $this->user(['remind_calorie_limit' => true, 'calorie_goal' => 2000]);

        $this->mock(PushSender::class, function ($mock) {
            $mock->shouldNotReceive('send');
        });

        $this->actingAs($user)->post('/meals', [
            'date' => CarbonImmutable::yesterday()->toDateString(),
            'items' => [$this->item(['calories' => 2100])],
        ])->assertRedirect();
    }

    // --- export and account deletion --------------------------------------------------------

    public function test_export_contains_only_the_users_own_data(): void
    {
        $user = $this->user();
        $this->meal($user);
        $this->meal($this->user(), ['title' => 'Al altcuiva']);
        $user->weightLogs()->create(['date' => CarbonImmutable::today()->toDateString(), 'weight_kg' => 71.2]);

        $response = $this->actingAs($user)->get('/me/export')->assertOk()->assertHeader('content-disposition');
        $data = json_decode($response->streamedContent(), true);

        $this->assertSame($user->email, $data['account']['email']);
        $this->assertCount(1, $data['meals']);
        $this->assertSame('Paste carbonara', $data['meals'][0]['title']);
        $this->assertSame(71.2, $data['weight_logs'][0]['weight_kg']);
    }

    public function test_account_deletion_requires_the_password_and_removes_everything(): void
    {
        Storage::fake('public');
        $user = $this->user();
        $this->meal($user, ['photo_path' => 'meals/'.$user->id.'/x.jpg']);
        Storage::disk('public')->put('meals/'.$user->id.'/x.jpg', 'x');
        $user->weightLogs()->create(['date' => CarbonImmutable::today()->toDateString(), 'weight_kg' => 70]);

        $this->actingAs($user)->delete('/me', ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->assertNotNull(User::find($user->id));

        $this->actingAs($user)->delete('/me', ['password' => 'password'])->assertRedirect('/login');

        $this->assertNull(User::find($user->id));
        $this->assertSame(0, \DB::table('meals')->where('user_id', $user->id)->count());
        $this->assertSame(0, \DB::table('weight_logs')->where('user_id', $user->id)->count());
        Storage::disk('public')->assertMissing('meals/'.$user->id.'/x.jpg');
        $this->assertGuest();
    }
}
