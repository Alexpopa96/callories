<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class FitDemoSeeder extends Seeder
{
    /**
     * Accounts for the calorie app: plain `user` role, no admin access.
     * Running it again resets every account to zero (no meals, steps, water, weight, favorites, body data,
     * challenges or goal adjustments).
     */
    public function run(): void
    {
        $role = Role::findOrCreate('user', 'web');

        foreach ([
            ['name' => 'Demo Calorii', 'email' => 'demo@calorii.test'],
            ['name' => 'Alexandru', 'email' => 'alexandru@mail.com'],
            ['name' => 'Andreea', 'email' => 'andreea@mail.com'],
        ] as $account) {
            $user = User::updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => Hash::make('demo1234'),
                    'email_verified_at' => now(),
                    'status' => true,
                    'calorie_goal' => 2000,
                    'steps_goal' => 10000,
                    'water_goal_ml' => 2500,
                    'protein_goal_g' => null,
                    'carbs_goal_g' => null,
                    'fat_goal_g' => null,
                    'sex' => null,
                    'birth_date' => null,
                    'height_cm' => null,
                    'activity_level' => 'light',
                    'goal_type' => 'maintain',
                    'remind_meals' => false,
                    'remind_water' => false,
                ]
            );

            $user->syncRoles([$role]);

            $user->meals()->delete();
            $user->dailyLogs()->delete();
            $user->weightLogs()->delete();
            $user->favoriteFoods()->delete();
            $user->pushSubscriptions()->delete();
            $user->challenges()->delete();
            $user->goalAdjustments()->delete();
        }
    }
}
