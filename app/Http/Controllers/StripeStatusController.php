<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Transaction;

class StripeStatusController extends Controller
{
    /**
     * Check the status of a Stripe payment intent
     */
    public function checkPaymentIntent(Request $request)
    {
        $request->validate([
            'payment_intent_id' => 'required|string'
        ]);

        try {
            $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));

            $paymentIntent = $stripe->paymentIntents->retrieve($request->payment_intent_id);

            // Also get our local transaction record
            $transaction = Transaction::where('stripe_payment_intent_id', $request->payment_intent_id)->first();

            return response()->json([
                'success' => true,
                'stripe_status' => $paymentIntent->status,
                'stripe_data' => [
                    'id' => $paymentIntent->id,
                    'amount' => $paymentIntent->amount,
                    'currency' => $paymentIntent->currency,
                    'status' => $paymentIntent->status,
                    'created' => $paymentIntent->created,
                    'description' => $paymentIntent->description,
                    'last_payment_error' => $paymentIntent->last_payment_error,
                    'next_action' => $paymentIntent->next_action,
                    'payment_method' => $paymentIntent->payment_method,
                    'metadata' => $paymentIntent->metadata->toArray()
                ],
                'local_transaction' => $transaction ? [
                    'id' => $transaction->id,
                    'status' => $transaction->status,
                    'amount' => $transaction->amount,
                    'created_at' => $transaction->created_at,
                    'updated_at' => $transaction->updated_at,
                    'metadata' => $transaction->metadata
                ] : null
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to check Stripe payment intent status', [
                'payment_intent_id' => $request->payment_intent_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * List all transactions from our database with their Stripe status
     */
    public function listTransactions(Request $request)
    {
        try {
            $transactions = Transaction::orderBy('created_at', 'desc')
                ->take(20) // Limit to last 20 transactions
                ->get();

            $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));
            $transactionsWithStatus = [];

            foreach ($transactions as $transaction) {
                $stripeStatus = null;
                $stripeError = null;

                if ($transaction->stripe_payment_intent_id) {
                    try {
                        $paymentIntent = $stripe->paymentIntents->retrieve($transaction->stripe_payment_intent_id);
                        $stripeStatus = [
                            'status' => $paymentIntent->status,
                            'amount' => $paymentIntent->amount,
                            'currency' => $paymentIntent->currency,
                            'created' => $paymentIntent->created,
                            'last_payment_error' => $paymentIntent->last_payment_error
                        ];
                    } catch (\Exception $e) {
                        $stripeError = $e->getMessage();
                    }
                }

                $transactionsWithStatus[] = [
                    'id' => $transaction->id,
                    'stripe_payment_intent_id' => $transaction->stripe_payment_intent_id,
                    'amount' => $transaction->amount,
                    'local_status' => $transaction->status,
                    'description' => $transaction->description,
                    'created_at' => $transaction->created_at,
                    'stripe_status' => $stripeStatus,
                    'stripe_error' => $stripeError,
                    'metadata' => $transaction->metadata
                ];
            }

            return response()->json([
                'success' => true,
                'transactions' => $transactionsWithStatus,
                'count' => count($transactionsWithStatus)
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to list transactions with Stripe status', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync transaction status from Stripe
     */
    public function syncTransactionStatus(Request $request)
    {
        $request->validate([
            'transaction_id' => 'required|integer|exists:transactions,id'
        ]);

        try {
            $transaction = Transaction::findOrFail($request->transaction_id);

            if (!$transaction->stripe_payment_intent_id) {
                return response()->json([
                    'success' => false,
                    'error' => 'Transaction has no Stripe payment intent ID'
                ], 400);
            }

            $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));
            $paymentIntent = $stripe->paymentIntents->retrieve($transaction->stripe_payment_intent_id);

            // Update local status to match Stripe
            $oldStatus = $transaction->status;
            $transaction->status = $paymentIntent->status;
            $transaction->save();

            Log::info('Transaction status synced with Stripe', [
                'transaction_id' => $transaction->id,
                'old_status' => $oldStatus,
                'new_status' => $paymentIntent->status,
                'stripe_payment_intent_id' => $transaction->stripe_payment_intent_id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Transaction status synchronized',
                'old_status' => $oldStatus,
                'new_status' => $paymentIntent->status,
                'stripe_data' => [
                    'id' => $paymentIntent->id,
                    'status' => $paymentIntent->status,
                    'amount' => $paymentIntent->amount,
                    'last_payment_error' => $paymentIntent->last_payment_error
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to sync transaction status', [
                'transaction_id' => $request->transaction_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
