<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStatusHistory extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['order_id', 'from_status', 'to_status', 'changed_by', 'note'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
