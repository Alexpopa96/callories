<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyLog extends Model
{
    protected $fillable = ['user_id', 'date', 'steps', 'water_ml', 'exercise_calories', 'sleep_minutes'];

    protected function casts(): array
    {
        return [
            'steps' => 'integer',
            'water_ml' => 'integer',
            'exercise_calories' => 'integer',
            'sleep_minutes' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
