<?php

namespace App\Http\Requests;

use App\Models\Merchant;
use App\Models\Transaction;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(Transaction::TYPES)],
            'amount' => ['bail', 'required', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_int($value) || $value < 1 || $value > Transaction::MAX_AMOUNT) {
                    $fail('The amount must be a positive JSON integer no greater than '.Transaction::MAX_AMOUNT.'.');
                }
            }],
            'currency' => ['required', 'string', Rule::in(Merchant::CURRENCIES)],
            'idempotency_key' => ['required', 'string', 'max:128', 'regex:/\A[A-Za-z0-9._:-]+\z/'],
        ];
    }
}
