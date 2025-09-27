<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Transaction;

class MockTransferController extends Controller
{
    /**
     * Create a mock ACH transfer for testing when Plaid Processor API isn't available
     */
    public function createMockTransfer(Request $request)
    {
        try {
            $request->validate([
                'user_id' => 'required|exists:users,id',
                'amount' => 'required|numeric|min:0.01',
                'type' => 'required|in:lender_to_borrower,borrower_to_lender',
                'plaid_account_id' => 'required|string'
            ]);

            // Create a mock transaction that simulates real ACH processing
            $transaction = Transaction::create([
                'user_id' => $request->user_id,
                'amount' => $request->amount,
                'type' => $request->type,
                'status' => 'processing', // Simulate processing state
                'plaid_account_id' => $request->plaid_account_id,
                'network' => 'ach', // Simulate ACH network
                'plaid_transfer_id' => 'mock_' . uniqid(), // Mock transfer ID
                'stripe_payment_intent_id' => 'mock_pi_' . uniqid(),
                'description' => "Mock {$request->type} transfer for testing",
                'metadata' => json_encode([
                    'mock' => true,
                    'original_amount' => $request->amount,
                    'currency' => 'usd',
                    'account_id' => $request->plaid_account_id,
                    'timestamp' => now()->toISOString()
                ])
            ]);

            Log::info('Mock transfer created', [
                'transaction_id' => $transaction->id,
                'amount' => $request->amount,
                'type' => $request->type
            ]);

            // Simulate async processing - in real world this would be webhook-driven
            dispatch(function () use ($transaction) {
                sleep(2); // Simulate processing delay
                $this->simulateWebhookCallback($transaction->id);
            });

            return response()->json([
                'success' => true,
                'transaction_id' => $transaction->id,
                'status' => 'processing',
                'message' => 'Mock transfer initiated - will complete in ~5 seconds',
                'mock' => true
            ]);

        } catch (\Exception $e) {
            Log::error('Mock transfer creation failed', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Simulate webhook callback for mock transfers
     */
    private function simulateWebhookCallback($transactionId)
    {
        try {
            $transaction = Transaction::find($transactionId);
            if (!$transaction) return;

            // Simulate 90% success rate
            $success = rand(1, 100) <= 90;

            $transaction->update([
                'status' => $success ? 'completed' : 'failed',
                'completed_at' => now(),
                'metadata' => json_encode(array_merge(
                    json_decode($transaction->metadata, true) ?? [],
                    [
                        'simulation_completed' => true,
                        'completion_time' => now()->toISOString(),
                        'simulated_success' => $success
                    ]
                ))
            ]);

            Log::info('Mock transfer completed', [
                'transaction_id' => $transactionId,
                'status' => $transaction->status,
                'success' => $success
            ]);

        } catch (\Exception $e) {
            Log::error('Mock webhook simulation failed', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get mock bank account details for testing
     */
    public function getMockBankAccount(Request $request)
    {
        $mockAccounts = [
            'account_1' => [
                'account_id' => 'mock_checking_001',
                'name' => 'Mock Checking Account',
                'type' => 'depository',
                'subtype' => 'checking',
                'mask' => '0000',
                'institution_name' => 'Mock Bank',
                'balance' => [
                    'available' => 5000.00,
                    'current' => 5000.00
                ]
            ],
            'account_2' => [
                'account_id' => 'mock_savings_002',
                'name' => 'Mock Savings Account',
                'type' => 'depository',
                'subtype' => 'savings',
                'mask' => '1111',
                'institution_name' => 'Mock Credit Union',
                'balance' => [
                    'available' => 15000.00,
                    'current' => 15000.00
                ]
            ]
        ];

        $accountKey = $request->get('account_key', 'account_1');
        $account = $mockAccounts[$accountKey] ?? $mockAccounts['account_1'];

        return response()->json([
            'success' => true,
            'account' => $account,
            'mock' => true,
            'message' => 'Mock account data for testing'
        ]);
    }

    /**
     * List all mock transactions for debugging
     */
    public function listMockTransactions()
    {
        $mockTransactions = Transaction::where('plaid_transfer_id', 'LIKE', 'mock_%')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'transactions' => $mockTransactions,
            'count' => $mockTransactions->count(),
            'mock' => true
        ]);
    }
}
