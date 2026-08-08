<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPackage extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'monthly_price', 'clothes_allowance', 'collections_per_month', 'max_clothes_per_cycle', 'is_active'];

    protected function casts(): array
    {
        return [
            'monthly_price' => 'decimal:2',
            'clothes_allowance' => 'integer',
            'collections_per_month' => 'integer',
            'max_clothes_per_cycle' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
