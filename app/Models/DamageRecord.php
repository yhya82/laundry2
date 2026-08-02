<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DamageRecord extends Model
{
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
}
