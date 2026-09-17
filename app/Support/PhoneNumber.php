<?php

namespace App\Support;

/**
 * Every phone field in this app (customers, users) accepts a bare local
 * number -- staff never need to type the +220 country code themselves.
 * normalize() prepends it if it's missing, so the stored value is always
 * consistent regardless of what was actually typed. Callers run this
 * *before* validation (not just before save), so the uniqueness check
 * compares against the same normalized shape every existing number was
 * stored in -- otherwise "5551234" typed bare would never collide with an
 * existing "+2205551234", even though they're the same number.
 */
class PhoneNumber
{
    public static function normalize(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '' || str_starts_with($value, '+')) {
            return $value;
        }

        return '+220'.$value;
    }
}
