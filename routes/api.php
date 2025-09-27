<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\StripePaymentController;

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
