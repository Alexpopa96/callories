<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        collect([
            ['name' => 'admin', 'slug' => 'Admin'],
            ['name' => 'supervisor', 'slug' => 'Supervisor'],
            ['name' => 'user', 'slug' => 'User']
        ])->each(function ($factory) {
            $role = Role::make([
                'name' => $factory['name'],
                'guard_name' => 'web',
            ]);

            $role->save();
        });
    }
}
