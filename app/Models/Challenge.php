<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Challenge extends Model
{
    protected $fillable = [
        'user_id', 'goal', 'status', 'start_weight_kg', 'target_weight_kg', 'days',
        'started_on', 'ends_on', 'calorie_goal', 'protein_goal_g', 'carbs_goal_g',
        'fat_goal_g', 'water_goal_ml', 'daily_calorie_delta', 'adjusted', 'explanation',
        'notified_milestone_pct', 'completed_at', 'abandoned_at',
    ];

    protected function casts(): array
    {
        return [
            'start_weight_kg' => 'float',
            'target_weight_kg' => 'float',
            'started_on' => 'date:Y-m-d',
            'ends_on' => 'date:Y-m-d',
            'adjusted' => 'boolean',
            'completed_at' => 'datetime',
            'abandoned_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function targets(): array
    {
        return [
            'calories' => (int) $this->calorie_goal,
            'proteinG' => (int) $this->protein_goal_g,
            'carbsG' => (int) $this->carbs_goal_g,
            'fatG' => (int) $this->fat_goal_g,
            'waterMl' => (int) $this->water_goal_ml,
        ];
    }

    /**
     * Latest weigh-in since the challenge started, or the start weight if none logged yet.
     */
    public function currentWeightKg(): float
    {
        return (float) ($this->user->weightLogs()
            ->where('date', '>=', $this->started_on->toDateString())
            ->orderByDesc('date')
            ->value('weight_kg') ?? $this->start_weight_kg);
    }

    public function targetReached(): bool
    {
        $current = $this->currentWeightKg();

        return $this->goal === 'lose_weight'
            ? $current <= $this->target_weight_kg
            : $current >= $this->target_weight_kg;
    }

    /**
     * Lazily flips active -> completed once the end date passes or the target is hit.
     */
    public function completeIfDue(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if (CarbonImmutable::today()->lt($this->ends_on) && ! $this->targetReached()) {
            return false;
        }

        $this->update(['status' => 'completed', 'completed_at' => now()]);

        return true;
    }
}
