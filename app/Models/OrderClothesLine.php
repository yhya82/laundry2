<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderClothesLine extends Model
{
    protected $fillable = [
        'order_package_line_id',
        'clothing_item_id',
        'item_name_snapshot',
        'item_price_snapshot',
        'quantity',
        'is_extra',
    ];

    protected function casts(): array
    {
        return [
            'item_price_snapshot' => 'decimal:2',
            'is_extra' => 'boolean',
        ];
    }

    public function packageLine(): BelongsTo
    {
        return $this->belongsTo(OrderPackageLine::class, 'order_package_line_id');
    }

    public function clothingItem(): BelongsTo
    {
        return $this->belongsTo(ClothingItem::class);
    }
}
