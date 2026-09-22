<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Fit\AdaptiveGoalCalculator;
use App\Services\Fit\DayStats;
use App\Services\Fit\GoalCalculator;
use App\Services\Fit\PushSender;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdaptiveGoalTest extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $today;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->today = CarbonImmutable::parse('2026-09-22');
    }

    private function user(array $attributes = []): User
    {
        $user = User::factory()->create([
            'status' => true,
            'sex' => 'f',
            'birth_date' => '1995-05-10',
            'height_cm' => 165,
            'activity_level' => 'light',
            'goal_type' => 'lose',
            'calorie_goal' => 1400,
            ...$attributes,
        ]);
        $user->assignRole(Role::findOrCreate('user', 'web'));

        return $user->fresh();
    }

    private function calculator(): AdaptiveGoalCalculator
    {
        return new AdaptiveGoalCalculator(new GoalCalculator, new DayStats);
    }

    private function seedFlatWeight(User $user, array $offsets, float $weightKg = 65.0): void
    {
        foreach ($offsets as $offset) {
            $user->weightLogs()->create([
                'date' => $this->today->subDays(13 - $offset)->toDateString(),
                'weight_kg' => $weightKg,
            ]);
        }
    }

    private function seedMeals(User $user, int $days, int $calories = 2200): void
    {
        for ($i = 0; $i < $days; $i++) {
            $user->meals()->create([
                'eaten_on' => $this->today->subDays($i)->toDateString(),
                'title' => 'Meal',
                'items' => [],
                'calories' => $calories,
            ]);
        }
    }

    public function test_returns_null_without_enough_weight_logs(): void
    {
        $user = $this->user();
        $this->seedFlatWeight($user, [0, 13]);
        $this->seedMeals($user, 14);

        $this->assertNull($this->calculator()->evaluate($user, $this->today));
    }

    public function test_returns_null_without_enough_logged_meal_days(): void
    {
        $user = $this->user();
        $this->seedFlatWeight($user, [0, 1, 2, 3, 13]);
        $this->seedMeals($user, 5);

        $this->assertNull($this->calculator()->evaluate($user, $this->today));
    }

    /**
     * Weight stalls (flat, no real movement) while intake stays at 2200 kcal/day for the whole
     * window: the actual maintenance is much higher than the goal (1400 kcal, "lose" preset)
     * assumed, so the calculator should suggest raising the goal.
     */
    public function test_detects_a_stalled_weight_and_suggests_a_higher_goal(): void
    {
        $user = $this->user(['calorie_goal' => 1400]);
        $this->seedFlatWeight($user, [0, 1, 2, 3, 13]);
        $this->seedMeals($user, 14, 2200);

        $result = $this->calculator()->evaluate($user, $this->today);

        $this->assertNotNull($result);
        $this->assertSame(2200, $result['avgIntake']);
        $this->assertSame(0.0, $result['weightChangeKg']);
        $this->assertSame(2200, $result['impliedTdee']);
        $this->assertSame(1400, $result['currentCalories']);
        $this->assertSame(1700, $result['suggestedCalories']);
        $this->assertStringContainsString('aproape neschimbată', $result['message']);
        $this->assertStringContainsString('urcăm obiectivul la 1700 kcal', $result['message']);
    }

    public function test_returns_null_when_the_divergence_is_too_small(): void
    {
        $user = $this->user(['calorie_goal' => 1650]);
        $this->seedFlatWeight($user, [0, 1, 2, 3, 13]);
        $this->seedMeals($user, 14, 2200);

        $this->assertNull($this->calculator()->evaluate($user, $this->today));
    }

    public function test_review_command_creates_a_suggestion_and_sends_a_push(): void
    {
        $this->travelTo($this->today);
        config(['services.webpush.public_key' => 'pub', 'services.webpush.private_key' => 'priv']);

        $user = $this->user(['calorie_goal' => 1400]);
        $this->seedFlatWeight($user, [0, 1, 2, 3, 13]);
        $this->seedMeals($user, 14, 2200);

        $this->mock(PushSender::class, function ($mock) use ($user) {
            $mock->shouldReceive('send')->once()
                ->withArgs(fn (User $sent, array $message) => $sent->is($user) && $message['url'] === '/me')
                ->andReturn(1);
        });

        $this->artisan('fit:review-calorie-goals')->expectsOutput('Suggested 1 goal adjustment(s).')->assertSuccessful();

        $this->assertDatabaseHas('goal_adjustments', [
            'user_id' => $user->id,
            'status' => 'pending',
            'suggested_calories' => 1700,
        ]);
    }

    public function test_review_command_does_not_re_suggest_right_after_a_recent_adjustment(): void
    {
        $this->travelTo($this->today);
        config(['services.webpush.public_key' => 'pub', 'services.webpush.private_key' => 'priv']);

        $user = $this->user(['calorie_goal' => 1400]);
        $this->seedFlatWeight($user, [0, 1, 2, 3, 13]);
        $this->seedMeals($user, 14, 2200);

        $adjustment = $user->goalAdjustments()->create([
            'window_days' => 14, 'avg_intake_calories' => 2200, 'weight_change_kg' => 0,
            'implied_tdee' => 2200, 'current_calories' => 1400, 'suggested_calories' => 1700,
            'suggested_macros' => [], 'message' => 'previous suggestion', 'status' => 'dismissed',
            'resolved_at' => now(),
        ]);
        DB::table('goal_adjustments')->where('id', $adjustment->id)->update(['created_at' => now()->subDays(2)]);

        $this->mock(PushSender::class, fn ($mock) => $mock->shouldNotReceive('send'));

        $this->artisan('fit:review-calorie-goals')->expectsOutput('Suggested 0 goal adjustment(s).')->assertSuccessful();
        $this->assertSame(1, $user->goalAdjustments()->count());
    }

    public function test_applying_the_adaptive_suggestion_updates_the_goals(): void
    {
        $user = $this->user(['calorie_goal' => 1400]);
        $adjustment = $user->goalAdjustments()->create([
            'window_days' => 14, 'avg_intake_calories' => 2200, 'weight_change_kg' => 0,
            'implied_tdee' => 2200, 'current_calories' => 1400, 'suggested_calories' => 1700,
            'suggested_macros' => ['calories' => 1700, 'proteinG' => 130, 'carbsG' => 150, 'fatG' => 47, 'waterMl' => 2300],
            'message' => 'test message', 'status' => 'pending',
        ]);

        $this->actingAs($user)->post('/me/goals/adaptive/apply')->assertSessionHas('success');

        $user->refresh();
        $this->assertSame(1700, $user->calorie_goal);
        $this->assertSame(130, $user->protein_goal_g);
        $this->assertSame('applied', $adjustment->fresh()->status);
    }

    public function test_dismissing_the_adaptive_suggestion_leaves_goals_untouched(): void
    {
        $user = $this->user(['calorie_goal' => 1400]);
        $adjustment = $user->goalAdjustments()->create([
            'window_days' => 14, 'avg_intake_calories' => 2200, 'weight_change_kg' => 0,
            'implied_tdee' => 2200, 'current_calories' => 1400, 'suggested_calories' => 1700,
            'suggested_macros' => ['calories' => 1700, 'proteinG' => 130, 'carbsG' => 150, 'fatG' => 47, 'waterMl' => 2300],
            'message' => 'test message', 'status' => 'pending',
        ]);

        $this->actingAs($user)->post('/me/goals/adaptive/dismiss')->assertSessionHas('success');

        $user->refresh();
        $this->assertSame(1400, $user->calorie_goal);
        $this->assertSame('dismissed', $adjustment->fresh()->status);
    }

    public function test_profile_page_exposes_the_pending_suggestion(): void
    {
        $user = $this->user();
        $user->goalAdjustments()->create([
            'window_days' => 14, 'avg_intake_calories' => 2200, 'weight_change_kg' => 0,
            'implied_tdee' => 2200, 'current_calories' => 1400, 'suggested_calories' => 1700,
            'suggested_macros' => ['calories' => 1700, 'proteinG' => 130, 'carbsG' => 150, 'fatG' => 47, 'waterMl' => 2300],
            'message' => 'test message', 'status' => 'pending',
        ]);

        $this->actingAs($user)->get('/me')->assertInertia(fn ($page) => $page
            ->where('adaptiveSuggestion.message', 'test message')
            ->where('adaptiveSuggestion.suggested_calories', 1700));
    }
}
