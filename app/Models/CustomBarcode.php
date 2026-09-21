<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomBarcode extends Model
{
    protected $fillable = [
        'code', 'name', 'portion_grams', 'calories', 'protein_g', 'carbs_g', 'fat_g', 'fiber_g', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'portion_grams' => 'integer',
            'calories' => 'integer',
            'protein_g' => 'float',
            'carbs_g' => 'float',
            'fat_g' => 'float',
            'fiber_g' => 'float',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
