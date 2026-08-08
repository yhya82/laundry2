<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Backup extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'filename',
        'disk',
        'size_bytes',
        'status',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
