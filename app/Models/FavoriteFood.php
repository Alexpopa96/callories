<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FavoriteFood extends Model
{
    protected $table = 'favorite_foods';

    protected $fillable = ['user_id', 'name', 'portion_grams', 'calories', 'protein_g', 'carbs_g', 'fat_g', 'fiber_g'];

    protected function casts(): array
    {
        return [
            'portion_grams' => 'float',
            'calories' => 'float',
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
}
