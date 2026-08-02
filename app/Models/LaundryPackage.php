<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LaundryPackage extends Model
{
    protected $fillable = ['name', 'description', 'base_price', 'is_active'];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
