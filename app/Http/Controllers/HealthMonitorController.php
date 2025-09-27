<?php

namespace App\Http\Controllers;

use App\Models\QueuedTransaction;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\Account;
use OpenApi\Attributes as OA;

class HealthMonitorController extends Controller
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * Check health status of all services
     */
    /**
     * @OA\Get(
     *      path="/health/services",
     *      operationId="getServiceHealth",
     *      tags={"Service Health Monitor"},
     *      summary="Get health status of all services",
     *      description="Returns health status of all services",
     *      @OA\Response(
     *          response=200,
     *          description="successful operation"
     *       )
     *     )
     *
     * Returns health status of all services
     */
    public function getServiceHealth(): JsonResponse
    {
        $plaidHealth = $this->checkPlaidHealth();
        $stripeHealth = $this->checkStripeHealth();

        // Determine overall status
        $overallStatus = 'healthy';
        if ($plaidHealth['status'] === 'disabled' || $stripeHealth['status'] === 'disabled') {
            $overallStatus = 'testing'; // Special status for when services are manually disabled
        } elseif ($plaidHealth['status'] !== 'healthy' || $stripeHealth['status'] !== 'healthy') {
            $overallStatus = 'degraded';
        }

        return response()->json([
            'services' => [
                'plaid' => $plaidHealth,
                'stripe' => $stripeHealth
            ],
            'overall_status' => $overallStatus,
            'checked_at' => now()->toISOString()
        ]);
    }

    /**
     * Check Plaid service health
     */
    private function checkPlaidHealth(): array
    {
        // Check if Plaid is manually disabled in settings
        if (!Setting::isServiceEnabled('plaid')) {
            return [
                'status' => 'disabled',
                'error' => 'Service manually disabled for testing',
                'last_check' => now()->toISOString(),
                'manually_disabled' => true
            ];
        }

        try {
            $response = Http::timeout(10)->post('https://sandbox.plaid.com/institutions/get', [
                'client_id' => config('services.plaid.client_id'),
                'secret' => config('services.plaid.secret'),
                'country_codes' => ['US'],
                'count' => 1,
                'offset' => 0
            ]);

            if ($response->successful()) {
                return [
                    'status' => 'healthy',
                    'response_time' => $response->transferStats ?
                        round($response->transferStats->getTransferTime() * 1000) . 'ms' : 'N/A',
                    'last_check' => now()->toISOString()
                ];
            }

            return [
                'status' => 'unhealthy',
                'error' => 'HTTP ' . $response->status(),
                'last_check' => now()->toISOString()
            ];

        } catch (\Exception $e) {
            Log::error('Plaid health check failed', ['error' => $e->getMessage()]);

            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'last_check' => now()->toISOString()
            ];
        }
    }

    /**
     * Check Stripe service health
     */
    private function checkStripeHealth(): array
    {
        // Check if Stripe is manually disabled in settings
        if (!Setting::isServiceEnabled('stripe')) {
            return [
                'status' => 'disabled',
                'error' => 'Service manually disabled for testing',
                'last_check' => now()->toISOString(),
                'manually_disabled' => true
            ];
        }

        try {
            $startTime = microtime(true);

            // Simple API call to check if Stripe is responding
            Account::retrieve();

            $responseTime = round((microtime(true) - $startTime) * 1000);

            return [
                'status' => 'healthy',
                'response_time' => $responseTime . 'ms',
                'last_check' => now()->toISOString()
            ];

        } catch (\Exception $e) {
            Log::error('Stripe health check failed', ['error' => $e->getMessage()]);

            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'last_check' => now()->toISOString()
            ];
        }
    }

    /**
     * Get queued transactions waiting for retry
     */
    public function getQueuedTransactions(): JsonResponse
    {
        try {
            $queuedTransactions = QueuedTransaction::with(['fromAccount', 'toAccount'])
                ->orderBy('created_at', 'desc')
                ->get();

            $stats = [
                'total' => $queuedTransactions->count(),
                'queued' => $queuedTransactions->where('status', 'queued')->count(),
                'retrying' => $queuedTransactions->where('status', 'retrying')->count(),
                'failed' => $queuedTransactions->where('status', 'failed')->count(),
                'ready_for_retry' => QueuedTransaction::readyForRetry()->count()
            ];

            return response()->json([
                'success' => true,
                'transactions' => $queuedTransactions,
                'stats' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get queued transactions', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Manually retry a specific queued transaction
     */
    public function retryTransaction(Request $request, $id): JsonResponse
    {
        try {
            $queuedTransaction = QueuedTransaction::findOrFail($id);

            if (!$queuedTransaction->canRetry(true)) { // true = manual retry
                return response()->json([
                    'success' => false,
                    'error' => 'Transaction cannot be retried - status: ' . $queuedTransaction->status . ', retry count: ' . $queuedTransaction->retry_count . '/' . $queuedTransaction->max_retries
                ], 400);
            }

            // Check service health first
            $serviceHealth = $this->getServiceHealth();
            $services = $serviceHealth->getData()->services;

            if ($queuedTransaction->failed_service === 'plaid' && !in_array($services->plaid->status, ['healthy'])) {
                return response()->json([
                    'success' => false,
                    'error' => $services->plaid->status === 'disabled' ?
                        'Plaid service is manually disabled' : 'Plaid service is still unhealthy'
                ], 400);
            }

            if ($queuedTransaction->failed_service === 'stripe' && !in_array($services->stripe->status, ['healthy'])) {
                return response()->json([
                    'success' => false,
                    'error' => $services->stripe->status === 'disabled' ?
                        'Stripe service is manually disabled' : 'Stripe service is still unhealthy'
                ], 400);
            }

            // Mark as retrying
            $queuedTransaction->update(['status' => 'retrying']);

            // Actually retry the transaction
            $retryResult = $this->executeTransactionRetry($queuedTransaction);

            if ($retryResult['success']) {
                $queuedTransaction->markAsProcessed();

                return response()->json([
                    'success' => true,
                    'message' => 'Transaction retry completed successfully',
                    'transaction_id' => $id,
                    'result' => $retryResult
                ]);
            } else {
                // Increment retry count and potentially mark as failed
                $queuedTransaction->incrementRetry();
                if ($queuedTransaction->retry_count >= $queuedTransaction->max_retries) {
                    $queuedTransaction->markAsFailed();
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Transaction retry failed: ' . $retryResult['error'],
                    'transaction_id' => $id,
                    'retry_count' => $queuedTransaction->retry_count,
                    'max_retries' => $queuedTransaction->max_retries
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('Failed to retry transaction', [
                'transaction_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process all queued transactions that are ready for retry
     */
    public function processQueue(): JsonResponse
    {
        try {
            $readyTransactions = QueuedTransaction::readyForRetry()->get();
            $processed = 0;
            $failed = 0;

            foreach ($readyTransactions as $transaction) {
                try {
                    // Check if the failed service is healthy
                    $serviceHealth = $this->getServiceHealth();
                    $services = $serviceHealth->getData()->services;

                    $serviceHealthy = true;
                    if ($transaction->failed_service === 'plaid' && !in_array($services->plaid->status, ['healthy'])) {
                        $serviceHealthy = false;
                    }
                    if ($transaction->failed_service === 'stripe' && !in_array($services->stripe->status, ['healthy'])) {
                        $serviceHealthy = false;
                    }
                    if ($transaction->failed_service === 'both' &&
                        (!in_array($services->plaid->status, ['healthy']) || !in_array($services->stripe->status, ['healthy']))) {
                        $serviceHealthy = false;
                    }

                    if (!$serviceHealthy) {
                        continue; // Skip this transaction
                    }

                    // Actually retry the transaction
                    $retryResult = $this->executeTransactionRetry($transaction);

                    if ($retryResult['success']) {
                        $transaction->markAsProcessed();
                        $processed++;
                    } else {
                        throw new \Exception($retryResult['error']);
                    }

                } catch (\Exception $e) {
                    $transaction->incrementRetry();
                    if ($transaction->retry_count >= $transaction->max_retries) {
                        $transaction->markAsFailed();
                    }
                    $failed++;

                    Log::error('Failed to process queued transaction', [
                        'transaction_id' => $transaction->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Queue processing completed',
                'processed' => $processed,
                'failed' => $failed,
                'total_ready' => $readyTransactions->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to process queue', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Execute the actual retry of a queued transaction
     */
    private function executeTransactionRetry(QueuedTransaction $queuedTransaction): array
    {
        try {
            Log::info('Executing transaction retry', [
                'transaction_id' => $queuedTransaction->id,
                'original_data' => $queuedTransaction->original_request_data
            ]);

            // Get the original request data
            $originalData = $queuedTransaction->original_request_data;

            // Create a new request object from the original data
            $request = new \Illuminate\Http\Request();
            $request->merge($originalData);

            // Get the PlaidController instance
            $plaidController = app(\App\Http\Controllers\PlaidController::class);

            // Call the createACHTransfer method
            $response = $plaidController->createACHTransfer($request);
            $responseData = $response->getData(true);

            if ($response->getStatusCode() === 200 || $response->getStatusCode() === 201) {
                Log::info('Transaction retry successful', [
                    'transaction_id' => $queuedTransaction->id,
                    'response' => $responseData
                ]);

                return [
                    'success' => true,
                    'message' => 'Transaction processed successfully',
                    'response' => $responseData
                ];
            } else {
                Log::warning('Transaction retry failed with HTTP error', [
                    'transaction_id' => $queuedTransaction->id,
                    'status_code' => $response->getStatusCode(),
                    'response' => $responseData
                ]);

                return [
                    'success' => false,
                    'error' => $responseData['message'] ?? $responseData['error'] ?? 'Unknown error',
                    'status_code' => $response->getStatusCode(),
                    'response' => $responseData
                ];
            }

        } catch (\Exception $e) {
            Log::error('Exception during transaction retry', [
                'transaction_id' => $queuedTransaction->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'exception' => get_class($e)
            ];
        }
    }
}
