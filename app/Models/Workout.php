<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Workout extends Model
{
    protected $fillable = ['user_id', 'date', 'title', 'exercises'];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'exercises' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
