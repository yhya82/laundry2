<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStatusHistory extends Model
{
    const UPDATED_AT = null;

    // Eloquent's auto-pluralization guesses "order_status_histories"; the
    // real table (per the migration) is "order_status_history".
    protected $table = 'order_status_history';

    protected $fillable = ['order_id', 'from_status', 'to_status', 'changed_by', 'note'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
