<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    public const TYPES = ['payment', 'refund'];

    public const MAX_AMOUNT = 2_147_483_647;

    protected $fillable = [
        'merchant_id', 'type', 'amount', 'currency', 'idempotency_key', 'balance_after',
    ];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'balance_after' => 'integer'];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }
}
