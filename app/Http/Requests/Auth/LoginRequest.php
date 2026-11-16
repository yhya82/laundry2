<?php

namespace App\Http\Requests\Auth;

use App\Models\LoginEvent;
use App\Models\User;
use App\Support\SecurityPolicy;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Login with the Security Center's lockout policy (attempts + minutes are
 * configurable). Problems are reported under the `login` error key, shown
 * as one alert at the top of the login form; field problems (empty email,
 * etc.) stay under their own field.
 *
 * "Incorrect email or password" is identical whether or not the email
 * exists, so the page can't be used to discover accounts -- the distinction
 * is only recorded in the Admin-only login log.
 */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Enter your email address.',
            'email.email' => 'Enter a valid email address, like name@example.com.',
            'password.required' => 'Enter your password.',
        ];
    }

    /**
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        // Checked BEFORE signing in, so a deactivated account never gets a
        // session (or a "signed in" log row) at all.
        $user = User::where('email', $this->string('email'))->first();

        if ($user && ! $user->is_active && Hash::check((string) $this->input('password'), $user->password)) {
            LoginEvent::record('failed', ['user_id' => $user->id, 'email' => $user->email, 'reason' => 'deactivated']);

            throw ValidationException::withMessages([
                'login' => 'This account has been deactivated. Contact an administrator.',
            ]);
        }

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey(), SecurityPolicy::lockoutMinutes() * 60);

            // This failure was the last one allowed -- lock now (and alert
            // Admins) rather than on the next try.
            if (RateLimiter::tooManyAttempts($this->throttleKey(), SecurityPolicy::lockoutAttempts())) {
                event(new Lockout($this));

                throw $this->lockedException();
            }

            $left = RateLimiter::remaining($this->throttleKey(), SecurityPolicy::lockoutAttempts());

            throw ValidationException::withMessages([
                'login' => 'Incorrect email or password.',
                'login_hint' => $left.' '.Str::plural('attempt', $left).' left before this account is locked for '.SecurityPolicy::lockoutMinutes().' minutes.',
            ]);
        }

        // Safety net: deactivated between the check above and now.
        if (! Auth::user()->is_active) {
            Auth::logout();

            throw ValidationException::withMessages([
                'login' => 'This account has been deactivated. Contact an administrator.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), SecurityPolicy::lockoutAttempts())) {
            return;
        }

        event(new Lockout($this));

        throw $this->lockedException();
    }

    protected function lockedException(): ValidationException
    {
        $minutes = max(1, (int) ceil(RateLimiter::availableIn($this->throttleKey()) / 60));

        return ValidationException::withMessages([
            'login' => 'Too many failed attempts.',
            'login_hint' => 'This account is locked. Try again in '.$minutes.' '.Str::plural('minute', $minutes).'.',
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
