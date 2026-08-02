<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClothingItem extends Model
{
    protected $fillable = ['clothes_category_id', 'name', 'image_path', 'image_mime', 'image_size'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ClothesCategory::class, 'clothes_category_id');
    }
}
