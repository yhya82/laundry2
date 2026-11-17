<?php

namespace App\Support;

/**
 * Gambian phone numbers during the move from 7 to 9 digits. Every phone
 * field (customers, staff, the business phone, collection stand-ins) goes
 * through here, so the rules are the same everywhere.
 *
 * - normalize(): staff type a bare local number (spaces/dashes ignored, +220
 *   optional). A 7-digit number is CONVERTED to its 9-digit form by adding
 *   the prefix for its first digit -- 5190199 becomes +220835190199. Numbers
 *   starting with 9 have no prefix yet and stay 7 digits. Callers run this
 *   BEFORE validation, so uniqueness compares the stored shape.
 * - VALID_PATTERN: +220 followed by 9 digits starting 83 / 86 / 87 / 9, or
 *   (unmapped for now) 7 digits starting with 9.
 * - equivalents(): numbers stored before conversion stay as they were, so a
 *   number and its old 7-digit form are treated as the SAME line for
 *   duplicate checks (UniquePhone) and search (searchVariants()).
 */
class PhoneNumber
{
    /**
     * First digit of the old 7-digit number => 2-digit prefix of the new
     * 9-digit number. 9 (and anything else) is deliberately unmapped.
     */
    public const PREFIX_MAP = [
        '3' => '83', '5' => '83',
        '6' => '86', '8' => '86',
        '2' => '87', '7' => '87', '4' => '87',
    ];

    public const VALID_PATTERN = '/^\+220(9\d{6}|(83|86|87|9\d)\d{7})$/';

    public const INVALID_MESSAGE = 'Enter a valid number: 7 digits, or 9 digits starting with 83, 86, 87 or 9.';

    public const HINT = '7 or 9 digits, e.g. 5190199 is saved as 835190199. +220 is added for you.';

    public static function normalize(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return $value;
        }

        $digits = preg_replace('/\D/', '', $value);

        if (str_starts_with($value, '+')) {
            // Another country's number: left as typed, validation rejects it.
            if (! str_starts_with($digits, '220')) {
                return '+'.$digits;
            }
            $local = substr($digits, 3);
        } elseif (str_starts_with($value, '00220')) {
            $local = substr($digits, 5);
        } elseif (str_starts_with($digits, '220') && in_array(strlen($digits), [10, 12], true)) {
            // 220 typed without the plus.
            $local = substr($digits, 3);
        } else {
            $local = $digits;
        }

        return '+220'.self::toNineDigits($local);
    }

    /** A bare 7-digit local number with a mapped first digit -> its 9-digit form. */
    public static function toNineDigits(string $local): string
    {
        if (strlen($local) === 7 && isset(self::PREFIX_MAP[$local[0]])) {
            return self::PREFIX_MAP[$local[0]].$local;
        }

        return $local;
    }

    public static function isValid(?string $normalized): bool
    {
        return is_string($normalized) && (bool) preg_match(self::VALID_PATTERN, $normalized);
    }

    /**
     * Every stored form that is the same line as $normalized: itself, plus
     * the old 7-digit form when the 9-digit prefix is the one that 7-digit
     * number maps to (835190199 <-> 5190199, but NOT 865190199, whose
     * old form would have started 6 or 8).
     *
     * @return list<string>
     */
    public static function equivalents(string $normalized): array
    {
        $forms = [$normalized];

        if (preg_match('/^\+220(83|86|87)(\d{7})$/', $normalized, $m) && (self::PREFIX_MAP[$m[2][0]] ?? null) === $m[1]) {
            $forms[] = '+220'.$m[2];
        }

        // An old 7-digit number given directly also matches its 9-digit form.
        if (preg_match('/^\+220(\d{7})$/', $normalized, $m) && ($nine = self::toNineDigits($m[1])) !== $m[1]) {
            $forms[] = '+220'.$nine;
        }

        return array_values(array_unique($forms));
    }

    /**
     * Search terms for a phone typed into a search box, covering both
     * forms: "5190199" also finds +220835190199, and "835190199" also finds
     * a customer still stored as +2205190199. Returns [] for anything that
     * isn't a phone-like run of digits, so name searches are untouched.
     *
     * @return list<string>
     */
    public static function searchVariants(string $term): array
    {
        $digits = preg_replace('/\D/', '', $term);

        if (strlen($digits) < 7 || preg_match('/[a-z]/i', $term)) {
            return [];
        }

        if (str_starts_with($digits, '220') && strlen($digits) > 9) {
            $digits = substr($digits, 3);
        }

        $variants = [$digits];

        if (strlen($digits) === 7 && ($nine = self::toNineDigits($digits)) !== $digits) {
            $variants[] = $nine;
        }

        if (strlen($digits) === 9 && preg_match('/^(83|86|87)(\d{7})$/', $digits, $m) && (self::PREFIX_MAP[$m[2][0]] ?? null) === $m[1]) {
            // The old 7-digit form, matched exactly at the end of the stored number.
            $variants[] = '+220'.$m[2];
        }

        return array_values(array_unique($variants));
    }

    /**
     * Adds "phone matches any form of $term" to a query (an OR group the
     * caller nests inside its own where()).
     */
    public static function whereMatches($query, string $column, string $term): void
    {
        foreach (self::searchVariants($term) as $variant) {
            str_starts_with($variant, '+')
                ? $query->orWhere($column, $variant)
                : $query->orWhere($column, 'like', '%'.$variant.'%');
        }
    }
}
