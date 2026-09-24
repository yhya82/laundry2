<?php

namespace App\Support;

/**
 * Every phone field in this app (customers, users) accepts a bare local
 * number -- staff never need to type the +220 country code themselves, or
 * worry about spaces/dashes. normalize() strips any formatting and prepends
 * +220 if it's missing, so the stored value is always "+220" followed by
 * digits, regardless of what was actually typed. The digit count itself
 * (7 or 9, during the transition to 9-digit numbers ending December) is
 * enforced separately by each caller's own regex validation, not here.
 * Callers run this *before* validation (not just before save), so the
 * uniqueness check compares against the same normalized shape every
 * existing number was stored in -- otherwise "555 123456" typed bare
 * would never collide with an existing "+220555123456", even though
 * they're the same number.
 */
class PhoneNumber
{
    public static function normalize(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return $value;
        }

        if (str_starts_with($value, '+')) {
            return '+'.preg_replace('/\D/', '', substr($value, 1));
        }

        return '+220'.preg_replace('/\D/', '', $value);
    }
}
