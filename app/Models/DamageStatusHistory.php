<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DamageStatusHistory extends Model
{
    const UPDATED_AT = null;

    protected $table = 'damage_status_history';

    protected $fillable = ['damage_record_id', 'from_status', 'to_status', 'changed_by', 'note'];

    public function damageRecord(): BelongsTo
    {
        return $this->belongsTo(DamageRecord::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
