<?php

use App\Http\Controllers\MerchantController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::post('/merchants', [MerchantController::class, 'store']);
Route::get('/merchants/{merchant}', [MerchantController::class, 'show'])->whereNumber('merchant');
Route::post('/merchants/{merchant}/transactions', [TransactionController::class, 'store'])->whereNumber('merchant');
Route::get('/merchants/{merchant}/transactions', [TransactionController::class, 'index'])->whereNumber('merchant');
