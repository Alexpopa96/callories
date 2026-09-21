<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class FitDemoSeeder extends Seeder
{
    /**
     * Demo account for the calorie app: plain `user` role, no admin access.
     * Running it again resets the account to zero (no meals, steps or water).
     */
    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'demo@calorii.test'],
            [
                'name' => 'Demo Calorii',
                'password' => Hash::make('demo1234'),
                'email_verified_at' => now(),
                'status' => true,
                'calorie_goal' => 2000,
                'steps_goal' => 10000,
                'water_goal_ml' => 2500,
            ]
        );

        $user->syncRoles([Role::findOrCreate('user', 'web')]);

        $user->meals()->delete();
        $user->dailyLogs()->delete();
    }
}
