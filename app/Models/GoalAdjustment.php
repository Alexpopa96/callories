<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoalAdjustment extends Model
{
    protected $fillable = [
        'user_id', 'window_days', 'avg_intake_calories', 'weight_change_kg',
        'implied_tdee', 'current_calories', 'suggested_calories', 'suggested_macros',
        'message', 'status', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'weight_change_kg' => 'float',
            'suggested_macros' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
