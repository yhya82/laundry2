<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PermissionsAndRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Complements SecurityRegressionTest, which covers the privilege-escalation
 * guards -- this covers the ordinary, non-adversarial paths through the same
 * controller.
 */
class UsersAndRolesTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionsAndRolesSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->syncRoles(['Admin']);
        $this->actingAs($this->admin);
    }

    public function test_a_new_staff_user_can_be_created_with_a_role(): void
    {
        $response = $this->post(route('users.store'), [
            'name' => 'New Staff',
            'email' => 'newstaff@example.com',
            'password' => 'a-reasonably-long-password',
            'password_confirmation' => 'a-reasonably-long-password',
            'roles' => ['Laundry'],
        ]);
        $response->assertSessionDoesntHaveErrors();

        $user = User::where('email', 'newstaff@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('Laundry'));
        $this->assertTrue($user->is_active, 'A newly created user should default to active.');
    }

    public function test_a_weak_password_is_rejected_on_user_creation(): void
    {
        $response = $this->post(route('users.store'), [
            'name' => 'Weak Pw',
            'email' => 'weak@example.com',
            'password' => '1234567', // 7 chars, under Password::defaults()' 8-char minimum
            'password_confirmation' => '1234567',
            'roles' => ['Laundry'],
        ]);
        $response->assertSessionHasErrors('password');
        $this->assertNull(User::where('email', 'weak@example.com')->first());
    }

    public function test_a_user_cannot_be_created_without_at_least_one_role(): void
    {
        $response = $this->post(route('users.store'), [
            'name' => 'No Role',
            'email' => 'norole@example.com',
            'password' => 'a-reasonably-long-password',
            'password_confirmation' => 'a-reasonably-long-password',
            'roles' => [],
        ]);
        $response->assertSessionHasErrors('roles');
    }

    public function test_a_user_can_be_deactivated_and_reactivated(): void
    {
        $staff = User::factory()->create();
        $staff->syncRoles(['Laundry']);

        $this->post(route('users.toggleActive', $staff));
        $this->assertFalse($staff->fresh()->is_active);

        $this->post(route('users.toggleActive', $staff));
        $this->assertTrue($staff->fresh()->is_active);
    }

    public function test_an_admin_cannot_deactivate_their_own_account(): void
    {
        $response = $this->post(route('users.toggleActive', $this->admin));
        $response->assertSessionHasErrors('user');
        $this->assertTrue($this->admin->fresh()->is_active);
    }

    public function test_a_deactivated_users_session_is_terminated_on_their_next_request(): void
    {
        $staff = User::factory()->create();
        $staff->syncRoles(['Laundry']);

        $this->post(route('users.toggleActive', $staff)); // as the admin, deactivating $staff

        // actingAs() uses whatever's already loaded on this instance --
        // $staff was fetched before the toggle, so it's still is_active=true
        // in memory even though the DB row just flipped. Same instance-vs-DB
        // trap fixed in EnsureUserIsActive during the security review.
        $this->actingAs($staff->fresh());
        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_a_new_role_can_be_created_and_given_permissions(): void
    {
        $response = $this->post(route('roles.store'), ['name' => 'Supervisor']);
        $response->assertSessionDoesntHaveErrors();

        $role = Role::where('name', 'Supervisor')->first();
        $this->assertNotNull($role);

        $response = $this->put(route('roles.permissions.update', $role), [
            'permissions' => ['orders.view', 'orders.manage'],
        ]);
        $response->assertSessionDoesntHaveErrors();

        $role->refresh();
        $this->assertTrue($role->hasPermissionTo('orders.view'));
        $this->assertTrue($role->hasPermissionTo('orders.manage'));
        $this->assertFalse($role->hasPermissionTo('users.manage'));
    }

    public function test_the_admin_role_cannot_have_users_manage_removed(): void
    {
        $adminRole = Role::where('name', 'Admin')->first();

        $response = $this->put(route('roles.permissions.update', $adminRole), [
            'permissions' => ['orders.view'], // omits users.manage
        ]);
        $response->assertSessionHasErrors('permissions');

        $adminRole->refresh();
        $this->assertTrue($adminRole->hasPermissionTo('users.manage'), 'Removing users.manage from Admin would lock every administrator out with no way back.');
    }

    public function test_an_admin_cannot_remove_their_own_last_role(): void
    {
        $response = $this->put(route('users.roles.update', $this->admin), ['roles' => []]);
        $response->assertSessionHasErrors('roles');

        $this->assertTrue($this->admin->fresh()->hasRole('Admin'));
    }
}
