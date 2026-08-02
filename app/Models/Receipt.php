<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Receipt extends Model
{
    protected $fillable = ['order_id', 'receipt_number', 'reprint_count'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
