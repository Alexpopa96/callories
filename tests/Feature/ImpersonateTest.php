<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ImpersonateTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        $user = User::factory()->create(['status' => true]);
        $user->assignRole(Role::findOrCreate('user', 'web'));

        return $user->fresh();
    }

    private function admin(): User
    {
        $role = Role::findOrCreate('admin', 'web');
        $role->givePermissionTo(
            Permission::findOrCreate('edit user', 'web'),
            Permission::findOrCreate('view administration', 'web'),
        );

        $admin = User::factory()->create(['status' => true]);
        $admin->assignRole($role);

        return $admin->fresh();
    }

    public function test_a_regular_user_cannot_impersonate_anyone(): void
    {
        $user = $this->user();
        $victim = $this->user();

        $this->actingAs($user)->post("/impersonate/{$victim->id}")->assertForbidden();

        $this->assertAuthenticatedAs($user);
        $this->assertFalse(session()->has('impersonate'));
    }

    public function test_an_admin_can_impersonate_a_user_and_go_back(): void
    {
        $admin = $this->admin();
        $user = $this->user();

        $this->actingAs($admin)->post("/impersonate/{$user->id}")->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);

        $this->post('/exitimpersonate')->assertRedirect('/administration/users');
        $this->assertAuthenticatedAs($admin);
    }

    public function test_admins_cannot_be_impersonated_and_exit_needs_an_impersonation(): void
    {
        $admin = $this->admin();
        $otherAdmin = $this->admin();
        $user = $this->user();

        $this->actingAs($admin)->post("/impersonate/{$otherAdmin->id}")->assertForbidden();
        $this->actingAs($admin)->post("/impersonate/{$admin->id}")->assertForbidden();

        // without a real impersonation, exit must not log anyone in
        $this->actingAs($user)->post('/exitimpersonate')->assertForbidden();
        $this->assertAuthenticatedAs($user);
    }
}
