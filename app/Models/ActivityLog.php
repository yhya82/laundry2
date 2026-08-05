<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Read-only -- every row here comes from a DB trigger (see
 * database/migrations/2025_01_02_000001_create_laundry_triggers.php), never
 * from Eloquent writes. causer_type is always App\Models\User in practice,
 * so causer() is a plain belongsTo rather than a morphTo.
 */
class ActivityLog extends Model
{
    public $table = 'activity_log';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function causer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'causer_id');
    }

    public function subjectLabel(): string
    {
        return class_basename($this->subject_type ?? '').($this->subject_id ? ' #'.$this->subject_id : '');
    }
}
