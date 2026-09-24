<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Calories\FoodAnalysisException;
use App\Services\Calories\FoodPhotoAnalyzer;
use App\Services\Calories\MealRefiner;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CaloriesTest extends TestCase
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

        return $user;
    }

    private function meal(): array
    {
        return [
            'name' => 'Paste carbonara', 'portion_grams' => 300, 'calories' => 520,
            'protein_g' => 22, 'carbs_g' => 60, 'fat_g' => 20, 'fiber_g' => 3,
        ];
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/today')->assertRedirect('/login');
        $this->postJson('/scan/analyze', ['photo' => UploadedFile::fake()->image('meal.jpg')])->assertUnauthorized();
    }

    public function test_root_redirects_to_today(): void
    {
        $this->actingAs($this->user())->get('/')->assertRedirect('/today');
    }

    public function test_analysis_result_is_returned(): void
    {
        $analysis = [
            'is_food' => true,
            'items' => [$this->meal()],
            'totals' => ['calories' => 520, 'protein_g' => 22, 'carbs_g' => 60, 'fat_g' => 20, 'fiber_g' => 3],
            'confidence' => 'medium',
            'notes' => '',
        ];

        $this->mock(FoodPhotoAnalyzer::class, function ($mock) use ($analysis) {
            $mock->shouldReceive('analyze')->once()->with(\Mockery::type('string'), 'image/jpeg')->andReturn($analysis);
        });

        $this->actingAs($this->user())
            ->postJson('/scan/analyze', ['photo' => UploadedFile::fake()->image('meal.jpg')])
            ->assertOk()
            ->assertJsonPath('totals.calories', 520)
            ->assertJsonPath('items.0.name', 'Paste carbonara');
    }

    public function test_non_image_files_are_rejected(): void
    {
        $this->mock(FoodPhotoAnalyzer::class)->shouldNotReceive('analyze');

        $this->actingAs($this->user())
            ->postJson('/scan/analyze', ['photo' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('photo');
    }

    public function test_analysis_failures_return_a_readable_error(): void
    {
        $this->mock(FoodPhotoAnalyzer::class, function ($mock) {
            $mock->shouldReceive('analyze')->andThrow(new FoodAnalysisException('Serviciul nu este disponibil.'));
        });

        $this->actingAs($this->user())
            ->postJson('/scan/analyze', ['photo' => UploadedFile::fake()->image('meal.jpg')])
            ->assertStatus(502)
            ->assertJsonPath('message', 'Serviciul nu este disponibil.');
    }

    public function test_a_meal_is_recalculated_from_a_remark(): void
    {
        $refined = [
            'is_food' => true,
            'items' => [[...$this->meal(), 'portion_grams' => 150, 'calories' => 260, 'pieces' => 1]],
            'totals' => ['calories' => 260, 'protein_g' => 22, 'carbs_g' => 60, 'fat_g' => 20, 'fiber_g' => 3],
            'confidence' => 'high',
            'notes' => 'Am înjumătățit porția.',
        ];

        $this->mock(MealRefiner::class, function ($mock) use ($refined) {
            $mock->shouldReceive('refine')->once()
                ->with([$this->meal()], 'Am mâncat doar jumătate', 'Am presupus paste fierte.')
                ->andReturn($refined);
        });

        $this->actingAs($this->user())
            ->postJson('/meals/refine', [
                'items' => [$this->meal()],
                'remark' => '  Am mâncat doar jumătate ',
                'notes' => 'Am presupus paste fierte.',
            ])
            ->assertOk()
            ->assertJsonPath('items.0.calories', 260)
            ->assertJsonPath('notes', 'Am înjumătățit porția.');
    }

    public function test_a_remark_is_required_to_recalculate(): void
    {
        $this->mock(MealRefiner::class)->shouldNotReceive('refine');

        $this->actingAs($this->user())
            ->postJson('/meals/refine', ['items' => [$this->meal()], 'remark' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('remark');
    }

    public function test_recalculating_needs_an_api_key(): void
    {
        $this->mock(MealRefiner::class)->shouldNotReceive('refine');

        $this->actingAs($this->user(['anthropic_api_key' => null]))
            ->postJson('/meals/refine', ['items' => [$this->meal()], 'remark' => 'era cu smântână'])
            ->assertForbidden()
            ->assertJsonPath('missing_api_key', true);
    }

    public function test_editing_a_meal_keeps_its_notes_unless_new_ones_are_sent(): void
    {
        $user = $this->user();
        $meal = $user->meals()->create([
            'eaten_on' => CarbonImmutable::today()->toDateString(), 'title' => 'Paste', 'items' => [$this->meal()],
            'calories' => 520, 'protein_g' => 22, 'carbs_g' => 60, 'fat_g' => 20, 'fiber_g' => 3, 'notes' => 'Inițial',
        ]);

        $this->actingAs($user)->put("/meals/{$meal->id}", ['items' => [$this->meal()]])->assertRedirect();
        $this->assertSame('Inițial', $meal->fresh()->notes);

        $this->actingAs($user)->put("/meals/{$meal->id}", ['items' => [$this->meal()], 'notes' => 'Cu smântână'])->assertRedirect();
        $this->assertSame('Cu smântână', $meal->fresh()->notes);
    }

    public function test_a_meal_is_saved_with_recomputed_totals_and_photo(): void
    {
        Storage::fake('public');
        $user = $this->user();
        $today = CarbonImmutable::today()->toDateString();

        $this->actingAs($user)
            ->post('/meals', [
                'date' => $today,
                'photo' => UploadedFile::fake()->image('meal.jpg'),
                'items' => [$this->meal(), [...$this->meal(), 'name' => 'Salată', 'calories' => 80, 'protein_g' => 2]],
                'confidence' => 'high',
            ])
            ->assertRedirect("/today?date={$today}");

        $meal = $user->meals()->sole();
        $this->assertSame('Paste carbonara, Salată', $meal->title);
        $this->assertSame(600, $meal->calories);
        $this->assertSame(24.0, $meal->protein_g);
        Storage::disk('public')->assertExists($meal->photo_path);
    }

    public function test_meals_cannot_be_saved_for_a_future_day(): void
    {
        $this->actingAs($this->user())
            ->post('/meals', ['date' => CarbonImmutable::tomorrow()->toDateString(), 'items' => [$this->meal()]])
            ->assertSessionHasErrors('date');
    }

    public function test_home_only_shows_the_logged_in_users_stats(): void
    {
        $mine = $this->user();
        $other = $this->user();
        $today = CarbonImmutable::today()->toDateString();

        foreach ([[$mine, 400, 3000, 500], [$other, 900, 9000, 2000]] as [$user, $kcal, $steps, $water]) {
            $user->meals()->create([
                'eaten_on' => $today, 'title' => 'Masă', 'items' => [], 'calories' => $kcal,
                'protein_g' => 10, 'carbs_g' => 20, 'fat_g' => 5, 'fiber_g' => 1,
            ]);
            $user->dailyLogs()->create(['date' => $today, 'steps' => $steps, 'water_ml' => $water]);
        }

        $this->actingAs($mine)->get('/today')
            ->assertInertia(fn ($page) => $page
                ->component('Fit/Home')
                ->where('totals.calories', 400)
                ->where('steps', 3000)
                ->where('waterMl', 500)
                ->has('meals', 1));
    }

    public function test_home_can_show_a_past_day_and_ignores_bad_dates(): void
    {
        $user = $this->user();
        $yesterday = CarbonImmutable::yesterday()->toDateString();
        $user->dailyLogs()->create(['date' => $yesterday, 'steps' => 7500, 'water_ml' => 0]);

        $this->actingAs($user)->get("/today?date={$yesterday}")
            ->assertInertia(fn ($page) => $page->where('date', $yesterday)->where('steps', 7500)->where('isToday', false));

        $this->actingAs($user)->get('/today?date=2999-01-01')
            ->assertInertia(fn ($page) => $page->where('isToday', true));

        $this->actingAs($user)->get('/today?date=nonsense')
            ->assertInertia(fn ($page) => $page->where('isToday', true));
    }

    public function test_water_accumulates_and_never_goes_below_zero(): void
    {
        $user = $this->user();
        $today = CarbonImmutable::today()->toDateString();

        $this->actingAs($user)->put('/log/water', ['date' => $today, 'delta' => 250]);
        $this->actingAs($user)->put('/log/water', ['date' => $today, 'delta' => 500]);
        $this->assertSame(750, $user->dailyLogs()->sole()->water_ml);

        $this->actingAs($user)->put('/log/water', ['date' => $today, 'delta' => -5000]);
        $this->assertSame(0, $user->dailyLogs()->sole()->water_ml);
    }

    public function test_steps_are_set_per_day(): void
    {
        $user = $this->user();
        $today = CarbonImmutable::today()->toDateString();

        $this->actingAs($user)->put('/log/steps', ['date' => $today, 'steps' => 4200]);
        $this->actingAs($user)->put('/log/steps', ['date' => $today, 'steps' => 5100]);

        $this->assertSame(5100, $user->dailyLogs()->sole()->steps);
    }

    public function test_exercise_calories_are_set_per_day_and_can_be_logged_for_a_past_day(): void
    {
        $user = $this->user();
        $today = CarbonImmutable::today()->toDateString();
        $yesterday = CarbonImmutable::yesterday()->toDateString();

        $this->actingAs($user)->put('/log/exercise', ['date' => $today, 'calories' => 300]);
        $this->actingAs($user)->put('/log/exercise', ['date' => $today, 'calories' => 450]);
        $this->assertSame(450, $user->dailyLogs()->where('date', $today)->sole()->exercise_calories);

        $this->actingAs($user)->put('/log/exercise', ['date' => $yesterday, 'calories' => 200]);
        $this->assertSame(200, $user->dailyLogs()->where('date', $yesterday)->sole()->exercise_calories);
    }

    public function test_sleep_is_set_per_day_and_shows_on_home_and_history(): void
    {
        $user = $this->user();
        $today = CarbonImmutable::today()->toDateString();
        $yesterday = CarbonImmutable::yesterday()->toDateString();

        $this->actingAs($user)->put('/log/sleep', ['date' => $today, 'minutes' => 390])->assertRedirect();
        $this->actingAs($user)->put('/log/sleep', ['date' => $today, 'minutes' => 450]);
        $this->actingAs($user)->put('/log/sleep', ['date' => $yesterday, 'minutes' => 360]);
        $this->assertSame(450, $user->dailyLogs()->where('date', $today)->sole()->sleep_minutes);

        $this->actingAs($user)->put('/log/sleep', ['date' => $today, 'minutes' => 1500])->assertSessionHasErrors('minutes');
        $this->actingAs($user)->put('/log/sleep', ['date' => CarbonImmutable::tomorrow()->toDateString(), 'minutes' => 400])->assertSessionHasErrors('date');

        $this->actingAs($user)->get('/today')
            ->assertInertia(fn ($page) => $page->where('sleepMinutes', 450));

        $this->actingAs($user)->get('/history')
            ->assertInertia(fn ($page) => $page
                ->where('averages.sleepMinutes', 405)
                ->where('hits.sleepMinutes', 1)
                ->where('goals.sleepMinutes', 420));
    }

    public function test_exercise_calories_widen_the_calorie_budget_shown_for_the_day(): void
    {
        $user = $this->user(['calorie_goal' => 2000]);
        $today = CarbonImmutable::today()->toDateString();
        $user->dailyLogs()->create(['date' => $today, 'exercise_calories' => 400]);

        $this->actingAs($user)->get('/today')
            ->assertInertia(fn ($page) => $page->where('exerciseCalories', 400)->where('goals.calories', 2000));
    }

    public function test_a_user_cannot_delete_someone_elses_meal(): void
    {
        $owner = $this->user();
        $meal = $owner->meals()->create([
            'eaten_on' => CarbonImmutable::today()->toDateString(), 'title' => 'Masă', 'items' => [], 'calories' => 300,
        ]);

        $this->actingAs($this->user())->delete("/meals/{$meal->id}")->assertNotFound();
        $this->assertDatabaseHas('meals', ['id' => $meal->id]);

        $this->actingAs($owner)->delete("/meals/{$meal->id}")->assertRedirect();
        $this->assertDatabaseMissing('meals', ['id' => $meal->id]);
    }

    public function test_history_lists_thirty_days_with_averages(): void
    {
        $user = $this->user();
        $today = CarbonImmutable::today()->toDateString();
        $user->dailyLogs()->create(['date' => $today, 'steps' => 8000, 'water_ml' => 2000]);

        $this->actingAs($user)->get('/history')
            ->assertInertia(fn ($page) => $page
                ->component('Fit/History')
                ->has('days', 30)
                ->where('averages.steps', 8000)
                ->where('averages.waterMl', 2000));
    }

    public function test_goals_can_be_updated(): void
    {
        $user = $this->user();

        $this->actingAs($user)->put('/me/goals', ['calories' => 2400, 'steps' => 12000, 'waterMl' => 3000])
            ->assertRedirect();

        $user->refresh();
        $this->assertSame([2400, 12000, 3000], [$user->calorie_goal, $user->steps_goal, $user->water_goal_ml]);

        $this->actingAs($user)->put('/me/goals', ['calories' => 10, 'steps' => 12000, 'waterMl' => 3000])
            ->assertSessionHasErrors('calories');
    }

    public function test_new_registrations_get_the_user_role(): void
    {
        $this->post('/register', [
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'password' => 'parola-foarte-lunga-123',
            'password_confirmation' => 'parola-foarte-lunga-123',
        ])->assertRedirect('/today');

        $this->assertTrue(User::where('email', 'ana@example.com')->firstOrFail()->hasRole('user'));
    }

    public function test_new_account_starts_at_zero(): void
    {
        $this->post('/register', [
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'password' => 'parola-foarte-lunga-123',
            'password_confirmation' => 'parola-foarte-lunga-123',
        ]);

        $this->get('/today')->assertInertia(fn ($page) => $page
            ->component('Fit/Home')
            ->where('totals', ['calories' => 0, 'protein' => 0, 'carbs' => 0, 'fat' => 0, 'fiber' => 0])
            ->where('steps', 0)
            ->where('waterMl', 0)
            ->has('meals', 0));

        $this->get('/history')->assertOk();
    }
}
