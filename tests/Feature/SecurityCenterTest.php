<?php

namespace Tests\Feature;

use App\Http\Middleware\ExplainSignOut;
use App\Listeners\RecordAuthenticationEvents;
use App\Models\LoginEvent;
use App\Models\Notification;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\PermissionsAndRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Settings → Security: password policy, history, forced changes, lockout,
 * login log, Admin password changes, signed-in devices.
 */
class SecurityCenterTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionsAndRolesSeeder::class);
        $this->admin = User::factory()->create(['email' => 'admin@laundry.test']);
        $this->admin->syncRoles(['Admin']);
    }

    protected function security(string $key, string $value, string $type = 'integer'): void
    {
        Setting::set("security.{$key}", $value, 'security', $type);
    }

    protected function changeOwnPassword(User $user, string $current, string $new)
    {
        return $this->actingAs($user)->from('/profile')->put('/password', [
            'current_password' => $current,
            'password' => $new,
            'password_confirmation' => $new,
        ]);
    }

    protected function addSession(User $user, string $id, string $agent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Safari/537.36'): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'ip_address' => '10.0.0.5',
            'user_agent' => $agent,
            'payload' => base64_encode(serialize([])),
            'last_activity' => now()->getTimestamp(),
        ]);
    }

    // ---- Password policy ---------------------------------------------------

    public function test_the_default_minimum_is_8_characters(): void
    {
        $user = User::factory()->create();

        $this->changeOwnPassword($user, 'password', 'short7!')->assertSessionHasErrorsIn('updatePassword', 'password');
        $this->changeOwnPassword($user, 'password', 'eightchr')->assertSessionHasNoErrors();
    }

    public function test_the_configured_rules_apply_to_every_password_form(): void
    {
        $this->security('password_min_length', '12');
        $this->security('password_require_numbers', 'true', 'boolean');
        $this->security('password_require_mixed_case', 'true', 'boolean');

        $user = User::factory()->create();

        $this->changeOwnPassword($user, 'password', 'alllowercase1')->assertSessionHasErrorsIn('updatePassword', 'password');
        $this->changeOwnPassword($user, 'password', 'NoNumbersHere')->assertSessionHasErrorsIn('updatePassword', 'password');
        $this->changeOwnPassword($user, 'password', 'Short1')->assertSessionHasErrorsIn('updatePassword', 'password');
        $this->changeOwnPassword($user, 'password', 'GoodPassword12')->assertSessionHasNoErrors();

        // The Admin "create user" form follows the same policy.
        $this->actingAs($this->admin)->post(route('users.store'), [
            'name' => 'New Person',
            'email' => 'new@laundry.test',
            'phone' => '7001234',
            'password' => 'weakpassword',
            'password_confirmation' => 'weakpassword',
            'roles' => ['Laundry'],
        ])->assertSessionHasErrors('password');
    }

    // ---- Password history --------------------------------------------------

    public function test_the_current_and_last_three_passwords_cannot_be_reused(): void
    {
        $user = User::factory()->create(); // 'password'

        $this->changeOwnPassword($user, 'password', 'password')->assertSessionHasErrorsIn('updatePassword', 'password');

        $this->changeOwnPassword($user, 'password', 'second-pass')->assertSessionHasNoErrors();
        $this->changeOwnPassword($user->fresh(), 'second-pass', 'third-pass')->assertSessionHasNoErrors();
        $this->changeOwnPassword($user->fresh(), 'third-pass', 'fourth-pass')->assertSessionHasNoErrors();

        // 'password' is 3 back -- still blocked.
        $this->changeOwnPassword($user->fresh(), 'fourth-pass', 'password')->assertSessionHasErrorsIn('updatePassword', 'password');

        // One more change pushes 'password' 4 back -- allowed again.
        $this->changeOwnPassword($user->fresh(), 'fourth-pass', 'fifth-pass')->assertSessionHasNoErrors();
        $this->changeOwnPassword($user->fresh(), 'fifth-pass', 'password')->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
        // Only hashes are stored, never the password itself.
        $this->assertDatabaseMissing('password_histories', ['password' => 'password']);
    }

    // ---- Forced changes ----------------------------------------------------

    public function test_a_new_account_must_choose_its_own_password_before_anything_else(): void
    {
        $this->actingAs($this->admin)->post(route('users.store'), [
            'name' => 'New Person',
            'email' => 'new@laundry.test',
            'phone' => '7001234',
            'password' => 'temporary-pass',
            'password_confirmation' => 'temporary-pass',
            'roles' => ['Laundry'],
        ])->assertSessionHasNoErrors();

        $user = User::where('email', 'new@laundry.test')->firstOrFail();
        $this->assertTrue($user->must_change_password);

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('password.change'));
        $this->get(route('password.change'))->assertOk()->assertSee('Your password was set by an administrator.');

        // Reusing the temporary password isn't allowed.
        $this->put(route('password.change.update'), [
            'current_password' => 'temporary-pass',
            'password' => 'temporary-pass',
            'password_confirmation' => 'temporary-pass',
        ])->assertSessionHasErrors('password');

        $this->put(route('password.change.update'), [
            'current_password' => 'temporary-pass',
            'password' => 'my-own-password',
            'password_confirmation' => 'my-own-password',
        ])->assertRedirect(route('dashboard'));

        $this->assertFalse($user->fresh()->must_change_password);
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_an_expired_password_must_be_changed_and_expiry_can_be_turned_off(): void
    {
        $user = User::factory()->create();

        $user->forceFill(['password_changed_at' => now()->subDays(89)])->saveQuietly();
        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $user->forceFill(['password_changed_at' => now()->subDays(91)])->saveQuietly();
        $this->actingAs($user->fresh())->get(route('dashboard'))->assertRedirect(route('password.change'));
        $this->get(route('password.change'))->assertSee('Your password has expired.');

        $this->security('password_expiry_days', '0');
        $this->actingAs($user->fresh())->get(route('dashboard'))->assertOk();
    }

    public function test_a_new_password_restarts_the_expiry_clock(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['password_changed_at' => now()->subDays(100)])->saveQuietly();

        // Expired, so they land on the change screen (Profile is out of reach until then).
        $this->actingAs($user)->put(route('password.change.update'), [
            'current_password' => 'password',
            'password' => 'a-fresh-password',
            'password_confirmation' => 'a-fresh-password',
        ])->assertRedirect(route('dashboard'));

        $this->assertTrue($user->fresh()->password_changed_at->isToday());
        $this->assertFalse($user->fresh()->passwordExpired());
    }

    // ---- Login -------------------------------------------------------------

    public function test_a_wrong_password_shows_a_clear_message_with_attempts_left_and_is_logged(): void
    {
        $user = User::factory()->create(['email' => 'awa@laundry.test']);

        $this->from('/login')->post('/login', ['email' => 'awa@laundry.test', 'password' => 'nope'])
            ->assertSessionHasErrors(['login' => 'Incorrect email or password.'])
            ->assertSessionHasErrors(['login_hint' => '4 attempts left before this account is locked for 15 minutes.']);

        $this->assertGuest();
        $this->assertDatabaseHas('login_events', [
            'event' => 'failed',
            'reason' => 'wrong_password',
            'email' => 'awa@laundry.test',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
        ]);
    }

    public function test_an_unknown_email_gets_the_same_message_but_is_logged_as_unknown(): void
    {
        $this->from('/login')->post('/login', ['email' => 'nobody@laundry.test', 'password' => 'nope'])
            ->assertSessionHasErrors(['login' => 'Incorrect email or password.']);

        $this->assertDatabaseHas('login_events', ['event' => 'failed', 'reason' => 'unknown_email', 'email' => 'nobody@laundry.test', 'user_id' => null]);
    }

    public function test_empty_fields_get_their_own_messages(): void
    {
        $this->from('/login')->post('/login', ['email' => '', 'password' => ''])
            ->assertSessionHasErrors(['email' => 'Enter your email address.', 'password' => 'Enter your password.']);
    }

    public function test_the_account_locks_after_the_configured_attempts_and_admins_are_notified_once(): void
    {
        $this->security('lockout_attempts', '3');
        $this->security('lockout_minutes', '10');
        User::factory()->create(['email' => 'awa@laundry.test']);

        $this->post('/login', ['email' => 'awa@laundry.test', 'password' => 'nope-1'])->assertSessionHasErrors(['login_hint' => '2 attempts left before this account is locked for 10 minutes.']);
        $this->post('/login', ['email' => 'awa@laundry.test', 'password' => 'nope-2']);
        $this->post('/login', ['email' => 'awa@laundry.test', 'password' => 'nope-3'])
            ->assertSessionHasErrors(['login' => 'Too many failed attempts.', 'login_hint' => 'This account is locked. Try again in 10 minutes.']);

        // Even the right password is refused while locked.
        $this->post('/login', ['email' => 'awa@laundry.test', 'password' => 'password'])
            ->assertSessionHasErrors(['login' => 'Too many failed attempts.']);
        $this->assertGuest();

        $this->assertSame(2, LoginEvent::where('event', 'lockout')->count());
        $this->assertSame(1, Notification::where('user_id', $this->admin->id)->where('title', 'Account locked')->count(), 'Admins are told once per lockout, not per attempt.');
        $this->assertStringContainsString('awa@laundry.test', Notification::where('title', 'Account locked')->value('body'));
    }

    public function test_a_deactivated_account_is_refused_before_signing_in(): void
    {
        $user = User::factory()->create(['email' => 'gone@laundry.test', 'is_active' => false]);

        $this->post('/login', ['email' => 'gone@laundry.test', 'password' => 'password'])
            ->assertSessionHasErrors(['login' => 'This account has been deactivated. Contact an administrator.']);

        $this->assertGuest();
        $this->assertDatabaseHas('login_events', ['event' => 'failed', 'reason' => 'deactivated', 'user_id' => $user->id]);
        $this->assertDatabaseMissing('login_events', ['event' => 'login', 'user_id' => $user->id]);
    }

    public function test_a_successful_login_is_logged(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('login_events', ['event' => 'login', 'user_id' => $user->id]);
    }

    public function test_the_login_page_explains_an_admin_or_idle_sign_out(): void
    {
        Cache::put(ExplainSignOut::REVOKED_CACHE_PREFIX.'old-session-id', true, now()->addHour());

        $this->withCookie(config('session.cookie'), 'old-session-id')
            ->get('/login')
            ->assertSee('Your session was ended by an administrator.');

        $this->flushSession();
        $this->withCookies([config('session.cookie') => 'other-id', RecordAuthenticationEvents::SIGNED_IN_COOKIE => '1'])
            ->get('/login')
            ->assertSee('You were signed out after 120 minutes of inactivity.');
    }

    public function test_the_login_page_has_a_show_password_toggle(): void
    {
        $this->get('/login')->assertOk()->assertSee('Show password');
    }

    public function test_the_idle_timeout_setting_drives_the_session_lifetime(): void
    {
        $this->security('idle_timeout_minutes', '30');

        $this->get('/login');

        $this->assertSame(30, config('session.lifetime'));
    }

    // ---- Admin password changes --------------------------------------------

    public function test_admins_can_change_another_users_password_from_the_users_list(): void
    {
        $user = User::factory()->create(['email' => 'staff@laundry.test']);
        $this->addSession($user, 'staff-session-1');
        $this->addSession($user, 'staff-session-2');
        $oldToken = $user->remember_token;

        $this->actingAs($this->admin)->get(route('users.index'))
            ->assertOk()
            ->assertSee('user-password-'.$user->id)
            ->assertSee('user-password-'.$this->admin->id)
            ->assertSee('Change my password');

        $this->put(route('users.setPassword', $user), [
            'password' => 'temporary-123',
            'password_confirmation' => 'temporary-123',
        ])->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertTrue(Hash::check('temporary-123', $user->password));
        $this->assertTrue($user->must_change_password);
        $this->assertNotSame($oldToken, $user->remember_token, 'Remember-me devices are signed out too.');
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->assertDatabaseHas('login_events', ['event' => 'password_reset_by_admin', 'user_id' => $user->id, 'actor_id' => $this->admin->id]);
    }

    public function test_an_admin_changes_their_own_password_from_the_users_list_with_their_current_one(): void
    {
        $this->addSession($this->admin, 'admin-other-device');
        $bag = "setPassword{$this->admin->id}";

        // Wrong current password: refused.
        $this->actingAs($this->admin)->put(route('users.setPassword', $this->admin), [
            'current_password' => 'not-it',
            'password' => 'my-new-admin-pass',
            'password_confirmation' => 'my-new-admin-pass',
        ])->assertSessionHasErrorsIn($bag, 'current_password');

        // Reusing the current one: refused by the history rule.
        $this->put(route('users.setPassword', $this->admin), [
            'current_password' => 'password',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrorsIn($bag, 'password');

        $this->put(route('users.setPassword', $this->admin), [
            'current_password' => 'password',
            'password' => 'my-new-admin-pass',
            'password_confirmation' => 'my-new-admin-pass',
        ])->assertSessionHasNoErrors()->assertSessionHas('status', 'Your password was changed.');

        $admin = $this->admin->fresh();
        $this->assertTrue(Hash::check('my-new-admin-pass', $admin->password));
        $this->assertFalse($admin->must_change_password, 'Your own new password is not temporary.');
        $this->get(route('dashboard'))->assertOk(); // still signed in, no forced change
        $this->assertDatabaseMissing('login_events', ['event' => 'password_reset_by_admin', 'user_id' => $admin->id]);
    }

    public function test_the_change_password_panels_check_the_policy_live_and_gate_the_button(): void
    {
        $this->security('password_min_length', '12');
        $this->security('password_require_numbers', 'true', 'boolean');
        User::factory()->create();

        $html = $this->actingAs($this->admin)->get(route('users.index'))->assertOk()->getContent();

        // The live rules come from the current policy.
        $this->assertStringContainsString('At least 12 characters', $html);
        $this->assertStringContainsString('At least one number', $html);
        $this->assertStringContainsString('(checked when you save)', $html);
        $this->assertStringContainsString('x-on:password-validity', $html);
        $this->assertStringContainsString('x-bind:disabled="! (policyOk', $html);

        // Both rows (the Admin's own and the other user's) get the same
        // fixed-width button with the same label.
        $this->assertSame(2, substr_count($html, 'inline-flex w-40 items-center justify-center'));
        $this->assertSame(1, substr_count($html, 'aria-label="Change your own password"'));
    }

    public function test_only_admins_see_or_use_the_change_password_action(): void
    {
        $manager = User::factory()->create();
        Role::create(['name' => 'Manager', 'guard_name' => 'web'])->givePermissionTo('users.manage');
        $manager->syncRoles(['Manager']);
        $user = User::factory()->create();

        $this->actingAs($manager)->get(route('users.index'))->assertOk()->assertDontSee('user-password-'.$user->id);
        $this->put(route('users.setPassword', $user), ['password' => 'temporary-123', 'password_confirmation' => 'temporary-123'])->assertForbidden();
    }

    // ---- Security tab and devices ------------------------------------------

    public function test_the_security_tab_is_admin_only(): void
    {
        $this->actingAs($this->admin)->get(route('settings.index', ['tab' => 'security']))
            ->assertOk()
            ->assertSee('Security policy')
            ->assertSee('Signed-in devices');

        $manager = User::factory()->create();
        Role::create(['name' => 'Manager', 'guard_name' => 'web'])->givePermissionTo('settings.manage');
        $manager->syncRoles(['Manager']);

        $this->actingAs($manager)->get(route('settings.index'))->assertOk()->assertDontSee('Security policy');
        $this->put(route('settings.update'), ['group' => 'security', 'password_min_length' => 8])->assertForbidden();
    }

    public function test_admins_save_the_policy_within_its_limits(): void
    {
        $base = [
            'group' => 'security', 'password_min_length' => 10, 'password_expiry_days' => 60, 'password_history' => 5,
            'lockout_attempts' => 4, 'lockout_minutes' => 30, 'idle_timeout_minutes' => 45, 'password_require_symbols' => '1',
        ];

        $this->actingAs($this->admin)->put(route('settings.update'), $base)->assertSessionHasNoErrors();

        $this->assertSame('10', Setting::get('security.password_min_length'));
        $this->assertSame('true', Setting::get('security.password_require_symbols'));
        $this->assertSame('false', Setting::get('security.password_require_numbers'));

        $this->put(route('settings.update'), [...$base, 'password_min_length' => 6])->assertSessionHasErrors('password_min_length');
        $this->put(route('settings.update'), [...$base, 'lockout_attempts' => 1])->assertSessionHasErrors('lockout_attempts');
    }

    public function test_failed_attempts_show_with_email_ip_and_time(): void
    {
        $this->post('/login', ['email' => 'intruder@example.com', 'password' => 'guess']);
        $event = LoginEvent::firstOrFail();

        $this->actingAs($this->admin)->get(route('settings.index', ['tab' => 'security']))
            ->assertSeeInOrder(['Login activity', $event->created_at->format('Y-m-d H:i:s'), 'Failed login', 'Unknown email', 'intruder@example.com', '127.0.0.1']);
    }

    public function test_the_security_score_reflects_the_policy_and_recent_activity(): void
    {
        // Defaults: 8 chars (8) + expiry (10) + history (10) + lockout (10)
        // + 120-min idle (6) + quiet day (10) = 54.
        $default = \App\Support\SecurityScore::calculate(0, 0);
        $this->assertSame(54, $default['score']);
        $this->assertSame('Weak', $default['password_strength']);
        $this->assertSame('Low', $default['suspicious']);
        $this->assertContains('Require at least one number.', $default['tips']);

        // Everything on: 40 + 10 + 10 + 10 + 10 + 10 = 90 (2FA is the last 10).
        $this->security('password_min_length', '12');
        foreach (['mixed_case', 'numbers', 'symbols'] as $rule) {
            $this->security("password_require_{$rule}", 'true', 'boolean');
        }
        $this->security('password_block_breached', 'true', 'boolean');
        $this->security('idle_timeout_minutes', '60');

        $strong = \App\Support\SecurityScore::calculate(0, 0);
        $this->assertSame(90, $strong['score']);
        $this->assertSame('Excellent', $strong['password_strength']);
        $this->assertSame('good', $strong['tone']);

        // A lockout in the last day costs the activity points.
        $busy = \App\Support\SecurityScore::calculate(3, 1);
        $this->assertSame(80, $busy['score']);
        $this->assertSame('High', $busy['suspicious']);
    }

    public function test_the_security_center_header_score_and_tabs_render(): void
    {
        $this->actingAs($this->admin)->get(route('settings.index', ['tab' => 'security']))
            ->assertOk()
            ->assertSeeInOrder(['Security Center', 'Password Policies', 'Refresh', 'Security Score', '54', 'Password Strength', '2FA Status', 'Suspicious Activity'])
            ->assertSeeInOrder(['Overview', 'Policy', 'Devices', 'Login activity', 'Threats'])
            ->assertSee('Improve your score');
    }

    public function test_password_policies_offers_presets_to_create_a_policy_until_one_exists(): void
    {
        $this->actingAs($this->admin)->get(route('settings.index', ['tab' => 'security']))
            ->assertOk()
            ->assertSee('No policy has been created yet')
            ->assertSee('Create password policy')
            ->assertSeeInOrder(['Basic', 'Recommended', 'Strict'])
            ->assertDontSee('Edit policy');
    }

    public function test_every_preset_can_be_saved_as_is(): void
    {
        foreach (\App\Support\SecurityPolicy::PRESETS as $key => $preset) {
            $payload = ['group' => 'security', 'policy_mode' => 'create'];

            foreach ($preset['values'] as $field => $value) {
                if (is_bool($value)) {
                    if ($value) {
                        $payload[$field] = '1';
                    }
                } else {
                    $payload[$field] = $value;
                }
            }

            $this->actingAs($this->admin)->put(route('settings.update'), $payload)
                ->assertSessionHasNoErrors()
                ->assertSessionHas('status', 'Password policy created.');

            $this->assertSame($preset['values'], \App\Support\SecurityPolicy::currentValues(), "Preset [{$key}] should be stored exactly.");
        }
    }

    public function test_once_saved_the_policy_is_edited_and_summarised(): void
    {
        $this->actingAs($this->admin)->put(route('settings.update'), [
            'group' => 'security', 'policy_mode' => 'edit', 'password_min_length' => 14, 'password_expiry_days' => 0,
            'password_history' => 0, 'lockout_attempts' => 4, 'lockout_minutes' => 20, 'idle_timeout_minutes' => 45,
            'password_require_numbers' => '1',
        ])->assertSessionHas('status', 'Password policy updated.');

        $this->get(route('settings.index', ['tab' => 'security']))
            ->assertSee('Edit policy')
            ->assertSee('Start from a preset')
            ->assertDontSee('No policy has been created yet')
            ->assertSee('At least 14 characters')
            ->assertSee('Passwords never expire')
            ->assertSee('Lock after 4 failed attempts')
            ->assertSee('Sign out after 45 idle minutes');
    }

    public function test_signed_in_devices_are_listed_and_can_be_signed_out(): void
    {
        $user = User::factory()->create(['name' => 'Fatou Sowe']);
        $this->addSession($user, 'fatou-phone', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1');
        $this->addSession($user, 'fatou-laptop');

        $this->actingAs($this->admin)->get(route('settings.index', ['tab' => 'security']))
            ->assertSee('Fatou Sowe')
            ->assertSee('Safari 17 on iOS')
            ->assertSee('Chrome 131 on Windows');

        $this->delete(route('security.sessions.revoke', 'fatou-phone'))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('sessions', ['id' => 'fatou-phone']);
        $this->assertDatabaseHas('sessions', ['id' => 'fatou-laptop']);
        $this->assertTrue(Cache::has(ExplainSignOut::REVOKED_CACHE_PREFIX.'fatou-phone'));

        $this->delete(route('security.users.sessions.revoke', $user))->assertSessionHasNoErrors();
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->assertSame(2, LoginEvent::where('event', 'sessions_revoked')->where('actor_id', $this->admin->id)->count());
    }

    public function test_people_shows_who_is_signed_in_and_how_others_signed_out(): void
    {
        $event = fn (User $u, string $event, int $minutesAgo) => LoginEvent::create([
            'user_id' => $u->id, 'email' => $u->email, 'event' => $event, 'created_at' => now()->subMinutes($minutesAgo),
        ]);
        $session = function (User $u, string $id, int $minutesAgo) {
            $this->addSession($u, $id);
            DB::table('sessions')->where('id', $id)->update(['last_activity' => now()->subMinutes($minutesAgo)->getTimestamp()]);
        };

        $online = User::factory()->create(['name' => 'Olive Online']);
        $event($online, 'login', 30);
        $session($online, 'olive-1', 1);
        $session($online, 'olive-2', 3);

        $idle = User::factory()->create(['name' => 'Ida Idle']);
        $event($idle, 'login', 60);
        $session($idle, 'ida-1', 20);

        $loggedOut = User::factory()->create(['name' => 'Leo Loggedout']);
        $event($loggedOut, 'login', 90);
        $event($loggedOut, 'logout', 45);

        $byAdmin = User::factory()->create(['name' => 'Abe Byadmin']);
        $event($byAdmin, 'login', 90);
        LoginEvent::create(['user_id' => $byAdmin->id, 'event' => 'sessions_revoked', 'actor_id' => $this->admin->id, 'created_at' => now()->subMinutes(10)]);

        $expired = User::factory()->create(['name' => 'Eve Expired']);
        $event($expired, 'login', 300);

        User::factory()->create(['name' => 'Nora Never']);

        $html = $this->actingAs($this->admin)->get(route('settings.index', ['tab' => 'security', 'sec_tab' => 'people']))
            ->assertOk()
            ->getContent();

        $row = function (string $name) use ($html) {
            $start = strpos($html, '>'.$name.'</a>');
            $this->assertNotFalse($start, "{$name} should be listed.");

            return substr($html, strrpos(substr($html, 0, $start), '<tr'), strpos($html, '</tr>', $start) - strrpos(substr($html, 0, $start), '<tr'));
        };

        $this->assertStringContainsString('data-person-status="online"', $row('Olive Online'));
        $this->assertStringContainsString('2', strip_tags($row('Olive Online')), 'Two devices.');
        $this->assertStringContainsString('data-person-status="idle"', $row('Ida Idle'));
        $this->assertStringContainsString('Logged out', $row('Leo Loggedout'));
        $this->assertStringContainsString('Signed out by an admin', $row('Abe Byadmin'));
        $this->assertStringContainsString('Session expired', $row('Eve Expired'));
        $this->assertStringContainsString('Never signed in', $row('Nora Never'));

        // Signed-in people come first.
        $this->assertLessThan(strpos($html, '>Leo Loggedout</a>'), strpos($html, '>Olive Online</a>'));
        $this->assertLessThan(strpos($html, '>Leo Loggedout</a>'), strpos($html, '>Ida Idle</a>'));
    }

    public function test_people_can_be_filtered_by_status_and_searched(): void
    {
        $online = User::factory()->create(['name' => 'Olive Online']);
        $this->addSession($online, 'olive-1');
        User::factory()->create(['name' => 'Sam Signedout']);

        $this->actingAs($this->admin);

        // Only the People section -- signed-in people also appear under Devices.
        $people = function (array $params) {
            $html = $this->get(route('settings.index', ['tab' => 'security', 'sec_tab' => 'people', ...$params]))->getContent();
            $start = strpos($html, 'id="sec-people"');

            return substr($html, $start, strpos($html, 'id="sec-devices"') - $start);
        };

        $signedIn = $people(['people_status' => 'signed_in']);
        $this->assertStringContainsString('>Olive Online</a>', $signedIn);
        $this->assertStringNotContainsString('>Sam Signedout</a>', $signedIn);

        $signedOut = $people(['people_status' => 'signed_out']);
        $this->assertStringNotContainsString('>Olive Online</a>', $signedOut);
        $this->assertStringContainsString('>Sam Signedout</a>', $signedOut);

        $search = $people(['people_q' => 'sam']);
        $this->assertStringContainsString('>Sam Signedout</a>', $search);
        $this->assertStringNotContainsString('>Olive Online</a>', $search);
    }

    public function test_people_is_admin_only(): void
    {
        $manager = User::factory()->create();
        Role::create(['name' => 'Manager', 'guard_name' => 'web'])->givePermissionTo('settings.manage');
        $manager->syncRoles(['Manager']);

        $this->actingAs($manager)->get(route('settings.index', ['tab' => 'security', 'sec_tab' => 'people']))
            ->assertOk()
            ->assertDontSee('data-person-status', false);
    }

    public function test_device_sign_outs_are_admin_only(): void
    {
        $staff = User::factory()->create();
        $staff->syncRoles(['Laundry']);
        $this->addSession($this->admin, 'admin-session');

        $this->actingAs($staff)->delete(route('security.sessions.revoke', 'admin-session'))->assertForbidden();
        $this->delete(route('security.sessions.revokeOthers'))->assertForbidden();
        $this->assertDatabaseHas('sessions', ['id' => 'admin-session']);
    }
}
