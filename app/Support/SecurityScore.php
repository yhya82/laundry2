<?php

namespace App\Support;

/**
 * The Security Center's 0–100 "Security Score": how strong the current
 * policy is, plus a check on the last 24 hours of login activity. Each
 * missed point comes back as a tip, so the score is always explainable.
 *
 *   Password rules ........ 40  (length up to 16, then 6 each for case,
 *                                numbers, symbols, breach check)
 *   Expiry ................ 10  (1–90 days)
 *   Password history ...... 10  (3+ blocked; 1–2 gives 5)
 *   Lockout ............... 10  (≤5 attempts; ≤10 gives 5)
 *   Idle sign-out ......... 10  (≤60 min; ≤120 gives 6)
 *   Two-factor ............ 10  (not available yet -- always 0 for now)
 *   Quiet last 24 hours ... 10  (no lockouts and <5 failures; <20 gives 5)
 */
class SecurityScore
{
    /**
     * @return array{score: int, tone: string, password_strength: string, password_tone: string, two_factor: string, suspicious: string, suspicious_tone: string, tips: list<string>}
     */
    public static function calculate(int $failedLast24h, int $lockoutsLast24h): array
    {
        $tips = [];

        $length = SecurityPolicy::passwordMinLength();
        $passwordPoints = match (true) {
            $length >= 12 => 16,
            $length >= 10 => 12,
            default => 8,
        };
        if ($length < 12) {
            $tips[] = 'Raise the minimum password length to 12 or more.';
        }

        foreach ([
            [SecurityPolicy::requiresMixedCase(), 'Require upper and lower case letters.'],
            [SecurityPolicy::requiresNumbers(), 'Require at least one number.'],
            [SecurityPolicy::requiresSymbols(), 'Require at least one symbol.'],
            [SecurityPolicy::blocksBreached(), 'Block passwords found in data breaches.'],
        ] as [$on, $tip]) {
            $on ? $passwordPoints += 6 : $tips[] = $tip;
        }

        $expiry = SecurityPolicy::passwordExpiryDays();
        $expiryPoints = $expiry > 0 && $expiry <= 90 ? 10 : 0;
        if (! $expiryPoints) {
            $tips[] = 'Make passwords expire within 90 days.';
        }

        $history = SecurityPolicy::passwordHistory();
        $historyPoints = $history >= 3 ? 10 : ($history > 0 ? 5 : 0);
        if ($history < 3) {
            $tips[] = 'Block reuse of at least the last 3 passwords.';
        }

        $attempts = SecurityPolicy::lockoutAttempts();
        $lockoutPoints = $attempts <= 5 ? 10 : ($attempts <= 10 ? 5 : 0);
        if ($attempts > 5) {
            $tips[] = 'Lock accounts after 5 failed attempts or fewer.';
        }

        $idle = SecurityPolicy::idleTimeoutMinutes();
        $idlePoints = $idle <= 60 ? 10 : ($idle <= 120 ? 6 : 2);
        if ($idle > 60) {
            $tips[] = 'Sign idle users out after 60 minutes or less.';
        }

        $tips[] = 'Two-factor authentication is planned for a later update.';

        $suspicious = match (true) {
            $lockoutsLast24h > 0 || $failedLast24h >= 20 => 'High',
            $failedLast24h >= 5 => 'Medium',
            default => 'Low',
        };
        $activityPoints = ['Low' => 10, 'Medium' => 5, 'High' => 0][$suspicious];
        if ($suspicious !== 'Low') {
            $tips[] = 'Review the failed logins from the last 24 hours in Threats.';
        }

        $score = $passwordPoints + $expiryPoints + $historyPoints + $lockoutPoints + $idlePoints + $activityPoints;

        return [
            'score' => $score,
            'tone' => $score >= 80 ? 'good' : ($score >= 50 ? 'fair' : 'poor'),
            'password_strength' => match (true) {
                $passwordPoints >= 34 => 'Excellent',
                $passwordPoints >= 24 => 'Good',
                $passwordPoints >= 14 => 'Fair',
                default => 'Weak',
            },
            'password_tone' => $passwordPoints >= 24 ? 'good' : ($passwordPoints >= 14 ? 'fair' : 'poor'),
            'two_factor' => 'Disabled',
            'suspicious' => $suspicious,
            'suspicious_tone' => ['Low' => 'good', 'Medium' => 'fair', 'High' => 'poor'][$suspicious],
            'tips' => $tips,
        ];
    }
}
