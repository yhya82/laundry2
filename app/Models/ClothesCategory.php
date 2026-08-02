<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClothesCategory extends Model
{
    protected $fillable = ['name', 'description'];

    public function clothingItems(): HasMany
    {
        return $this->hasMany(ClothingItem::class);
    }
}
