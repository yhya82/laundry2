<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Validation\Rules\Password;

/**
 * The Security Center's settings (Settings → Security), read in one place
 * with safe defaults and clamped ranges. Defaults keep the app exactly as
 * strict as before this existed (8+ characters, 5 attempts) until an Admin
 * tightens it.
 */
class SecurityPolicy
{
    public const DEFAULTS = [
        'security.password_min_length' => 8,
        'security.password_require_mixed_case' => false,
        'security.password_require_numbers' => false,
        'security.password_require_symbols' => false,
        'security.password_block_breached' => false,
        'security.password_expiry_days' => 90,
        'security.password_history' => 3,
        'security.lockout_attempts' => 5,
        'security.lockout_minutes' => 15,
        'security.idle_timeout_minutes' => 120,
    ];

    /**
     * Starting points offered when creating the policy (Password Policies
     * panel). Picking one only fills the form -- nothing is saved until the
     * Admin saves, and every value can still be adjusted first.
     */
    public const PRESETS = [
        'basic' => [
            'name' => 'Basic',
            'summary' => 'Easy for staff: 8+ characters, no expiry, relaxed lockout.',
            'values' => [
                'password_min_length' => 8, 'password_require_mixed_case' => false, 'password_require_numbers' => false,
                'password_require_symbols' => false, 'password_block_breached' => false, 'password_expiry_days' => 0,
                'password_history' => 0, 'lockout_attempts' => 10, 'lockout_minutes' => 5, 'idle_timeout_minutes' => 240,
            ],
        ],
        'recommended' => [
            'name' => 'Recommended',
            'summary' => 'A good balance: 10+ characters with case and numbers, 90-day expiry.',
            'values' => [
                'password_min_length' => 10, 'password_require_mixed_case' => true, 'password_require_numbers' => true,
                'password_require_symbols' => false, 'password_block_breached' => true, 'password_expiry_days' => 90,
                'password_history' => 3, 'lockout_attempts' => 5, 'lockout_minutes' => 15, 'idle_timeout_minutes' => 120,
            ],
        ],
        'strict' => [
            'name' => 'Strict',
            'summary' => 'Maximum protection: 12+ characters, all rules, 60-day expiry, 30-min sign-out.',
            'values' => [
                'password_min_length' => 12, 'password_require_mixed_case' => true, 'password_require_numbers' => true,
                'password_require_symbols' => true, 'password_block_breached' => true, 'password_expiry_days' => 60,
                'password_history' => 5, 'lockout_attempts' => 3, 'lockout_minutes' => 30, 'idle_timeout_minutes' => 30,
            ],
        ],
    ];

    /**
     * The current values in the same shape as a preset (the panel's form).
     */
    public static function currentValues(): array
    {
        return [
            'password_min_length' => self::passwordMinLength(),
            'password_require_mixed_case' => self::requiresMixedCase(),
            'password_require_numbers' => self::requiresNumbers(),
            'password_require_symbols' => self::requiresSymbols(),
            'password_block_breached' => self::blocksBreached(),
            'password_expiry_days' => self::passwordExpiryDays(),
            'password_history' => self::passwordHistory(),
            'lockout_attempts' => self::lockoutAttempts(),
            'lockout_minutes' => self::lockoutMinutes(),
            'idle_timeout_minutes' => self::idleTimeoutMinutes(),
        ];
    }

    /** Has an Admin ever saved the policy? (Otherwise the defaults apply.) */
    public static function isSaved(): bool
    {
        return Setting::where('key', 'like', 'security.%')->exists();
    }

    public const RANGES = [
        'security.password_min_length' => [8, 32],
        'security.password_expiry_days' => [0, 365],
        'security.password_history' => [0, 12],
        'security.lockout_attempts' => [3, 20],
        'security.lockout_minutes' => [1, 1440],
        'security.idle_timeout_minutes' => [5, 1440],
    ];

    public static function passwordMinLength(): int
    {
        return self::int('security.password_min_length');
    }

    public static function requiresMixedCase(): bool
    {
        return self::bool('security.password_require_mixed_case');
    }

    public static function requiresNumbers(): bool
    {
        return self::bool('security.password_require_numbers');
    }

    public static function requiresSymbols(): bool
    {
        return self::bool('security.password_require_symbols');
    }

    public static function blocksBreached(): bool
    {
        return self::bool('security.password_block_breached');
    }

    /** 0 = passwords never expire. */
    public static function passwordExpiryDays(): int
    {
        return self::int('security.password_expiry_days');
    }

    /** How many previous passwords can't be reused (0 = no history check). */
    public static function passwordHistory(): int
    {
        return self::int('security.password_history');
    }

    public static function lockoutAttempts(): int
    {
        return self::int('security.lockout_attempts');
    }

    public static function lockoutMinutes(): int
    {
        return self::int('security.lockout_minutes');
    }

    public static function idleTimeoutMinutes(): int
    {
        return self::int('security.idle_timeout_minutes');
    }

    /**
     * Installed as Password::defaults() (AppServiceProvider), so every
     * password form -- create user, Admin reset, profile change, forgot
     * password, forced change -- follows the same policy.
     */
    public static function passwordRule(): Password
    {
        $rule = Password::min(self::passwordMinLength());

        if (self::requiresMixedCase()) {
            $rule->mixedCase();
        }

        if (self::requiresNumbers()) {
            $rule->numbers();
        }

        if (self::requiresSymbols()) {
            $rule->symbols();
        }

        // Have I Been Pwned range API: only the first 5 characters of the
        // password's SHA-1 hash are sent. Fails open if unreachable.
        if (self::blocksBreached()) {
            $rule->uncompromised();
        }

        return $rule;
    }

    /**
     * Plain-language rules, listed under every new-password field.
     *
     * @return list<string>
     */
    public static function passwordRequirements(): array
    {
        return array_values(array_filter([
            'At least '.self::passwordMinLength().' characters',
            self::requiresMixedCase() ? 'Upper and lower case letters' : null,
            self::requiresNumbers() ? 'At least one number' : null,
            self::requiresSymbols() ? 'At least one symbol' : null,
            self::blocksBreached() ? 'Not found in a known data breach' : null,
            self::passwordHistory() > 0 ? 'Not one of your last '.self::passwordHistory().' passwords' : null,
        ]));
    }

    /**
     * The same requirements, in a shape the browser can check live as the
     * person types (x-password-input). 'server' rules -- the breach check
     * and password history -- can only be checked when the form is saved.
     * The server always re-checks everything regardless.
     *
     * @return list<array{text: string, check: string, min?: int}>
     */
    public static function clientRules(): array
    {
        return array_values(array_filter([
            ['text' => 'At least '.self::passwordMinLength().' characters', 'check' => 'min', 'min' => self::passwordMinLength()],
            self::requiresMixedCase() ? ['text' => 'Upper and lower case letters', 'check' => 'mixed'] : null,
            self::requiresNumbers() ? ['text' => 'At least one number', 'check' => 'number'] : null,
            self::requiresSymbols() ? ['text' => 'At least one symbol', 'check' => 'symbol'] : null,
            self::blocksBreached() ? ['text' => 'Not found in a known data breach', 'check' => 'server'] : null,
            self::passwordHistory() > 0 ? ['text' => 'Not one of your last '.self::passwordHistory().' passwords', 'check' => 'server'] : null,
        ]));
    }

    protected static function int(string $key): int
    {
        $value = (int) (Setting::get($key) ?? self::DEFAULTS[$key]);

        if (isset(self::RANGES[$key])) {
            [$min, $max] = self::RANGES[$key];
            $value = max($min, min($max, $value));
        }

        return $value;
    }

    protected static function bool(string $key): bool
    {
        $value = Setting::get($key);

        return $value === null ? (bool) self::DEFAULTS[$key] : $value === 'true';
    }
}
