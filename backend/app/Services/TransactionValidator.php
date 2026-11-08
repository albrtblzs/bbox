<?php

namespace App\Services;

use App\Exceptions\ApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TransactionValidator
{
    public function validate(array $input): void
    {
        $correlationId = (string) Str::uuid();
        $started = hrtime(true);
        $outcome = 'unavailable';
        try {
            $response = Http::acceptJson()->asJson()->timeout(2)->connectTimeout(2)->withoutRedirecting()
                ->withHeaders(['X-Correlation-ID' => $correlationId])
                ->post(rtrim(config('services.validator.url'), '/').'/validate', $input);

            $decision = $response->json();
            if (! $response->successful() || ! is_array($decision)
                || ! is_bool($decision['approved'] ?? null)
                || ! in_array($decision['reason'] ?? null, [
                    'APPROVED', 'INVALID_REQUEST', 'INVALID_TYPE', 'INVALID_AMOUNT',
                    'UNSUPPORTED_CURRENCY', 'AMOUNT_LIMIT_EXCEEDED',
                ], true)
                || $decision['approved'] !== ($decision['reason'] === 'APPROVED')) {
                throw $this->unavailable();
            }

            $outcome = $decision['approved'] ? 'approved' : 'rejected';
            if (! $decision['approved']) {
                throw new ApiException('VALIDATOR_REJECTED', 'The validator rejected the transaction.', 422);
            }
        } catch (ConnectionException $exception) {
            throw $this->unavailable();
        } finally {
            Log::info('transaction_validation', [
                'correlation_id' => $correlationId,
                'outcome' => $outcome,
                'latency_ms' => round((hrtime(true) - $started) / 1_000_000, 2),
            ]);
        }
    }

    private function unavailable(): ApiException
    {
        return new ApiException('VALIDATOR_UNAVAILABLE', 'The validator is unavailable. Retry with the same idempotency key.', 503);
    }
}
