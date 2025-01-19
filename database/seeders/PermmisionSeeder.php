<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermmisionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        collect([
            ['name' => 'view dashboard', 'group' => 'dashboard', 'parent' => ''],
            ['name' => 'view administration', 'group' => 'administration', 'parent' => 'administration'],
            ['name' => 'view users', 'group' => 'users', 'parent' => 'administration'],
            ['name' => 'create user', 'group' => 'users', 'parent' => 'administration'],
            ['name' => 'edit user', 'group' => 'users', 'parent' => 'administration'],
            ['name' => 'view roles', 'group' => 'roles', 'parent' => 'administration'],
            ['name' => 'create role', 'group' => 'roles', 'parent' => 'administration'],
            ['name' => 'edit role', 'group' => 'roles', 'parent' => 'administration'],
            ['name' => 'view permissions', 'group' => 'permissions', 'parent' => 'administration'],
            ['name' => 'create permission', 'group' => 'permissions', 'parent' => 'administration'],
            ['name' => 'edit permission', 'group' => 'permissions', 'parent' => 'administration'],
        ])->each(function ($factory) {
            Permission::create($factory);
        });

        Role::get()->each(function ($role) {

            if ($role->name === 'admin') {
                $role->syncPermissions(Permission::whereIn('group', [
                    'dashboard', 'administration', 'users', 'roles', 'permissions',
                ])->pluck('id'));
            } elseif ($role->name === 'supervisor') {
                $role->syncPermissions(Permission::whereIn('group', [
                    'dashboard', 'administration', 'users',
                ])->pluck('id'));
            } elseif ($role->name === 'user') {
                $role->syncPermissions(Permission::whereIn('name', [
                    'view dashboard'
                ])->pluck('id'));
            }
        });
    }
}
