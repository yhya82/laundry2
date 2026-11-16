<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only security log shown in Settings → Security: successful and
 * failed logins, lockouts, logouts and Admin security actions. The app's DB
 * user can only INSERT/SELECT this table (ops_grants.sql). Passwords are
 * never stored here, not even wrong ones.
 */
class LoginEvent extends Model
{
    public const UPDATED_AT = null;

    public const EVENT_LABELS = [
        'login' => 'Signed in',
        'logout' => 'Signed out',
        'failed' => 'Failed login',
        'lockout' => 'Account locked',
        'password_reset_by_admin' => 'Password changed by admin',
        'sessions_revoked' => 'Signed out by admin',
    ];

    public const REASON_LABELS = [
        'wrong_password' => 'Wrong password',
        'unknown_email' => 'Unknown email',
        'deactivated' => 'Account deactivated',
        'too_many_attempts' => 'Too many attempts',
        'one_device' => 'One device',
        'all_devices' => 'All devices',
        'everyone_else' => 'Everyone except the admin',
    ];

    protected $fillable = ['user_id', 'email', 'event', 'reason', 'ip_address', 'user_agent', 'actor_id', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Records an event for the current request (IP + browser), never
     * letting a logging failure break a login.
     */
    public static function record(string $event, array $attributes = []): void
    {
        try {
            static::create([
                'event' => $event,
                'ip_address' => request()?->ip(),
                'user_agent' => substr((string) request()?->userAgent(), 0, 1000) ?: null,
                ...$attributes,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
