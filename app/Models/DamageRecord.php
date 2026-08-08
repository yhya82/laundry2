<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DamageRecord extends Model
{
    /**
     * Unlike orders' linear pipeline, review isn't strictly sequential --
     * §2.4 allows pending_review to go straight to approved/rejected without
     * passing through under_investigation. 'resolved' is deliberately absent
     * here: it's never a manually-chosen target, only a side effect of a
     * damage_resolutions insert (trg_damage_records_resolve_guard enforces
     * this at the DB level; this map just keeps the UI from offering it).
     */
    public const VALID_TRANSITIONS = [
        'pending_review' => ['under_investigation', 'approved', 'rejected'],
        'under_investigation' => ['approved', 'rejected'],
        'approved' => [],
        'rejected' => ['closed'],
        'resolved' => ['closed'],
        'closed' => [],
    ];

    protected $fillable = [
        'order_id',
        'damage_type_id',
        'reported_by',
        'item_description',
        'stage_at_report',
        'description',
        'photo_path',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function damageType(): BelongsTo
    {
        return $this->belongsTo(DamageType::class);
    }

    public function resolution(): HasOne
    {
        return $this->hasOne(DamageResolution::class);
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(DamageStatusHistory::class);
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::VALID_TRANSITIONS[$this->status] ?? [], true);
    }
}
