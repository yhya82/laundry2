<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\SecurityPolicy;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'theme_preference',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'password_changed_at' => 'datetime',
            'must_change_password' => 'boolean',
        ];
    }

    /**
     * Every password change, from any form, goes through here: it stamps
     * password_changed_at (the expiry clock) and records the new hash in
     * password_histories, so "don't reuse your last N passwords" works the
     * same everywhere. must_change_password is never mass-assignable -- only
     * set deliberately via forceFill() (Admin reset, new account).
     */
    protected static function booted(): void
    {
        static::saving(function (User $user) {
            if ($user->isDirty('password') && ! $user->isDirty('password_changed_at')) {
                $user->password_changed_at = now();
            }
        });

        static::saved(function (User $user) {
            if ($user->wasChanged('password') || ($user->wasRecentlyCreated && $user->password)) {
                $user->passwordHistories()->create(['password' => $user->password]);
            }
        });
    }

    public function passwordHistories(): HasMany
    {
        return $this->hasMany(PasswordHistory::class);
    }

    /**
     * Older than the configured expiry (0 = never). A user with no
     * password_changed_at is never treated as expired -- the migration
     * backfills every existing user, and saving() stamps every new one.
     */
    public function passwordExpired(): bool
    {
        $days = SecurityPolicy::passwordExpiryDays();

        return $days > 0
            && $this->password_changed_at !== null
            && $this->password_changed_at->lte(now()->subDays($days));
    }

    /**
     * Sent to "Choose a new password" before anything else.
     */
    public function needsPasswordChange(): bool
    {
        return $this->must_change_password || $this->passwordExpired();
    }

    public function passwordExpiresAt(): ?\Illuminate\Support\Carbon
    {
        $days = SecurityPolicy::passwordExpiryDays();

        return $days > 0 && $this->password_changed_at ? $this->password_changed_at->copy()->addDays($days) : null;
    }
}
