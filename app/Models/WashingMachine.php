<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WashingMachine extends Model
{
    protected $fillable = ['name', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Derived, not stored -- "busy" is just "an order is currently sitting
     * on this machine at the washing stage," so it can never drift out of
     * sync with the order it's tracking the way a separate status column
     * could (e.g. an order cancelled without explicitly freeing the machine).
     */
    public function currentOrder(): ?Order
    {
        return $this->orders()->where('status', 'washing')->first();
    }

    public function isBusy(): bool
    {
        return $this->currentOrder() !== null;
    }
}
