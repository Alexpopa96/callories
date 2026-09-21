<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Calories\FoodPhotoAnalyzer;
use App\Services\Fit\GoalCalculator;
use App\Services\Fit\PushSender;
use App\Services\Fit\ReminderPlanner;
use Carbon\CarbonImmutable;
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
        $user = User::factory()->create(['status' => true, ...$attributes]);
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
            ->where('hits', ['calories' => 1, 'steps' => 1, 'waterMl' => 1])
            ->where('averages.calories', 2000)
            ->where('averages30.calories', 2750)
            ->where('days.0.protein', 100));
    }

    // --- day strip ------------------------------------------------------------------------

    public function test_day_strip_is_the_calendar_week_monday_to_sunday(): void
    {
        $user = $this->user();
        $today = CarbonImmutable::today();
        $monday = $today->startOfWeek(\Carbon\CarbonInterface::MONDAY);

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
        $monday = $old->startOfWeek(\Carbon\CarbonInterface::MONDAY);

        $this->actingAs($user)->get('/today?date='.$old->toDateString())->assertInertia(fn ($page) => $page
            ->where('strip.0.date', $monday->toDateString())
            ->where('strip.6.date', $monday->addDays(6)->toDateString())
            ->where('strip.6.future', false)
            ->where('week.prev', $old->subWeek()->toDateString())
            ->where('week.next', $old->addWeek()->toDateString()));

        // moving forward never goes past today
        $lastWeek = $today->subWeek()->startOfWeek(\Carbon\CarbonInterface::MONDAY)->addDays(6);
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

        $this->actingAs($user)->put('/me/reminders', ['meals' => true, 'water' => false])->assertRedirect();

        $this->assertTrue($user->fresh()->remind_meals);
        $this->assertFalse($user->fresh()->remind_water);
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
