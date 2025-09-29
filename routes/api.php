<?php

use App\Http\Controllers\PlaidController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\StripePaymentController;
use App\Http\Controllers\StripeStatusController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// User management routes
Route::post('/users', [UserController::class, 'store']);
Route::get('/users', [UserController::class, 'index']);
Route::get('/users/{id}', [UserController::class, 'show']);
Route::put('/users/{id}', [UserController::class, 'update']);

// Transaction routes
Route::post('/stripe/create-payment-intent', [StripePaymentController::class, 'createPaymentIntent']);
Route::post('/stripe/process-ach-transfer', [StripePaymentController::class, 'processACHTransfer']);
Route::post('/stripe/complete-transfer', [StripePaymentController::class, 'completeTransfer']);
Route::get('/stripe/transactions', [StripePaymentController::class, 'getTransactions']);

Route::post('/stripe/create-payment-intent', [StripePaymentController::class, 'createPaymentIntent'])->name('api.stripe.create-payment-intent');
Route::post('/plaid/create-ach-transfer', [PlaidController::class, 'createACHTransfer'])->name('api.plaid.create-ach-transfer');

// Debug routes
Route::get('/debug/api-config', [PlaidController::class, 'showApiConfiguration'])->name('api.debug.config');

// Stripe Status Check Routes
Route::post('/stripe/check-payment-intent', [StripeStatusController::class, 'checkPaymentIntent']);
Route::get('/stripe/list-transactions', [StripeStatusController::class, 'listTransactions']);
Route::post('/stripe/sync-transaction-status', [StripeStatusController::class, 'syncTransactionStatus']);

// ACH Payment with Bank Token Route (SOLUTION TO YOUR QUESTION!)
Route::post('/plaid/charge-ach-with-token', [PlaidController::class, 'chargeACHWithBankToken'])->name('api.plaid.charge-ach-token');
