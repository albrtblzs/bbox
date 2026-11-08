<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Merchant;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;

class TransactionController extends Controller
{
    public function store(StoreTransactionRequest $request, Merchant $merchant, TransactionService $service): JsonResponse
    {
        $transaction = $service->post(
            $merchant,
            $request->safe()->only(['type', 'amount', 'currency']),
            $request->validated('idempotency_key'),
        );

        return (new TransactionResource($transaction))->response()->setStatusCode(201);
    }
}
