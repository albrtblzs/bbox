<?php

use App\Http\Controllers\MerchantController;
use Illuminate\Support\Facades\Route;

Route::post('/merchants', [MerchantController::class, 'store']);
Route::get('/merchants/{merchant}', [MerchantController::class, 'show'])->whereNumber('merchant');
