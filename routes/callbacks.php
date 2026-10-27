<?php

use AmrSaleh\FasahPay\Http\Controllers\CallbackController;
use Illuminate\Support\Facades\Route;

Route::post('invoice-notification', [CallbackController::class, 'invoice'])->name('fasahpay.callbacks.invoice');
Route::post('settlement-notification', [CallbackController::class, 'settlement'])->name('fasahpay.callbacks.settlement');
