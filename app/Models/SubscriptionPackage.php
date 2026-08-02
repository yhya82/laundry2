<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPackage extends Model
{
    protected $fillable = ['name', 'description', 'monthly_price', 'clothes_allowance', 'is_active'];

    protected function casts(): array
    {
        return [
            'monthly_price' => 'decimal:2',
            'clothes_allowance' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
