<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\PlaidController;
use App\Http\Controllers\StripePaymentController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\MockTransferController;
use App\Http\Controllers\StripeStatusController;
use App\Http\Controllers\HealthMonitorController;
use App\Http\Controllers\SettingsController;

Route::get('/', function () {
    return view('welcome');
});

// Plaid Integration Routes
Route::post('/plaid/create-link-token', [PlaidController::class, 'createLinkToken'])->name('plaid.create-link-token');
Route::post('/plaid/token-exchange', [PlaidController::class, 'exchangeToken'])->name('plaid.token-exchange');
Route::post('/plaid/accounts', [PlaidController::class, 'getAccounts'])->name('plaid.accounts');
Route::post('/plaid/user-accounts', [PlaidController::class, 'getUserAccounts'])->name('plaid.user-accounts');
Route::post('/plaid/balances', [PlaidController::class, 'getBalances'])->name('plaid.balances');
Route::post('/plaid/create-ach-transfer', [PlaidController::class, 'createACHTransfer'])->name('plaid.create-ach-transfer');
Route::get('/plaid/check-verification-status/{paymentIntentId}', [PlaidController::class, 'checkVerificationStatus'])->name('plaid.check-verification-status');
Route::post('/plaid/webhook', [PlaidController::class, 'webhook'])->name('plaid.webhook');
Route::get('/plaid/stored-accounts', [PlaidController::class, 'getStoredAccounts'])->name('plaid.stored-accounts');
Route::delete('/plaid/accounts/{accountId}', [PlaidController::class, 'disconnectAccount'])->name('plaid.disconnect-account');
Route::get('/plaid/configuration-status', [PlaidController::class, 'getConfigurationStatus'])->name('plaid.configuration-status');

// Stripe Payment Routes (Regular Stripe + Plaid ACH)
Route::post('/stripe/create-payment-intent', [StripePaymentController::class, 'createPaymentIntent'])->name('stripe.create-payment-intent');
Route::post('/stripe/process-ach-transfer', [StripePaymentController::class, 'processACHTransfer'])->name('stripe.process-ach-transfer');
Route::post('/stripe/complete-transfer', [StripePaymentController::class, 'completeTransfer'])->name('stripe.complete-transfer');
Route::get('/stripe/transactions', [StripePaymentController::class, 'getTransactions'])->name('stripe.transactions');

// Stripe Webhook Route (no CSRF protection needed for webhooks)
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle'])->name('stripe.webhook');

// Dashboard for lending platform management
Route::get('/dashboard', function () {
    return view('simple-dashboard');
})->name('dashboard');

// Stripe transaction status dashboard
Route::get('/stripe-status', function () {
    return view('stripe-status');
})->name('stripe-status');

// Health Monitoring Routes
Route::get('/health/services', [HealthMonitorController::class, 'getServiceHealth'])->name('health.services');
Route::get('/health/queue', [HealthMonitorController::class, 'getQueuedTransactions'])->name('health.queue');
Route::post('/health/retry/{id}', [HealthMonitorController::class, 'retryTransaction'])->name('health.retry');
Route::post('/health/process-queue', [HealthMonitorController::class, 'processQueue'])->name('health.process-queue');

// Settings Routes
Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
Route::get('/settings/{key}', [SettingsController::class, 'show'])->name('settings.show');
Route::put('/settings/{key}', [SettingsController::class, 'update'])->name('settings.update');
Route::post('/settings/toggle-service/{service}', [SettingsController::class, 'toggleService'])->name('settings.toggle-service');
Route::get('/settings/services/status', [SettingsController::class, 'getServiceStatuses'])->name('settings.service-statuses');
Route::post('/settings/reset-defaults', [SettingsController::class, 'resetToDefaults'])->name('settings.reset-defaults');
Route::post('/settings/bulk-update', [SettingsController::class, 'bulkUpdate'])->name('settings.bulk-update');

// API Routes (simpler approach - these will use Accept: application/json header to bypass CSRF)
Route::prefix('api')->group(function () {
    Route::post('/users', [UserController::class, 'store']);
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::put('/users/{id}', [UserController::class, 'update']);

    // API Transaction routes
    Route::post('/stripe/create-payment-intent', [StripePaymentController::class, 'createPaymentIntent']);
    Route::post('/stripe/process-ach-transfer', [StripePaymentController::class, 'processACHTransfer']);
    Route::post('/stripe/complete-transfer', [StripePaymentController::class, 'completeTransfer']);
    Route::get('/stripe/transactions', [StripePaymentController::class, 'getTransactions']);

    // Mock Transfer Routes (for testing when Plaid Processor API isn't available)
    Route::post('/mock/create-transfer', [MockTransferController::class, 'createMockTransfer']);
    Route::get('/mock/bank-account', [MockTransferController::class, 'getMockBankAccount']);
    Route::get('/mock/transactions', [MockTransferController::class, 'listMockTransactions']);

    // Stripe Status Check Routes
    Route::post('/stripe/check-payment-intent', [StripeStatusController::class, 'checkPaymentIntent']);
    Route::get('/stripe/list-transactions', [StripeStatusController::class, 'listTransactions']);
    Route::post('/stripe/sync-transaction-status', [StripeStatusController::class, 'syncTransactionStatus']);
});


