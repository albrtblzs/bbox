<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Merchant;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class TransactionService
{
    public function __construct(private readonly TransactionValidator $validator) {}

    public function post(Merchant $merchant, array $input, string $key): Transaction
    {
        if ($existing = $this->existing($merchant, $key, $input)) {
            return $existing;
        }

        if ($input['currency'] !== $merchant->currency) {
            throw new ApiException('INVALID_INPUT', 'Currency must match the merchant currency.', 422);
        }

        $this->validator->validate($input);

        return DB::transaction(function () use ($merchant, $input, $key): Transaction {
            $locked = Merchant::query()->lockForUpdate()->findOrFail($merchant->id);
            if ($existing = $this->existing($locked, $key, $input)) {
                return $existing;
            }

            if ($input['type'] === 'refund' && $input['amount'] > $locked->balance) {
                throw new ApiException('INSUFFICIENT_FUNDS', 'The merchant has insufficient funds.', 422);
            }
            if ($input['type'] === 'payment' && $locked->balance > Merchant::MAX_BALANCE - $input['amount']) {
                throw new ApiException('BALANCE_LIMIT_EXCEEDED', 'The balance limit would be exceeded.', 422);
            }

            $balance = $locked->balance + ($input['type'] === 'payment' ? $input['amount'] : -$input['amount']);
            $transaction = $locked->transactions()->create([
                ...$input, 'idempotency_key' => $key, 'balance_after' => $balance,
            ]);
            $locked->update(['balance' => $balance]);

            return $transaction;
        });
    }

    private function existing(Merchant $merchant, string $key, array $input): ?Transaction
    {
        $existing = $merchant->transactions()->where('idempotency_key', $key)->first();
        if ($existing && ($existing->type !== $input['type'] || $existing->amount !== $input['amount'] || $existing->currency !== $input['currency'])) {
            throw new ApiException('IDEMPOTENCY_CONFLICT', 'The idempotency key was already used with different input.', 409);
        }

        return $existing;
    }
}
