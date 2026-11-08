<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMerchantRequest;
use App\Http\Resources\MerchantResource;
use App\Models\Merchant;
use Illuminate\Http\JsonResponse;

class MerchantController extends Controller
{
    public function store(StoreMerchantRequest $request): JsonResponse
    {
        $merchant = Merchant::create($request->safe()->only(['name', 'currency']));

        return (new MerchantResource($merchant))->response()->setStatusCode(201);
    }

    public function show(Merchant $merchant): MerchantResource
    {
        return new MerchantResource($merchant);
    }
}
