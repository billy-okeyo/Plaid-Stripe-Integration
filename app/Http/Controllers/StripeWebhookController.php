<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Transaction;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    /**
     * Handle incoming Stripe webhooks for ACH payment status updates
     */
    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $endpointSecret = config('services.stripe.webhook_secret');

        try {
            // Verify webhook signature
            $event = Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
        } catch (\UnexpectedValueException $e) {
            Log::error('Invalid Stripe webhook payload', ['error' => $e->getMessage()]);
            return response('Invalid payload', 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Log::error('Invalid Stripe webhook signature', ['error' => $e->getMessage()]);
            return response('Invalid signature', 400);
        }

        // Handle the event
        switch ($event['type']) {
            case 'payment_intent.succeeded':
                $this->handlePaymentIntentSucceeded($event['data']['object']);
                break;

            case 'payment_intent.payment_failed':
                $this->handlePaymentIntentFailed($event['data']['object']);
                break;

            case 'payment_intent.processing':
                $this->handlePaymentIntentProcessing($event['data']['object']);
                break;

            case 'payment_intent.requires_action':
                $this->handlePaymentIntentRequiresAction($event['data']['object']);
                break;

            case 'payment_intent.canceled':
                $this->handlePaymentIntentCanceled($event['data']['object']);
                break;

            default:
                Log::info('Received unhandled Stripe webhook', ['event_type' => $event['type']]);
        }

        return response('Webhook handled', 200);
    }

    /**
     * Handle successful payment intent (ACH transfer completed)
     */
    private function handlePaymentIntentSucceeded($paymentIntent)
    {
        Log::info('ACH Transfer Succeeded', [
            'payment_intent_id' => $paymentIntent['id'],
            'amount' => $paymentIntent['amount'],
            'metadata' => $paymentIntent['metadata']
        ]);

        // Update transaction record
        $transaction = Transaction::where('stripe_payment_intent_id', $paymentIntent['id'])->first();

        if ($transaction) {
            $transaction->update([
                'status' => 'succeeded',
                'metadata' => array_merge($transaction->metadata ?? [], [
                    'stripe_webhook_received' => now()->toISOString(),
                    'stripe_payment_succeeded' => $paymentIntent,
                    'completion_time' => now()->toISOString()
                ])
            ]);

            Log::info('Transaction status updated to succeeded', [
                'transaction_id' => $transaction->id,
                'payment_intent_id' => $paymentIntent['id']
            ]);

            // Here you could send notifications to users, update balances, etc.
        } else {
            Log::warning('Transaction not found for succeeded payment intent', [
                'payment_intent_id' => $paymentIntent['id']
            ]);
        }
    }

    /**
     * Handle failed payment intent (ACH transfer failed)
     */
    private function handlePaymentIntentFailed($paymentIntent)
    {
        Log::warning('ACH Transfer Failed', [
            'payment_intent_id' => $paymentIntent['id'],
            'amount' => $paymentIntent['amount'],
            'last_payment_error' => $paymentIntent['last_payment_error'],
            'metadata' => $paymentIntent['metadata']
        ]);

        // Update transaction record
        $transaction = Transaction::where('stripe_payment_intent_id', $paymentIntent['id'])->first();

        if ($transaction) {
            $transaction->update([
                'status' => 'failed',
                'failure_reason' => $paymentIntent['last_payment_error']['message'] ?? 'Payment failed',
                'metadata' => array_merge($transaction->metadata ?? [], [
                    'stripe_webhook_received' => now()->toISOString(),
                    'stripe_payment_failed' => $paymentIntent,
                    'failure_details' => $paymentIntent['last_payment_error'],
                    'failure_time' => now()->toISOString()
                ])
            ]);

            Log::info('Transaction status updated to failed', [
                'transaction_id' => $transaction->id,
                'payment_intent_id' => $paymentIntent['id'],
                'failure_reason' => $paymentIntent['last_payment_error']['message'] ?? 'Unknown'
            ]);

            // Here you could send failure notifications to users
        }
    }

    /**
     * Handle processing payment intent (ACH transfer in progress)
     */
    private function handlePaymentIntentProcessing($paymentIntent)
    {
        Log::info('ACH Transfer Processing', [
            'payment_intent_id' => $paymentIntent['id'],
            'amount' => $paymentIntent['amount']
        ]);

        $transaction = Transaction::where('stripe_payment_intent_id', $paymentIntent['id'])->first();

        if ($transaction) {
            $transaction->update([
                'status' => 'processing',
                'metadata' => array_merge($transaction->metadata ?? [], [
                    'stripe_webhook_received' => now()->toISOString(),
                    'processing_started' => now()->toISOString(),
                    'stripe_processing_update' => $paymentIntent
                ])
            ]);
        }
    }

    /**
     * Handle payment intent requiring additional action
     */
    private function handlePaymentIntentRequiresAction($paymentIntent)
    {
        Log::info('ACH Transfer Requires Action', [
            'payment_intent_id' => $paymentIntent['id'],
            'next_action' => $paymentIntent['next_action']
        ]);

        $transaction = Transaction::where('stripe_payment_intent_id', $paymentIntent['id'])->first();

        if ($transaction) {
            $transaction->update([
                'status' => 'requires_action',
                'metadata' => array_merge($transaction->metadata ?? [], [
                    'stripe_webhook_received' => now()->toISOString(),
                    'next_action_required' => $paymentIntent['next_action'],
                    'requires_action_time' => now()->toISOString()
                ])
            ]);
        }
    }

    /**
     * Handle canceled payment intent
     */
    private function handlePaymentIntentCanceled($paymentIntent)
    {
        Log::info('ACH Transfer Canceled', [
            'payment_intent_id' => $paymentIntent['id'],
            'cancellation_reason' => $paymentIntent['cancellation_reason']
        ]);

        $transaction = Transaction::where('stripe_payment_intent_id', $paymentIntent['id'])->first();

        if ($transaction) {
            $transaction->update([
                'status' => 'cancelled',
                'failure_reason' => $paymentIntent['cancellation_reason'] ?? 'Transfer canceled',
                'metadata' => array_merge($transaction->metadata ?? [], [
                    'stripe_webhook_received' => now()->toISOString(),
                    'canceled_time' => now()->toISOString(),
                    'cancellation_reason' => $paymentIntent['cancellation_reason']
                ])
            ]);
        }
    }
}
