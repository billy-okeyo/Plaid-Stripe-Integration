<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\LendingUser;
use App\Models\Transaction;
use App\Models\PlaidAccount;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\PaymentMethod;
use Exception;

class StripePaymentController extends Controller
{
    public function __construct()
    {
        Stripe::setApiKey(env('STRIPE_SECRET_KEY'));
    }

    /**
     * Create a payment intent for high-value transfers
     * This uses Stripe for card payments and fee collection
     */
    public function createPaymentIntent(Request $request)
    {
        try {
            $request->validate([
                'amount' => 'required|numeric|min:1000000', // Minimum $10,000 in cents
                'description' => 'required|string',
                'from_user_id' => 'required|exists:lending_users,id',
                'to_user_id' => 'required|exists:lending_users,id'
            ]);

            $amount = $request->amount;
            $platformFee = $amount * 0.01; // 1% platform fee
            $totalAmount = $amount + $platformFee;

            // Create Stripe Payment Intent
            $paymentIntent = PaymentIntent::create([
                'amount' => $totalAmount,
                'currency' => 'usd',
                'description' => $request->description,
                'metadata' => [
                    'from_user_id' => $request->from_user_id,
                    'to_user_id' => $request->to_user_id,
                    'transfer_amount' => $amount,
                    'platform_fee' => $platformFee,
                    'type' => 'lending_transfer'
                ]
            ]);

            // Create transaction record
            $transaction = Transaction::create([
                'from_account_id' => $request->from_user_id,
                'to_account_id' => $request->to_user_id,
                'amount' => $amount / 100, // Store in dollars
                'currency' => 'USD',
                'status' => 'pending',
                'stripe_payment_intent_id' => $paymentIntent->id,
                'description' => $request->description,
                'transaction_type' => 'transfer',
                'metadata' => [
                    'platform_fee' => $platformFee / 100,
                    'total_charged' => $totalAmount / 100
                ]
            ]);

            return response()->json([
                'success' => true,
                'client_secret' => $paymentIntent->client_secret,
                'payment_intent_id' => $paymentIntent->id,
                'transaction_id' => $transaction->id,
                'amount' => $amount / 100,
                'platform_fee' => $platformFee / 100,
                'total_amount' => $totalAmount / 100
            ]);

        } catch (Exception $e) {
            Log::error('Payment intent creation failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Process ACH transfer using Plaid after payment confirmation
     * This is where the actual money movement happens via bank transfer
     */
    public function processACHTransfer(Request $request)
    {
        try {
            $request->validate([
                'payment_intent_id' => 'required|string',
                'from_plaid_account_id' => 'required|exists:plaid_accounts,id',
                'to_plaid_account_id' => 'required|exists:plaid_accounts,id'
            ]);

            // Find the transaction by payment intent
            $transaction = Transaction::where('stripe_payment_intent_id', $request->payment_intent_id)->first();

            if (!$transaction) {
                throw new Exception('Transaction not found');
            }

            // Verify payment intent was successful
            $paymentIntent = PaymentIntent::retrieve($request->payment_intent_id);

            if ($paymentIntent->status !== 'succeeded') {
                throw new Exception('Payment must be confirmed before ACH transfer');
            }

            $fromAccount = PlaidAccount::find($request->from_plaid_account_id);
            $toAccount = PlaidAccount::find($request->to_plaid_account_id);

            // Here you would integrate with Plaid's ACH transfer API
            // For now, we'll simulate the process

            $transaction->update([
                'status' => 'processing',
                'metadata' => array_merge($transaction->metadata ?? [], [
                    'ach_initiated_at' => now(),
                    'from_plaid_account' => $fromAccount->account_id,
                    'to_plaid_account' => $toAccount->account_id
                ])
            ]);

            return response()->json([
                'success' => true,
                'message' => 'ACH transfer initiated',
                'transaction_id' => $transaction->id,
                'status' => 'processing',
                'estimated_completion' => now()->addBusinessDays(3)->format('Y-m-d')
            ]);

        } catch (Exception $e) {
            Log::error('ACH transfer failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Simulate ACH transfer completion (webhook simulation)
     */
    public function completeTransfer(Request $request)
    {
        try {
            $request->validate([
                'transaction_id' => 'required|exists:transactions,id'
            ]);

            $transaction = Transaction::find($request->transaction_id);

            $transaction->update([
                'status' => 'succeeded',
                'metadata' => array_merge($transaction->metadata ?? [], [
                    'completed_at' => now(),
                    'ach_status' => 'settled'
                ])
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Transfer completed successfully',
                'transaction' => $transaction
            ]);

        } catch (Exception $e) {
            Log::error('Transfer completion failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get transaction history
     */
    public function getTransactions(Request $request)
    {
        try {
            $query = Transaction::with(['fromAccount', 'toAccount']);

            if ($request->user_id) {
                $query->where(function($q) use ($request) {
                    $q->where('from_account_id', $request->user_id)
                      ->orWhere('to_account_id', $request->user_id);
                });
            }

            if ($request->status) {
                $query->where('status', $request->status);
            }

            $transactions = $query->orderBy('created_at', 'desc')
                                 ->paginate(20);

            return response()->json([
                'success' => true,
                'transactions' => $transactions
            ]);

        } catch (Exception $e) {
            Log::error('Failed to fetch transactions: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }
}
