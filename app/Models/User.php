<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasRoles;

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
        'phone',
        'obs',
        'calorie_goal',
        'steps_goal',
        'water_goal_ml',
        'protein_goal_g',
        'carbs_goal_g',
        'fat_goal_g',
        'sex',
        'birth_date',
        'height_cm',
        'activity_level',
        'goal_type',
        'remind_meals',
        'remind_water',
        'remind_calorie_limit',
        'remind_challenge',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
        'anthropic_api_key',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'birth_date' => 'date:Y-m-d',
            'remind_meals' => 'boolean',
            'remind_water' => 'boolean',
            'remind_calorie_limit' => 'boolean',
            'remind_challenge' => 'boolean',
            'anthropic_api_key' => 'encrypted',
        ];
    }

    public function permissionList()
    {
        return $this->roles
            ->map->permissions
            ->flatten()->pluck('name')->unique();
    }

    public function hasAnthropicKey(): bool
    {
        return filled($this->anthropic_api_key);
    }

    /** The key's last characters, enough to recognise it without exposing it. */
    public function anthropicKeyHint(): ?string
    {
        return $this->hasAnthropicKey() ? '…'.substr($this->anthropic_api_key, -4) : null;
    }

    public function goals(): array
    {
        return [
            'calories' => (int) $this->calorie_goal,
            'steps' => (int) $this->steps_goal,
            'waterMl' => (int) $this->water_goal_ml,
            'proteinG' => $this->protein_goal_g,
            'carbsG' => $this->carbs_goal_g,
            'fatG' => $this->fat_goal_g,
        ];
    }

    public function latestWeight(): ?WeightLog
    {
        return $this->weightLogs()->orderByDesc('date')->first();
    }

    public function meals(): HasMany
    {
        return $this->hasMany(Meal::class);
    }

    public function dailyLogs(): HasMany
    {
        return $this->hasMany(DailyLog::class);
    }

    public function weightLogs(): HasMany
    {
        return $this->hasMany(WeightLog::class);
    }

    public function challenges(): HasMany
    {
        return $this->hasMany(Challenge::class);
    }

    public function activeChallenge(): HasOne
    {
        return $this->hasOne(Challenge::class)->where('status', 'active');
    }

    public function goalAdjustments(): HasMany
    {
        return $this->hasMany(GoalAdjustment::class);
    }

    public function favoriteFoods(): HasMany
    {
        return $this->hasMany(FavoriteFood::class);
    }

    public function workouts(): HasMany
    {
        return $this->hasMany(Workout::class);
    }

    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    public function userRole()
    {
        return $this->belongsTo('Spatie\Permission\Models\Role', 'id', 'id');
    }
}
