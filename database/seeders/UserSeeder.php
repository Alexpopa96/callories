<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        collect([
            ['name' => 'Marian Ene', 'email' => 'marian@twm.ro', 'password' => 'twm2025', 'role_id' => 1],
            ['name' => 'Florin Harhata', 'email' => 'florin@twm.ro', 'password' => 'twm2025', 'role_id' => 1],
            ['name' => 'Octavian Gabriel', 'email' => 'octavian@twm.ro', 'password' => 'twm2025', 'role_id' => 1],
            ['name' => 'Alexandru Popa', 'email' => 'alexandru@twm.ro', 'password' => 'twm2025', 'role_id' => 1],
            ['name' => 'Adrian Golubei', 'email' => 'adrian@twm.ro', 'password' => 'twm2025', 'role_id' => 1],
            ['name' => 'Ionel Popa', 'email' => 'ionel@twm.ro', 'password' => 'twm2025', 'role_id' => 1],
            ['name' => 'Diana Harhata', 'email' => 'diana@twm.ro', 'password' => 'twm2025', 'role_id' => 1],
        ])->each(function ($factory) {
            $user = User::factory()->make([
                'name' => $factory['name'],
                'email' => $factory['email'],
            ]);

            $user->password = Hash::make($factory['password']);

            $user->save();

            $user->assignRole(Role::find($factory['role_id']));

        });
    }
}
