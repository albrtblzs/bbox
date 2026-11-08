<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Merchant;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TransactionController extends Controller
{
    public function index(Request $request, Merchant $merchant): AnonymousResourceCollection
    {
        $request->validate(['page' => ['sometimes', 'integer', 'min:1', 'max:2147483647']]);

        return TransactionResource::collection(
            $merchant->transactions()->orderByDesc('created_at')->orderByDesc('id')->paginate(20),
        );
    }

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
