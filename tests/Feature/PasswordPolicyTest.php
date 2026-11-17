<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\PermissionsAndRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Password;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The Security Center's password policy, rule by rule, on EVERY form that
 * sets a password. Each rule is checked both ways: a password missing just
 * that one thing is refused, and one that has it is accepted.
 */
class PasswordPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionsAndRolesSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->syncRoles(['Admin']);
    }

    /** Every form that sets a password => submits $password, returns [response, error bag]. */
    public static function forms(): array
    {
        return [
            'profile' => ['profile'],
            'choose a new password (forced change)' => ['forced_change'],
            'forgot-password reset' => ['reset'],
            'admin changes another user' => ['admin_other'],
            'admin changes own (users list)' => ['admin_self'],
            'admin creates a user' => ['create_user'],
        ];
    }

    protected function submit(string $form, string $password): array
    {
        $user = User::factory()->create(); // current password: 'password'

        return match ($form) {
            'profile' => [$this->actingAs($user)->from('/profile')->put('/password', [
                'current_password' => 'password', 'password' => $password, 'password_confirmation' => $password,
            ]), 'updatePassword'],

            'forced_change' => (function () use ($user, $password) {
                $user->forceFill(['must_change_password' => true])->saveQuietly();

                return [$this->actingAs($user)->from(route('password.change'))->put(route('password.change.update'), [
                    'current_password' => 'password', 'password' => $password, 'password_confirmation' => $password,
                ]), 'default'];
            })(),

            'reset' => [$this->post('/reset-password', [
                'token' => Password::createToken($user), 'email' => $user->email,
                'password' => $password, 'password_confirmation' => $password,
            ]), 'default'],

            'admin_other' => [$this->actingAs($this->admin)->from(route('users.index'))->put(route('users.setPassword', $user), [
                'password' => $password, 'password_confirmation' => $password,
            ]), "setPassword{$user->id}"],

            'admin_self' => [$this->actingAs($this->admin)->from(route('users.index'))->put(route('users.setPassword', $this->admin), [
                'current_password' => 'password', 'password' => $password, 'password_confirmation' => $password,
            ]), "setPassword{$this->admin->id}"],

            'create_user' => [$this->actingAs($this->admin)->from(route('users.index'))->post(route('users.store'), [
                'name' => 'New Person', 'email' => 'new'.uniqid().'@laundry.test', 'phone' => (string) random_int(7000000, 7999999),
                'password' => $password, 'password_confirmation' => $password, 'roles' => ['Laundry'],
            ]), 'default'],
        };
    }

    protected function assertRefused(string $form, string $password, string $because): void
    {
        [$response, $bag] = $this->submit($form, $password);
        $response->assertSessionHasErrorsIn($bag, 'password');

        $this->assertStringContainsString($because, implode(' ', session('errors')->getBag($bag)->get('password')),
            "[{$form}] '{$password}' should be refused because: {$because}");
    }

    protected function assertAccepted(string $form, string $password): void
    {
        [$response, $bag] = $this->submit($form, $password);
        $response->assertSessionDoesntHaveErrors();
    }

    protected function policy(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::set("security.{$key}", is_bool($value) ? ($value ? 'true' : 'false') : (string) $value, 'security', is_bool($value) ? 'boolean' : 'integer');
        }
    }

    #[DataProvider('forms')]
    public function test_minimum_length(string $form): void
    {
        $this->policy(['password_min_length' => 10]);

        $this->assertRefused($form, 'ninechars', 'at least 10 characters');
        $this->assertAccepted($form, 'tencharsok');
    }

    #[DataProvider('forms')]
    public function test_capital_and_lower_case_letters(string $form): void
    {
        $this->policy(['password_require_mixed_case' => true]);

        $this->assertRefused($form, 'alllowercase', 'one uppercase and one lowercase letter');
        $this->assertRefused($form, 'ALLUPPERCASE', 'one uppercase and one lowercase letter');
        $this->assertAccepted($form, 'HasCapitalA');
    }

    #[DataProvider('forms')]
    public function test_a_number(string $form): void
    {
        $this->policy(['password_require_numbers' => true]);

        $this->assertRefused($form, 'no-numbers-here', 'at least one number');
        $this->assertAccepted($form, 'has-number-7');
    }

    #[DataProvider('forms')]
    public function test_a_symbol(string $form): void
    {
        $this->policy(['password_require_symbols' => true]);

        $this->assertRefused($form, 'NoSymbols123', 'at least one symbol');
        $this->assertAccepted($form, 'HasSymbol!23');
    }

    #[DataProvider('forms')]
    public function test_every_rule_together(string $form): void
    {
        $this->policy([
            'password_min_length' => 12,
            'password_require_mixed_case' => true,
            'password_require_numbers' => true,
            'password_require_symbols' => true,
        ]);

        $this->assertRefused($form, 'Short1!', 'at least 12 characters');
        $this->assertRefused($form, 'nocapitals123!', 'one uppercase and one lowercase letter');
        $this->assertRefused($form, 'NoNumbersHere!', 'at least one number');
        $this->assertRefused($form, 'NoSymbolsHere123', 'at least one symbol');
        $this->assertAccepted($form, 'Str0ng&Secure!');
    }

    public function test_turning_a_rule_off_relaxes_it_again(): void
    {
        $this->policy(['password_require_numbers' => true]);
        $this->assertRefused('profile', 'no-numbers-here', 'at least one number');

        $this->policy(['password_require_numbers' => false]);
        $this->assertAccepted('profile', 'no-numbers-here');
    }

    public function test_the_breach_check_blocks_a_known_breached_password(): void
    {
        $this->policy(['password_block_breached' => true]);

        // Have I Been Pwned's range API: only the first 5 hash characters are
        // sent; the response lists matching suffixes. Faked -- no real call.
        $hash = strtoupper(sha1('Breached-Password-1'));
        Http::fake([
            'api.pwnedpasswords.com/range/'.substr($hash, 0, 5) => Http::response(substr($hash, 5).":42\r\n", 200),
            'api.pwnedpasswords.com/*' => Http::response('', 200),
        ]);

        $this->assertRefused('profile', 'Breached-Password-1', 'data leak');
        $this->assertAccepted('profile', 'Not-Breached-Password-2');

        Http::assertSent(fn ($request) => str_contains($request->url(), substr($hash, 0, 5)) && ! str_contains($request->url(), substr($hash, 5)));
    }

    public function test_the_live_checklist_shows_exactly_the_active_rules(): void
    {
        $this->policy(['password_min_length' => 12, 'password_require_mixed_case' => true, 'password_require_symbols' => true]);

        $user = User::factory()->create();
        $html = $this->actingAs($user)->get('/profile')->assertOk()->getContent();

        $this->assertStringContainsString('At least 12 characters', $html);
        $this->assertStringContainsString('Upper and lower case letters', $html);
        $this->assertStringContainsString('At least one symbol', $html);
        $this->assertStringNotContainsString('At least one number', $html, 'Rules that are off are not listed.');
    }
}
