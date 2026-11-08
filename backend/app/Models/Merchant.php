<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class Merchant extends Model
{
    public const CURRENCIES = ['EUR', 'USD', 'HUF'];

    // Keep JSON money values exact in both PHP and JavaScript clients.
    public const MAX_BALANCE = 9_007_199_254_740_991;

    protected $fillable = ['name', 'currency', 'balance'];

    protected $attributes = ['balance' => 0];

    protected function casts(): array
    {
        return ['balance' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(function (Merchant $merchant): void {
            if ($merchant->isDirty('currency')) {
                throw new LogicException('Merchant currency cannot be changed.');
            }
        });
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
