<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderPackageLine extends Model
{
    protected $fillable = [
        'order_id',
        'laundry_package_id',
        'package_name_snapshot',
        'package_price_snapshot',
        'quantity',
    ];

    protected function casts(): array
    {
        return ['package_price_snapshot' => 'decimal:2'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function laundryPackage(): BelongsTo
    {
        return $this->belongsTo(LaundryPackage::class);
    }

    public function clothesLines(): HasMany
    {
        return $this->hasMany(OrderClothesLine::class);
    }
}
