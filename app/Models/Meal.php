<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Meal extends Model
{
    protected $fillable = [
        'user_id', 'eaten_on', 'title', 'photo_path', 'items', 'calories',
        'protein_g', 'carbs_g', 'fat_g', 'fiber_g', 'confidence', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'calories' => 'integer',
            'protein_g' => 'float',
            'carbs_g' => 'float',
            'fat_g' => 'float',
            'fiber_g' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path ? '/storage/'.$this->photo_path : null;
    }
}
