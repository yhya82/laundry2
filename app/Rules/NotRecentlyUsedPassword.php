<?php

namespace App\Rules;

use App\Models\User;
use App\Support\SecurityPolicy;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Hash;

/**
 * Rejects the user's current password and their last N previous ones
 * (N = Security setting, default 3). Compares against stored HASHES only.
 * A null user (e.g. a reset for an email with no account) passes -- the
 * reset itself fails later for that case.
 */
class NotRecentlyUsedPassword implements ValidationRule
{
    public function __construct(protected ?User $user)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $count = SecurityPolicy::passwordHistory();

        if (! $this->user || $count <= 0 || ! is_string($value) || $value === '') {
            return;
        }

        // Current password + the N before it. The newest history row is the
        // current password, so N+1 rows covers "current + last N".
        $hashes = $this->user->passwordHistories()
            ->latest('id')
            ->limit($count + 1)
            ->pluck('password')
            ->push($this->user->password)
            ->filter()
            ->unique();

        foreach ($hashes as $hash) {
            if (Hash::check($value, $hash)) {
                $fail("You've used this password recently. Choose one you haven't used in your last {$count} passwords.");

                return;
            }
        }
    }
}
