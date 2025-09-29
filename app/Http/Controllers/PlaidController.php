<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\PlaidAccount;
use App\Models\LendingUser;
use App\Models\Transaction;
use App\Models\QueuedTransaction;
use App\Models\Setting;
use Stripe\StripeClient;

class PlaidController extends Controller
{
    private string $clientId;
    private string $secret;
    private string $baseUrl;
    private string $environment;

    public function __construct()
    {
        $useProduction = config('app.use_production_apis', false);

        if ($useProduction) {
            // Production configuration
            $this->clientId = config('services.plaid.prod_client_id') ?? env('PLAID_PROD_CLIENT_ID');
            $this->secret = config('services.plaid.prod_secret') ?? env('PLAID_PROD_SECRET');
            $this->baseUrl = config('services.plaid.prod_base_url') ?? env('PLAID_PROD_URL', 'https://production.plaid.com');
            $this->environment = config('services.plaid.prod_environment') ?? env('PLAID_PROD_ENV', 'production');
        } else {
            // Sandbox configuration (default)
            $this->clientId = config('services.plaid.client_id') ?? env('PLAID_CLIENT_ID');
            $this->secret = config('services.plaid.secret') ?? env('PLAID_SECRET');
            $this->baseUrl = config('services.plaid.base_url') ?? env('PLAID_URL', 'https://sandbox.plaid.com');
            $this->environment = config('services.plaid.environment') ?? env('PLAID_ENV', 'sandbox');
        }
    }

    /**
     * Create a link token for Plaid Link initialization
     */
    public function createLinkToken(Request $request): JsonResponse
    {
        try {
            // Log all incoming request data for debugging
            Log::info('Create Link Token Request', [
                'all_data' => $request->all(),
                'user_id_input' => $request->input('user_id'),
                'has_user_id' => $request->has('user_id'),
                'method' => $request->method(),
                'content_type' => $request->header('Content-Type')
            ]);

            // Ensure we have a valid client_user_id (must be a string)
            $clientUserId = $request->input('user_id');
            if (empty($clientUserId)) {
                $clientUserId = 'demo_user_' . uniqid();
            } else {
                // Convert to string if it's an integer (Plaid requires string)
                $clientUserId = (string) $clientUserId;
            }

            Log::info('Creating Plaid Link Token', [
                'client_user_id' => $clientUserId,
                'request_user_id' => $request->input('user_id'),
                'client_id' => $this->clientId,
                'base_url' => $this->baseUrl
            ]);

            // Determine which products to request based on environment configuration
            $useProduction = config('app.use_production_apis', false);

            if ($useProduction) {
                $productsString = env('PLAID_PROD_PRODUCTS', 'auth');
                $products = explode(',', $productsString);
            } else {
                $productsString = env('PLAID_SANDBOX_PRODUCTS', 'auth,transactions');
                $products = explode(',', $productsString);
            }

            // Clean up product names (trim whitespace)
            $products = array_map('trim', $products);

            Log::info('Plaid products to request', [
                'use_production' => $useProduction,
                'products_string' => $productsString,
                'products' => $products,
                'environment' => $this->environment
            ]);

            $requestData = [
                'client_id' => $this->clientId,
                'secret' => $this->secret,
                'user' => [
                    'client_user_id' => $clientUserId,
                ],
                'client_name' => config('app.name') . ' - Plaid Integration Demo',
                'products' => $products,
                'country_codes' => ['US'],
                'language' => 'en',
                'webhook' => config('app.url') . '/plaid/webhook',
            ];

            Log::info('Plaid API Request Data', ['request_data' => $requestData]);

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/link/token/create", $requestData);

            if ($response->successful()) {
                return response()->json($response->json());
            }

            Log::error('Plaid Link Token Creation Failed', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return response()->json([
                'error' => 'Failed to create link token',
                'details' => $response->json()
            ], 400);

        } catch (\Exception $e) {
            Log::error('Plaid Link Token Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Internal server error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exchange public token for access token and save accounts to database
     */
    public function exchangeToken(Request $request): JsonResponse
    {
        $request->validate([
            'public_token' => 'required|string',
            'metadata' => 'required|array',
            'user_id' => 'required|integer|exists:lending_users,id',
        ]);

        try {
            Log::info('Exchanging Plaid public token for access token', [
                'public_token' => substr($request->public_token, 0, 10) . '***',
                'user_id' => $request->user_id,
                'metadata' => $request->metadata
            ]);

            // Exchange the public token for an access token
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/item/public_token/exchange", [
                'client_id' => $this->clientId,
                'secret' => $this->secret, // Use the correct secret
                'public_token' => $request->public_token,
            ]);

            if (!$response->successful()) {
                Log::error('Plaid Token Exchange Failed', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);

                return response()->json([
                    'error' => 'Failed to exchange token',
                    'details' => $response->json()
                ], 400);
            }

            $tokenData = $response->json();
            $accessToken = $tokenData['access_token'];
            $itemId = $tokenData['item_id'];

            // Get account information using the access token
            $accountsResponse = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/accounts/get", [
                'client_id' => $this->clientId,
                'secret' => $this->secret, // Use the correct secret
                'access_token' => $accessToken,
            ]);

            if (!$accountsResponse->successful()) {
                Log::error('Plaid Get Accounts Failed after token exchange', [
                    'status' => $accountsResponse->status(),
                    'response' => $accountsResponse->body()
                ]);

                return response()->json([
                    'error' => 'Failed to get account information',
                    'details' => $accountsResponse->json()
                ], 400);
            }

            $accountsData = $accountsResponse->json();

            // Save accounts with proper parameters
            $savedAccounts = $this->saveAccountsToDatabase(
                $accountsData,
                $request->user_id,
                $itemId,
                $accessToken,
                $request->metadata
            );

            return response()->json([
                'success' => true,
                'access_token' => $accessToken,
                'item_id' => $itemId,
                'request_id' => $tokenData['request_id'],
                'accounts_saved' => count($savedAccounts),
                'accounts' => $savedAccounts,
                'stripe_integration_summary' => [
                    'total_accounts' => count($savedAccounts),
                    'stripe_ready_accounts' => count(array_filter($savedAccounts, fn($account) => $account['stripe_ready'])),
                    'failed_integrations' => count(array_filter($savedAccounts, fn($account) => !$account['stripe_ready'])),
                ],
                'message' => 'Token exchange successful, accounts saved, and Stripe bank account tokens created where possible'
            ]);

        } catch (\Exception $e) {
            Log::error('Token Exchange Exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Token exchange failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }



    public function saveAccountsToDatabase($accountsData, $userId, $itemId, $accessToken, $metadata)
    {
        $savedAccounts = [];

        // Save each account to the database and create Stripe bank account tokens
        foreach ($accountsData['accounts'] as $account) {
            $plaidAccount = PlaidAccount::updateOrCreate(
                [
                    'plaid_account_id' => $account['account_id'],
                ],
                [
                    'user_id' => $userId,
                    'plaid_item_id' => $itemId,
                    'access_token' => $accessToken, // In production, encrypt this
                    'account_name' => $account['name'],
                    'account_type' => $account['type'],
                    'account_subtype' => $account['subtype'],
                    'institution_name' => $metadata['institution']['name'],
                    'institution_id' => $metadata['institution']['institution_id'],
                    'available_balance' => $account['balances']['available'] ?? null,
                    'current_balance' => $account['balances']['current'] ?? null,
                    'currency_code' => $account['balances']['iso_currency_code'] ?? 'USD',
                    'metadata' => [
                        'account' => $account,
                        'institution' => $metadata['institution'],
                        'link_session_id' => $metadata['link_session_id'] ?? null,
                    ],
                    'is_active' => true,
                ]
            );

            // Create persistent Stripe Customer + Bank Account for this account
            $stripeIntegration = null;
            $stripeTokenStatus = 'not_attempted';

            try {
                Log::info('Creating persistent Stripe Customer + Bank Account during Link flow', [
                    'plaid_account_id' => $plaidAccount->plaid_account_id,
                    'account_name' => $plaidAccount->account_name,
                    'institution_name' => $plaidAccount->institution_name
                ]);

                $stripeIntegration = $this->createStripeCustomerWithBankAccount($plaidAccount);

                if ($stripeIntegration) {
                    $stripeTokenStatus = 'success';

                    Log::info('Stripe Customer + Bank Account created and stored successfully', [
                        'plaid_account_id' => $plaidAccount->plaid_account_id,
                        'customer_id' => $stripeIntegration['customer_id'],
                        'bank_account_id' => $stripeIntegration['bank_account_id'],
                        'verification_required' => $stripeIntegration['verification_required']
                    ]);
                } else {
                    $stripeTokenStatus = 'failed';
                    $plaidAccount->update(['stripe_integration_status' => 'failed']);

                    Log::warning('Failed to create persistent Stripe Customer + Bank Account', [
                        'plaid_account_id' => $plaidAccount->plaid_account_id
                    ]);
                }
            } catch (\Exception $e) {
                $stripeTokenStatus = 'error';
                $plaidAccount->update(['stripe_integration_status' => 'error']);

                Log::error('Exception creating persistent Stripe Customer + Bank Account', [
                    'plaid_account_id' => $plaidAccount->plaid_account_id,
                    'error' => $e->getMessage()
                ]);
            }

            $savedAccounts[] = [
                'id' => $plaidAccount->id,
                'plaid_account_id' => $plaidAccount->plaid_account_id,
                'account_name' => $plaidAccount->account_name,
                'account_type' => $plaidAccount->account_type,
                'account_subtype' => $plaidAccount->account_subtype,
                'institution_name' => $plaidAccount->institution_name,
                'available_balance' => $plaidAccount->available_balance,
                'current_balance' => $plaidAccount->current_balance,
                'formatted_available_balance' => $plaidAccount->formatted_available_balance,
                'formatted_current_balance' => $plaidAccount->formatted_current_balance,
                'stripe_integration_status' => $stripeTokenStatus,
                'stripe_ready' => $stripeIntegration !== null,
                'stripe_customer_id' => $plaidAccount->stripe_customer_id,
                'stripe_bank_account_id' => $plaidAccount->stripe_bank_account_id,
                'verification_required' => $stripeIntegration ? $stripeIntegration['verification_required'] : null,
                'integration_method' => $stripeIntegration ? 'persistent_customer_account' : 'none'
            ];
        }

        Log::info('Plaid accounts saved to database with Stripe integration', [
            'item_id' => $itemId,
            'accounts_count' => count($savedAccounts),
            'institution' => $metadata['institution']['name'],
            'stripe_tokens_created' => count(array_filter($savedAccounts, fn($account) => $account['stripe_ready']))
        ]);

        return $savedAccounts;
    }

    /**
     * Create a generic processor token for third-party integrations
     */
    public function createProcessorToken(string $accessToken, string $accountId, string $processor = 'stripe'): ?string
    {
        try {
            Log::info('Creating generic processor token', [
                'access_token' => substr($accessToken, 0, 10) . '***',
                'account_id' => $accountId,
                'processor' => $processor
            ]);

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/processor/token/create", [
                // 'client_id' => $this->clientId,
                // 'secret' => $this->secret,
                'access_token' => $accessToken,
                'account_id' => $accountId,
                'processor' => $processor,
            ]);

            if (!$response->successful()) {
                Log::error('Failed to create processor token', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'processor' => $processor
                ]);
                return null;
            }

            $data = $response->json();
            $processorToken = $data['processor_token'] ?? null;

            if ($processorToken) {
                Log::info('Successfully created processor token', [
                    'processor' => $processor,
                    'token_prefix' => substr($processorToken, 0, 10) . '***'
                ]);
            }

            return $processorToken;

        } catch (\Exception $e) {
            Log::error('Exception creating processor token', [
                'error' => $e->getMessage(),
                'processor' => $processor,
                'account_id' => $accountId
            ]);
            return null;
        }
    }



    /**
     * Get account information
     */
    public function getAccounts(Request $request): JsonResponse
    {
        $request->validate([
            'access_token' => 'required|string',
        ]);

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/accounts/get", [
                'client_id' => $this->clientId,
                'secret' => $this->secret,
                'access_token' => $request->access_token,
            ]);

            if ($response->successful()) {
                return response()->json($response->json());
            }

            Log::error('Plaid Get Accounts Failed', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return response()->json([
                'error' => 'Failed to get accounts',
                'details' => $response->json()
            ], 400);

        } catch (\Exception $e) {
            Log::error('Plaid Get Accounts Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Internal server error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get stored accounts for a specific user
     */
    public function getUserAccounts(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer|exists:lending_users,id',
        ]);

        try {
            $accounts = PlaidAccount::where('user_id', $request->user_id)
                ->where('is_active', true)
                ->get()
                ->map(function ($account) {
                    return [
                        'id' => $account->id,
                        'plaid_account_id' => $account->plaid_account_id,
                        'account_name' => $account->account_name,
                        'account_type' => $account->account_type,
                        'account_subtype' => $account->account_subtype,
                        'institution_name' => $account->institution_name,
                        'available_balance' => $account->available_balance,
                        'current_balance' => $account->current_balance,
                        'formatted_available_balance' => $account->formatted_available_balance,
                        'formatted_current_balance' => $account->formatted_current_balance,
                        'currency_code' => $account->currency_code,
                    ];
                });

            return response()->json([
                'success' => true,
                'accounts' => $accounts,
                'count' => $accounts->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Get User Accounts Exception', [
                'user_id' => $request->user_id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Failed to get user accounts',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get account balances
     */
    public function getBalances(Request $request): JsonResponse
    {
        $request->validate([
            'access_token' => 'required|string',
        ]);

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/accounts/balance/get", [
                'client_id' => $this->clientId,
                'secret' => $this->secret,
                'access_token' => $request->access_token,
            ]);

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return response()->json([
                'error' => 'Failed to get balances',
                'details' => $response->json()
            ], 400);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Internal server error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle Plaid webhooks
     */
    public function webhook(Request $request): JsonResponse
    {
        Log::info('Plaid Webhook Received', $request->all());

        // In a real application, you'd process the webhook data
        // For now, just acknowledge receipt
        return response()->json(['status' => 'received']);
    }

    /**
     * Get stored accounts from database
     */
    /**
     * @OA\Get(
     *      path="/plaid/stored-accounts",
     *      operationId="getStoredAccounts",
     *      tags={"Bank Accounts List"},
     *      summary="Get list of connected bank accounts",
     *      description="Returns list of connected bank accounts",
     *      @OA\Response(
     *          response=200,
     *          description="successful operation"
     *       )
     *     )
     *
     * Returns list of connected bank accounts
     */
    public function getStoredAccounts(Request $request): JsonResponse
    {
        try {
            $userId = $request->user_id ?? null;

            $query = PlaidAccount::where('is_active', true);

            if ($userId) {
                $query->where('user_id', $userId);
            }

            $accounts = $query->orderBy('created_at', 'desc')->get();

            $formattedAccounts = $accounts->map(function ($account) {
                $requiresRelink = $account->metadata['requires_relink'] ?? false;
                $errorStatus = $account->metadata['error_status'] ?? null;

                return [
                    'id' => $account->id,
                    'user_id' => $account->user_id,
                    'plaid_account_id' => $account->plaid_account_id,
                    'account_name' => $account->account_name,
                    'account_type' => $account->account_type,
                    'account_subtype' => $account->account_subtype,
                    'institution_name' => $account->institution_name,
                    'available_balance' => $account->available_balance,
                    'current_balance' => $account->current_balance,
                    'formatted_available_balance' => $account->formatted_available_balance,
                    'formatted_current_balance' => $account->formatted_current_balance,
                    'currency_code' => $account->currency_code,
                    'connected_at' => $account->created_at->format('M d, Y g:i A'),
                    'requires_relink' => $requiresRelink,
                    'error_status' => $errorStatus,
                    'status' => $requiresRelink ? 'requires_relink' : 'connected',
                    'error_message' => $requiresRelink ? ($account->metadata['error_message'] ?? 'Connection expired') : null,
                    'stripe_integration_status' => $account->stripe_integration_status ?? 'pending',
                    'stripe_status_display' => $account->stripe_status_display ?? '❓ Unknown',
                    'connection_status' => $account->connection_status ?? 'connected',
                    'connection_status_display' => $account->connection_status_display ?? '❓ Unknown',
                    'has_stripe_token' => $account->hasValidStripeToken(),
                    'stripe_token_created_at' => $account->stripe_token_created_at?->format('M d, Y g:i A'),
                ];
            });

            return response()->json([
                'success' => true,
                'accounts' => $formattedAccounts,
                'total' => $accounts->count(),
                'message' => 'Accounts retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Get Stored Accounts Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Internal server error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Disconnect/remove an account
     */
    public function disconnectAccount(Request $request, $accountId): JsonResponse
    {
        try {
            $account = PlaidAccount::findOrFail($accountId);

            // Mark as inactive instead of deleting
            $account->update(['is_active' => false]);

            Log::info('Plaid account disconnected', [
                'account_id' => $accountId,
                'plaid_account_id' => $account->plaid_account_id,
                'institution' => $account->institution_name
            ]);

            return response()->json([
                'message' => 'Account disconnected successfully',
                'account_id' => $accountId
            ]);

        } catch (\Exception $e) {
            Log::error('Disconnect Account Exception', [
                'message' => $e->getMessage(),
                'account_id' => $accountId
            ]);

            return response()->json([
                'error' => 'Failed to disconnect account',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Map Stripe PaymentIntent status to database-allowed transaction status
     */
    private function mapStripeStatusToDatabase($stripeStatus)
    {
        $statusMap = [
            'requires_confirmation' => 'pending',
            'requires_action' => 'pending',
            'processing' => 'processing',
            'requires_capture' => 'processing',
            'canceled' => 'cancelled',
            'succeeded' => 'succeeded',
            'requires_payment_method' => 'failed',
        ];

        return $statusMap[$stripeStatus] ?? 'pending';
    }

    /**
     * Create real ACH transfer using Plaid Processor + Stripe integration with fallback
     */
    /**
     * @OA\Post(
     *      path="/api/plaid/create-ach-transfer",
     *      tags={"ACH Transfer"},
     *      summary="Create real ACH transfer using Plaid Processor + Stripe integration with fallback",
     *      description="Returns real ACH transfer using Plaid Processor + Stripe integration with fallback",
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="from_account_id", type="integer"),
     *              @OA\Property(property="to_account_id", type="integer"),
     *              @OA\Property(property="amount", type="number"),
     *              @OA\Property(property="description", type="string")
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="successful operation"
     *       )
     *     )
     *
     * Returns real ACH transfer using Plaid Processor + Stripe integration with fallback
     */
    public function createACHTransfer(Request $request): JsonResponse
    {
        // Increase time limit for this operation
        set_time_limit(120);

        $request->validate([
            'from_account_id' => 'required|integer|exists:plaid_accounts,id',
            'to_account_id' => 'required|integer|exists:plaid_accounts,id',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:255',
            'email' => 'required|email|max:255',
            'payment_method' => 'nullable|in:payment_intents,charges', // Optional override
            'verification_method' => 'nullable|in:instant,microdeposit', // User preference
        ]);

        try {
            Log::info('Starting ACH Transfer Creation', [
                'from_account_id' => $request->from_account_id,
                'to_account_id' => $request->to_account_id,
                'amount' => $request->amount,
                'email' => $request->email
            ]);

            // Get source and destination accounts with user relationships
            $fromAccount = PlaidAccount::with('user')->findOrFail($request->from_account_id);
            $toAccount = PlaidAccount::with('user')->findOrFail($request->to_account_id);

            Log::info('Accounts loaded successfully', [
                'from_account' => [
                    'id' => $fromAccount->id,
                    'plaid_account_id' => $fromAccount->plaid_account_id,
                    'account_name' => $fromAccount->account_name,
                    'has_access_token' => !empty($fromAccount->access_token),
                    'user_id' => $fromAccount->user_id
                ],
                'to_account' => [
                    'id' => $toAccount->id,
                    'plaid_account_id' => $toAccount->plaid_account_id,
                    'account_name' => $toAccount->account_name,
                    'has_access_token' => !empty($toAccount->access_token),
                    'user_id' => $toAccount->user_id
                ]
            ]);

            // Ensure accounts belong to different users (lending platform requirement)
            if ($fromAccount->user_id === $toAccount->user_id) {
                return response()->json([
                    'error' => 'Cannot transfer between accounts of the same user',
                    'message' => 'ACH transfers must be between different users (lender and borrower)'
                ], 400);
            }

            // Check if services are available (for testing purposes)
            $plaidEnabled = Setting::isServiceEnabled('plaid');
            $stripeEnabled = Setting::isServiceEnabled('stripe');

            Log::info('Service availability check', [
                'plaid_enabled' => $plaidEnabled,
                'stripe_enabled' => $stripeEnabled
            ]);

            // Simulate service unavailability by throwing appropriate exceptions
            if (!$plaidEnabled) {
                Log::info('Plaid service manually disabled - simulating service failure');
                throw new \Exception('Plaid service temporarily unavailable (simulated for testing)', 503);
            }

            if (!$stripeEnabled) {
                Log::info('Stripe service manually disabled - simulating service failure');
                throw new \Exception('Stripe service temporarily unavailable (simulated for testing)', 503);
            }

            // Convert amount to cents for Stripe
            $amountInCents = intval($request->amount * 100);

            // Determine payment method: PaymentIntents (modern) vs Charges (legacy)
            $paymentMethod = $request->payment_method ?? config('services.stripe.ach_payment_method', 'payment_intents');
            $verificationMethod = $request->verification_method ?? (config('services.stripe.instant_verification', true) ? 'instant' : 'microdeposit');

            Log::info('ACH Payment Configuration', [
                'payment_api' => $paymentMethod,
                'verification_method' => $verificationMethod,
                'user_override' => $request->has('payment_method') || $request->has('verification_method')
            ]);

            // Route to appropriate payment method
            // Route to appropriate payment method with Financial Connections priority
            if ($paymentMethod === 'charges') {
                return $this->createACHTransferWithCharges($request, $fromAccount, $toAccount, $amountInCents, $verificationMethod);
            } else {
                // For PaymentIntents API, check if we should use Financial Connections for instant verification
                if ($verificationMethod === 'instant' && config('services.stripe.financial_connections.enabled', true)) {
                    Log::info('Using Financial Connections for instant verification');
                    return $this->createACHTransferWithFinancialConnectionsDirect($request, $fromAccount, $toAccount, $amountInCents);
                }

                // Otherwise use traditional Plaid processor method
                return $this->createACHTransferWithPaymentIntents($request, $fromAccount, $toAccount, $amountInCents, $verificationMethod);
            }

        } catch (\Stripe\Exception\CardException $e) {
            Log::error('Stripe ACH Transfer Failed', [
                'error' => $e->getMessage(),
                'decline_code' => $e->getDeclineCode(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'error' => 'ACH transfer declined',
                'message' => $e->getMessage(),
                'decline_code' => $e->getDeclineCode()
            ], 400);

        } catch (\Exception $e) {
            Log::error('ACH Transfer Creation Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Internal server error',
                'message' => 'Failed to create ACH transfer: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create ACH Transfer using PaymentIntents API (modern, with instant verification)
     */
    private function createACHTransferWithPaymentIntents(Request $request, PlaidAccount $fromAccount, PlaidAccount $toAccount, int $amountInCents, string $verificationMethod): JsonResponse
    {
        Log::info('Creating ACH Transfer with PaymentIntents API', [
            'verification_method' => $verificationMethod,
            'amount' => $amountInCents,
            'instant_verification_requested' => $verificationMethod === 'instant'
        ]);

        try {
            // Initialize Stripe client
            $useProduction = config('app.use_production_apis', false);
            $stripeSecret = $useProduction
                ? (config('services.stripe.prod_secret') ?? env('STRIPE_PROD_SECRET_KEY'))
                : (config('services.stripe.secret') ?? env('STRIPE_SECRET_KEY'));
            $stripe = new \Stripe\StripeClient($stripeSecret);

            // Get user details for billing
            $fromUser = $fromAccount->user;
            $billingName = $fromUser ? $fromUser->name : 'Account Holder';
            $billingEmail = $fromUser ? $fromUser->email : $request->email;

            if ($verificationMethod === 'instant') {
                // Instant Verification: Use Plaid Processor token if available, otherwise fallback to microdeposits
                Log::info('Attempting instant verification with Plaid Processor token');

                $bankAccountToken = $this->createStripeBankAccountToken($fromAccount);

                if (!$bankAccountToken) {
                    // Processor API not available - fallback to microdeposit verification
                    Log::warning('Processor API not available, falling back to microdeposit verification');
                    return $this->createACHTransferWithMicrodeposits($request, $fromAccount, $toAccount, $amountInCents, $stripe);
                }

                // Handle different token types
                if ($bankAccountToken === 'pm_usBankAccount_success') {
                    // Test/sandbox mode - use test token directly
                    Log::info('Using Stripe test token for instant verification demo (sandbox mode)');
                    $paymentMethodId = $bankAccountToken;
                } elseif (str_starts_with($bankAccountToken, 'btok_')) {
                    // Production mode - bank account token from Plaid, need to create PaymentMethod
                    Log::info('Converting Plaid bank account token to Stripe PaymentMethod', [
                        'bank_account_token' => substr($bankAccountToken, 0, 10) . '***'
                    ]);

                    // Get user details for billing
                    $fromUser = $fromAccount->user;
                    $billingName = null;
                    if ($fromUser) {
                        if (!empty($fromUser->name)) {
                            $billingName = $fromUser->name;
                        } elseif (!empty($fromUser->first_name) && !empty($fromUser->last_name)) {
                            $billingName = $fromUser->first_name . ' ' . $fromUser->last_name;
                        } elseif (!empty($fromUser->first_name)) {
                            $billingName = $fromUser->first_name;
                        } elseif (!empty($fromUser->business_name)) {
                            $billingName = $fromUser->business_name;
                        }
                    }
                    if (empty($billingName)) {
                        $billingName = 'Account Holder';
                    }
                    $billingEmail = $fromUser && !empty($fromUser->email) ? $fromUser->email : $request->email;

                    try {
                        // Create PaymentMethod from bank account token
                        $paymentMethod = $stripe->paymentMethods->create([
                            'type' => 'us_bank_account',
                            'us_bank_account' => [
                                'bank_account_token' => $bankAccountToken,
                                'account_holder_type' => 'individual',
                            ],
                            'billing_details' => [
                                'name' => $billingName,
                                'email' => $billingEmail,
                            ],
                        ]);

                        $paymentMethodId = $paymentMethod->id;
                        Log::info('Successfully created PaymentMethod from bank account token', [
                            'payment_method_id' => $paymentMethodId,
                            'bank_account_token' => substr($bankAccountToken, 0, 10) . '***'
                        ]);

                    } catch (\Exception $e) {
                        Log::error('Failed to create PaymentMethod from bank account token', [
                            'error' => $e->getMessage(),
                            'bank_account_token' => substr($bankAccountToken, 0, 10) . '***'
                        ]);

                        // Fall back to microdeposit verification
                        Log::warning('Falling back to microdeposit verification due to PaymentMethod creation failure');
                        return $this->createACHTransferWithMicrodeposits($request, $fromAccount, $toAccount, $amountInCents, $stripe);
                    }
                } else {
                    // Unknown token type
                    Log::warning('Unknown token type received from Processor API', [
                        'token' => substr($bankAccountToken, 0, 10) . '***'
                    ]);
                    return $this->createACHTransferWithMicrodeposits($request, $fromAccount, $toAccount, $amountInCents, $stripe);
                }

                $paymentIntent = $stripe->paymentIntents->create([
                    'amount' => $amountInCents,
                    'currency' => 'usd',
                    'payment_method' => $paymentMethodId,
                    'payment_method_types' => ['us_bank_account'],
                    'payment_method_options' => [
                        'us_bank_account' => [
                            'verification_method' => 'instant',
                            'financial_connections' => [
                                'permissions' => ['payment_method']
                            ]
                        ]
                    ],
                    'description' => $request->description ?? 'ACH Transfer via Lending Platform',
                    'receipt_email' => $request->email,
                    'metadata' => [
                        'from_account_id' => $fromAccount->user_id,
                        'to_account_id' => $toAccount->user_id,
                        'plaid_from_account_id' => $fromAccount->id,
                        'plaid_to_account_id' => $toAccount->id,
                        'platform' => 'lending_platform',
                        'transfer_type' => 'ach_debit_instant_verification',
                        'verification_method' => 'instant'
                    ],
                    'confirm' => true,
                    'return_url' => config('app.url') . '/transfer/return',
                ]);

                // Store the transaction record
                $transaction = Transaction::create([
                    'stripe_payment_intent_id' => $paymentIntent->id,
                    'amount' => number_format($amountInCents / 100, 2, '.', ''),
                    'description' => $request->description ?? 'ACH Transfer via Lending Platform',
                    'local_status' => $paymentIntent->status === 'succeeded' ? 'processing' : 'pending',
                    'metadata' => [
                        'method_used' => 'payment_intents_api',
                        'verification_method' => 'instant',
                        'stripe_payment_intent' => $paymentIntent->toArray(),
                        'from_account_details' => [
                            'plaid_account_id' => $fromAccount->plaid_account_id,
                            'account_name' => $fromAccount->account_name,
                            'institution_name' => $fromAccount->institution_name,
                            'user_id' => $fromAccount->user_id
                        ],
                        'to_account_details' => [
                            'plaid_account_id' => $toAccount->plaid_account_id,
                            'account_name' => $toAccount->account_name,
                            'institution_name' => $toAccount->institution_name,
                            'user_id' => $toAccount->user_id
                        ]
                    ]
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'ACH transfer initiated successfully with instant verification',
                    'transfer' => [
                        'id' => $transaction->id,
                        'payment_intent_id' => $paymentIntent->id,
                        'amount' => $amountInCents / 100,
                        'status' => $paymentIntent->status,
                        'verification_method' => 'instant'
                    ]
                ]);

            } else {
                // Microdeposit Verification: Use existing logic
                Log::info('Using microdeposit verification with PaymentIntents API');
                return $this->createACHTransferWithMicrodeposits($request, $fromAccount, $toAccount, $amountInCents, $stripe);
            }

            // Save transaction to database
            $transaction = \App\Models\Transaction::create([
                'from_account_id' => $fromAccount->user_id,
                'to_account_id' => $toAccount->user_id,
                'amount' => $request->amount,
                'description' => $request->description ?? 'ACH Transfer via Lending Platform',
                'stripe_payment_intent_id' => $paymentIntent->id,
                'status' => $this->mapStripeStatusToDatabase($paymentIntent->status),
                'network' => 'ach',
                'metadata' => [
                    'method_used' => 'payment_intents_api',
                    'verification_method' => $verificationMethod,
                    'stripe_payment_intent' => $paymentIntent->toArray(),
                    'from_account_details' => [
                        'plaid_account_id' => $fromAccount->plaid_account_id,
                        'account_name' => $fromAccount->account_name,
                        'institution_name' => $fromAccount->institution_name,
                        'user_id' => $fromAccount->user_id
                    ],
                    'to_account_details' => [
                        'plaid_account_id' => $toAccount->plaid_account_id,
                        'account_name' => $toAccount->account_name,
                        'institution_name' => $toAccount->institution_name,
                        'user_id' => $toAccount->user_id
                    ]
                ]
            ]);

            Log::info('ACH Transfer Created Successfully with PaymentIntents API', [
                'payment_intent_id' => $paymentIntent->id,
                'transaction_id' => $transaction->id,
                'verification_method' => $verificationMethod,
                'status' => $paymentIntent->status,
                'instant_verification' => $verificationMethod === 'instant'
            ]);

            return response()->json([
                'success' => true,
                'transfer' => [
                    'id' => $paymentIntent->id,
                    'status' => $paymentIntent->status,
                    'amount' => $amountInCents,
                    'type' => 'ach_debit',
                    'network' => 'ach',
                    'created' => now()->toISOString(),
                    'description' => $request->description ?? 'ACH Transfer via Lending Platform',
                    'next_action' => $paymentIntent->next_action,
                    'verification_method' => $verificationMethod
                ],
                'transaction_id' => $transaction->id,
                'message' => $verificationMethod === 'instant'
                    ? 'ACH transfer initiated with instant verification'
                    : 'ACH transfer initiated with PaymentIntents API',
                'method_used' => 'payment_intents_api',
                'verification_method' => $verificationMethod,
                'estimated_completion' => $verificationMethod === 'instant'
                    ? 'Instant verification - funds typically available within minutes'
                    : 'ACH transfers typically complete in 3-5 business days',
                'next_action' => $paymentIntent->next_action,
                'client_secret' => $paymentIntent->client_secret
            ]);

        } catch (\Exception $e) {
            Log::error('PaymentIntents ACH Transfer Creation Exception', [
                'message' => $e->getMessage(),
                'verification_method' => $verificationMethod,
                'trace' => $e->getTraceAsString()
            ]);

            // Check if this is a microdeposit blocking error
            if (strpos($e->getMessage(), 'Microdeposit transfers have been blocked') !== false) {
                return response()->json([
                    'success' => false,
                    'error' => 'Transfer method not supported',
                    'message' => 'ACH transfers using microdeposit verification are not available for this account. This is a common restriction in Stripe production accounts for security and compliance reasons.',
                    'details' => [
                        'issue' => 'Microdeposit verification blocked by Stripe',
                        'recommendation' => 'Use instant verification by linking a bank account from a major US financial institution',
                        'supported_banks' => 'Most major US banks support instant verification including Chase, Bank of America, Wells Fargo, Citi, and others',
                        'support_url' => 'https://support.stripe.com/',
                        'alternatives' => [
                            'Link an account from a different bank that supports instant verification',
                            'Contact Stripe support to request microdeposit verification access',
                            'Use Plaid Link to connect accounts from supported institutions'
                        ]
                    ]
                ], 400);
            }

            return response()->json([
                'error' => 'Internal server error',
                'message' => 'Failed to create ACH transfer with PaymentIntents API: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create ACH Transfer using Financial Connections for instant verification
     */
    private function createACHTransferWithFinancialConnectionsDirect(Request $request, PlaidAccount $fromAccount, PlaidAccount $toAccount, int $amountInCents): JsonResponse
    {
        Log::info('Creating ACH Transfer with Plaid-Powered Financial Connections', [
            'amount' => $amountInCents,
            'from_account_id' => $fromAccount->id,
            'to_account_id' => $toAccount->id
        ]);

        try {
            // Initialize Stripe client
            $useProduction = config('app.use_production_apis', false);
            $stripeSecret = $useProduction
                ? (config('services.stripe.prod_secret') ?? env('STRIPE_PROD_SECRET_KEY'))
                : (config('services.stripe.secret') ?? env('STRIPE_SECRET_KEY'));
            $stripe = new \Stripe\StripeClient($stripeSecret);

            // First, try to get Plaid bank account token (like the original method)
            $bankAccountToken = $this->createStripeBankAccountToken($fromAccount);

            // dd($bankAccountToken);

            if ($bankAccountToken && str_starts_with($bankAccountToken, 'btok_')) {
                // We have a Plaid processor token, but bank_account_token parameter isn't supported
                // in current Stripe API for PaymentMethod creation. Log this and fall back.
                Log::info('Plaid processor token available but not compatible with current Stripe API', [
                    'token_prefix' => substr($bankAccountToken, 0, 10) . '***'
                ]);
                Log::warning('Falling back to bank details approach due to Stripe API compatibility');
            } else if ($bankAccountToken === 'pm_usBankAccount_success') {
                // Test mode - this token should work directly, but let's be safe and fall back too
                Log::info('Test token available but falling back to consistent bank details approach');
            }

            // Always use bank details + Financial Connections approach for now
            // This provides the best user experience with current API compatibility
            return $this->createACHTransferWithBankDetailsUsingChargesAPI(
                $request, $fromAccount, $toAccount, $amountInCents, $stripe
            );

        } catch (\Exception $e) {
            Log::error('Financial Connections Direct ACH Transfer failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // If Financial Connections fails, fallback to traditional Plaid processor method
            if (strpos($e->getMessage(), 'Microdeposit transfers have been blocked') !== false) {
                return response()->json([
                    'success' => false,
                    'error' => 'Verification method not available',
                    'message' => 'Both Financial Connections and microdeposit verification are not available for this account configuration. Please contact support.',
                    'details' => [
                        'primary_method' => 'Financial Connections failed',
                        'fallback_method' => 'Microdeposit verification blocked',
                        'recommendation' => 'Contact Stripe support or try a different bank account',
                        'support_url' => 'https://support.stripe.com/'
                    ]
                ], 400);
            }

            return response()->json([
                'error' => 'Internal server error',
                'message' => 'Failed to create ACH transfer with Financial Connections: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create ACH Transfer using Plaid bank account token with Financial Connections verification
     */
    private function createACHTransferWithPlaidTokenAndFinancialConnections(Request $request, PlaidAccount $fromAccount, PlaidAccount $toAccount, int $amountInCents, string $bankAccountToken, \Stripe\StripeClient $stripe): JsonResponse
    {
        Log::info('Using Plaid bank account token with Financial Connections verification');

        // Get user details for billing
        $fromUser = $fromAccount->user;
        $billingName = null;
        if ($fromUser) {
            if (!empty($fromUser->name)) {
                $billingName = $fromUser->name;
            } elseif (!empty($fromUser->first_name) && !empty($fromUser->last_name)) {
                $billingName = $fromUser->first_name . ' ' . $fromUser->last_name;
            } elseif (!empty($fromUser->first_name)) {
                $billingName = $fromUser->first_name;
            } elseif (!empty($fromUser->business_name)) {
                $billingName = $fromUser->business_name;
            }
        }
        if (empty($billingName)) {
            $billingName = 'Account Holder';
        }
        $billingEmail = $fromUser && !empty($fromUser->email) ? $fromUser->email : $request->email;

        // Create Stripe Customer
        $customer = $stripe->customers->create([
            'email' => $billingEmail,
            'name' => $billingName,
            'metadata' => [
                'plaid_account_id' => $fromAccount->id,
                'platform' => 'lending_platform',
                'user_id' => $fromAccount->user_id,
                'integration_type' => 'plaid_financial_connections'
            ]
        ]);

        Log::info('Plaid token approach - but bank_account_token parameter not supported in this Stripe version');

        // The bank_account_token parameter for PaymentMethod creation might not be supported
        // in this Stripe API version. Fall back to bank details approach for now.
        Log::warning('Falling back to bank details approach due to Stripe API compatibility issue');

        throw new \Exception('bank_account_token not supported - falling back to bank details method');

        Log::info('Created PaymentMethod from Plaid token', [
            'payment_method_id' => $paymentMethod->id,
            'customer_id' => $customer->id
        ]);

        // Don't attach PaymentMethod to Customer yet - US bank accounts need verification first
        // The PaymentIntent will handle the verification and attachment automatically

        // Create and confirm PaymentIntent immediately (no user interaction needed)
        $paymentIntent = $stripe->paymentIntents->create([
            'amount' => $amountInCents,
            'currency' => 'usd',
            'customer' => $customer->id,
            'payment_method' => $paymentMethod->id,
            'payment_method_types' => ['us_bank_account'],
            'payment_method_options' => [
                'us_bank_account' => [
                    'verification_method' => 'instant'
                ]
            ],
            'description' => $request->description ?? 'ACH Transfer via Plaid + Financial Connections',
            'receipt_email' => $billingEmail,
            'metadata' => [
                'from_account_id' => $fromAccount->user_id,
                'to_account_id' => $toAccount->user_id,
                'plaid_from_account_id' => $fromAccount->id,
                'plaid_to_account_id' => $toAccount->id,
                'platform' => 'lending_platform',
                'transfer_type' => 'ach_debit_plaid_financial_connections',
                'verification_method' => 'instant_plaid_verified'
            ],
            'confirm' => true, // Auto-confirm since we have verified Plaid account data
        ]);

        Log::info('Created and confirmed PaymentIntent with Plaid token', [
            'payment_intent_id' => $paymentIntent->id,
            'status' => $paymentIntent->status
        ]);

        // Store the transaction record
        $transaction = Transaction::create([
            'from_account_id' => $fromAccount->user_id,
            'to_account_id' => $toAccount->user_id,
            'stripe_payment_intent_id' => $paymentIntent->id,
            'amount' => number_format($amountInCents / 100, 2, '.', ''),
            'currency' => 'usd',
            'status' => $this->mapStripeStatusToDatabase($paymentIntent->status),
            'description' => $request->description ?? 'ACH Transfer via Plaid + Financial Connections',
            'transaction_type' => 'transfer',
            'metadata' => [
                'method_used' => 'plaid_financial_connections',
                'verification_method' => 'instant_plaid_verified',
                'stripe_payment_intent' => $paymentIntent->toArray(),
                'customer_id' => $customer->id,
                'payment_method_id' => $paymentMethod->id,
                'transfer_network' => 'ach',
                'from_account_details' => [
                    'plaid_account_id' => $fromAccount->plaid_account_id,
                    'account_name' => $fromAccount->account_name,
                    'institution_name' => $fromAccount->institution_name,
                    'user_id' => $fromAccount->user_id
                ],
                'to_account_details' => [
                    'plaid_account_id' => $toAccount->plaid_account_id,
                    'account_name' => $toAccount->account_name,
                    'institution_name' => $toAccount->institution_name,
                    'user_id' => $toAccount->user_id
                ]
            ]
        ]);

        return response()->json([
            'success' => true,
            'message' => 'ACH transfer completed with Plaid + Financial Connections verification',
            'transfer' => [
                'id' => $transaction->id,
                'payment_intent_id' => $paymentIntent->id,
                'amount' => $amountInCents / 100,
                'status' => $paymentIntent->status,
                'verification_method' => 'instant_plaid_verified',
                'customer_id' => $customer->id,
                'payment_method_id' => $paymentMethod->id
            ],
            'completion_details' => [
                'verification_used' => 'Plaid account verification + Stripe Financial Connections',
                'processing_time' => 'Instant - no user interaction required',
                'user_experience' => 'Seamless - used existing Plaid account connection',
                'next_steps' => 'Payment is processing automatically'
            ],
            'estimated_completion' => 'ACH transfers typically complete in 1-3 business days'
        ]);
    }

    /**
     * Create ACH Transfer using bank account details with Financial Connections
     */
    private function createACHTransferWithBankDetailsAndFinancialConnections(Request $request, PlaidAccount $fromAccount, PlaidAccount $toAccount, int $amountInCents, \Stripe\StripeClient $stripe): JsonResponse
    {
        Log::info('Creating ACH Transfer using bank details with Financial Connections fallback');

        // Get bank account details from Plaid
        $bankAccountDetails = $this->getBankAccountDetails($fromAccount);

        if (!$bankAccountDetails) {
            return response()->json([
                'error' => 'Unable to retrieve bank account information',
                'message' => 'Failed to get bank account details for transfer.'
            ], 400);
        }

        // Get user details for billing
        $fromUser = $fromAccount->user;
        $billingName = null;
        if ($fromUser) {
            if (!empty($fromUser->name)) {
                $billingName = $fromUser->name;
            } elseif (!empty($fromUser->first_name) && !empty($fromUser->last_name)) {
                $billingName = $fromUser->first_name . ' ' . $fromUser->last_name;
            } elseif (!empty($fromUser->first_name)) {
                $billingName = $fromUser->first_name;
            } elseif (!empty($fromUser->business_name)) {
                $billingName = $fromUser->business_name;
            }
        }
        if (empty($billingName)) {
            $billingName = 'Account Holder';
        }
        $billingEmail = $fromUser && !empty($fromUser->email) ? $fromUser->email : $request->email;

        // Create Stripe Customer
        $customer = $stripe->customers->create([
            'email' => $billingEmail,
            'name' => $billingName,
            'metadata' => [
                'plaid_account_id' => $fromAccount->id,
                'platform' => 'lending_platform',
                'user_id' => $fromAccount->user_id,
                'integration_type' => 'bank_details_financial_connections'
            ]
        ]);

        // Create PaymentMethod using bank account details
        $paymentMethod = $stripe->paymentMethods->create([
            'type' => 'us_bank_account',
            'us_bank_account' => [
                'routing_number' => $bankAccountDetails['routing_number'],
                'account_number' => $bankAccountDetails['account_number'],
                'account_holder_type' => 'individual',
                'account_type' => $bankAccountDetails['account_type'] ?? 'checking',
            ],
            'billing_details' => [
                'name' => $billingName,
                'email' => $billingEmail,
            ],
        ]);

        // Don't attach PaymentMethod to Customer yet - US bank accounts need verification first
        // The PaymentIntent will handle the verification and attachment automatically

        Log::info('Created PaymentMethod from bank details', [
            'payment_method_id' => $paymentMethod->id,
            'customer_id' => $customer->id
        ]);

        // Create PaymentIntent with Financial Connections verification
        $paymentIntent = $stripe->paymentIntents->create([
            'amount' => $amountInCents,
            'currency' => 'usd',
            'customer' => $customer->id,
            'payment_method' => $paymentMethod->id,
            'payment_method_types' => ['us_bank_account'],
            'payment_method_options' => [
                'us_bank_account' => [
                    'verification_method' => 'automatic', // Let Stripe choose best verification
                    'financial_connections' => [
                        'permissions' => ['payment_method']
                    ]
                ]
            ],
            'description' => $request->description ?? 'ACH Transfer via Bank Details + Financial Connections',
            'receipt_email' => $billingEmail,
            'metadata' => [
                'from_account_id' => $fromAccount->user_id,
                'to_account_id' => $toAccount->user_id,
                'plaid_from_account_id' => $fromAccount->id,
                'plaid_to_account_id' => $toAccount->id,
                'platform' => 'lending_platform',
                'transfer_type' => 'ach_debit_bank_details_financial_connections',
                'verification_method' => 'automatic_financial_connections'
            ],
            'confirm' => false, // Don't auto-confirm - mandate data will be added during confirmation
        ]);

        Log::info('Created PaymentIntent with bank details', [
            'payment_intent_id' => $paymentIntent->id,
            'status' => $paymentIntent->status
        ]);

        // Store the transaction record
        $transaction = Transaction::create([
            'from_account_id' => $fromAccount->user_id,
            'to_account_id' => $toAccount->user_id,
            'stripe_payment_intent_id' => $paymentIntent->id,
            'amount' => number_format($amountInCents / 100, 2, '.', ''),
            'currency' => 'usd',
            'status' => 'pending',
            'description' => $request->description ?? 'ACH Transfer via Bank Details + Financial Connections',
            'transaction_type' => 'transfer',
            'metadata' => [
                'method_used' => 'bank_details_financial_connections',
                'verification_method' => 'automatic_financial_connections',
                'stripe_payment_intent' => $paymentIntent->toArray(),
                'customer_id' => $customer->id,
                'payment_method_id' => $paymentMethod->id,
                'transfer_network' => 'ach',
                'from_account_details' => [
                    'plaid_account_id' => $fromAccount->plaid_account_id,
                    'account_name' => $fromAccount->account_name,
                    'institution_name' => $fromAccount->institution_name,
                    'user_id' => $fromAccount->user_id
                ],
                'to_account_details' => [
                    'plaid_account_id' => $toAccount->plaid_account_id,
                    'account_name' => $toAccount->account_name,
                    'institution_name' => $toAccount->institution_name,
                    'user_id' => $toAccount->user_id
                ]
            ]
        ]);

        // If PaymentIntent can be confirmed immediately, do it
        if ($paymentIntent->status === 'requires_confirmation') {
            try {
                Log::info('Attempting to auto-confirm PaymentIntent', [
                    'payment_intent_id' => $paymentIntent->id,
                    'current_status' => $paymentIntent->status
                ]);

                // Include mandate data for US bank account confirmation
                $confirmParams = [
                    'mandate_data' => [
                        'customer_acceptance' => [
                            'type' => 'online',
                            'online' => [
                                'ip_address' => $request->ip(),
                                'user_agent' => $request->header('User-Agent'),
                            ],
                            'accepted_at' => time(),
                        ]
                    ]
                ];

                $confirmedPaymentIntent = $stripe->paymentIntents->confirm($paymentIntent->id, $confirmParams);

                // Update transaction status
                $transaction->update([
                    'status' => $this->mapStripeStatusToDatabase($confirmedPaymentIntent->status)
                ]);

                Log::info('Successfully auto-confirmed PaymentIntent', [
                    'payment_intent_id' => $confirmedPaymentIntent->id,
                    'old_status' => $paymentIntent->status,
                    'new_status' => $confirmedPaymentIntent->status
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'ACH transfer completed automatically',
                    'transfer' => [
                        'id' => $transaction->id,
                        'payment_intent_id' => $confirmedPaymentIntent->id,
                        'amount' => $amountInCents / 100,
                        'status' => $confirmedPaymentIntent->status,
                        'verification_method' => 'automatic_confirmed',
                        'customer_id' => $customer->id,
                        'payment_method_id' => $paymentMethod->id
                    ],
                    'completion_details' => [
                        'verification_used' => 'Bank details + automatic verification',
                        'processing_time' => 'Immediate confirmation',
                        'user_experience' => 'No additional verification required'
                    ]
                ]);

            } catch (\Exception $confirmException) {
                Log::error('Auto-confirmation failed', [
                    'payment_intent_id' => $paymentIntent->id,
                    'error' => $confirmException->getMessage(),
                    'stripe_error_type' => get_class($confirmException)
                ]);

                // Update transaction with confirmation failure info
                $transaction->update([
                    'metadata' => array_merge($transaction->metadata, [
                        'confirmation_attempted' => true,
                        'confirmation_failed' => true,
                        'confirmation_error' => $confirmException->getMessage()
                    ])
                ]);

                // Still return success but indicate manual confirmation is needed
                return response()->json([
                    'success' => true,
                    'message' => 'ACH transfer created - manual confirmation may be required',
                    'transfer' => [
                        'id' => $transaction->id,
                        'payment_intent_id' => $paymentIntent->id,
                        'amount' => $amountInCents / 100,
                        'status' => $paymentIntent->status,
                        'verification_method' => 'automatic_financial_connections',
                        'customer_id' => $customer->id,
                        'payment_method_id' => $paymentMethod->id,
                        'client_secret' => $paymentIntent->client_secret,
                        'next_action' => $paymentIntent->next_action
                    ],
                    'requires_verification' => true,
                    'completion_details' => [
                        'verification_used' => 'Bank account details with Financial Connections fallback',
                        'processing_status' => 'Auto-confirmation failed - may need user interaction',
                        'error_details' => $confirmException->getMessage()
                    ]
                ]);
            }
        }

        // Return response indicating next action needed
        return response()->json([
            'success' => true,
            'message' => 'ACH transfer created - verification may be required',
            'transfer' => [
                'id' => $transaction->id,
                'payment_intent_id' => $paymentIntent->id,
                'amount' => $amountInCents / 100,
                'status' => $paymentIntent->status,
                'verification_method' => 'automatic_financial_connections',
                'customer_id' => $customer->id,
                'payment_method_id' => $paymentMethod->id,
                'client_secret' => $paymentIntent->client_secret,
                'next_action' => $paymentIntent->next_action
            ],
            'requires_verification' => $paymentIntent->status === 'requires_action' || $paymentIntent->status === 'requires_payment_method',
            'completion_details' => [
                'verification_used' => 'Bank account details with Financial Connections fallback',
                'processing_status' => $paymentIntent->status === 'requires_action'
                    ? 'Additional verification may be required'
                    : 'Processing automatically'
            ]
        ]);
    }

    /**
     * Create ACH Transfer using bank account details with the simpler Charges API
     */
    private function createACHTransferWithBankDetailsUsingChargesAPI(Request $request, PlaidAccount $fromAccount, PlaidAccount $toAccount, int $amountInCents, \Stripe\StripeClient $stripe): JsonResponse
    {
        Log::info('Creating ACH Transfer using bank details with Charges API');

        // Get bank account details from Plaid
        $bankAccountDetails = $this->getBankAccountDetails($fromAccount);

        if (!$bankAccountDetails) {
            return response()->json([
                'error' => 'Unable to retrieve bank account information',
                'message' => 'Failed to get bank account details for transfer.'
            ], 400);
        }

        // Get user details for billing
        $fromUser = $fromAccount->user;
        $billingName = null;
        if ($fromUser) {
            if (!empty($fromUser->name)) {
                $billingName = $fromUser->name;
            } elseif (!empty($fromUser->first_name) && !empty($fromUser->last_name)) {
                $billingName = $fromUser->first_name . ' ' . $fromUser->last_name;
            } elseif (!empty($fromUser->first_name)) {
                $billingName = $fromUser->first_name;
            } elseif (!empty($fromUser->business_name)) {
                $billingName = $fromUser->business_name;
            }
        }
        if (empty($billingName)) {
            $billingName = 'Account Holder';
        }
        $billingEmail = $fromUser && !empty($fromUser->email) ? $fromUser->email : $request->email;

        try {
            // Step 1: Create Stripe Customer (or reuse existing)
            $customer = null;
            $bankAccount = null;

            // Check if we have persistent Stripe objects stored
            if ($fromAccount->hasStripeCustomerAccount()) {
                Log::info('Found existing Stripe Customer, refreshing bank account with fresh Plaid token', [
                    'customer_id' => $fromAccount->stripe_customer_id,
                    'existing_bank_account_id' => $fromAccount->stripe_bank_account_id
                ]);

                // Regenerate fresh bank account with new Plaid token to ensure it's valid
                $refreshedAccount = $this->refreshStripeCustomerBankAccount($fromAccount);

                if ($refreshedAccount) {
                    $customer = $stripe->customers->retrieve($refreshedAccount['customer_id']);
                    $bankAccount = $stripe->customers->retrieveSource($refreshedAccount['customer_id'], $refreshedAccount['bank_account_id']);

                    Log::info('Successfully refreshed bank account with fresh token', [
                        'customer_id' => $customer->id,
                        'bank_account_id' => $bankAccount->id,
                        'bank_account_status' => $bankAccount->status
                    ]);
                } else {
                    Log::warning('Failed to refresh bank account, falling back to create new customer');
                    // Fall through to create new customer approach
                    $fromAccount = null; // Force new customer creation
                }
            }

            // Create new customer if no existing customer or refresh failed
            if (!$fromAccount || !$fromAccount->hasStripeCustomerAccount()) {
                Log::info('Creating new Stripe Customer + Bank Account from Plaid');

                $stripeIntegration = $this->createStripeCustomerWithBankAccount($fromAccount);

                if (!$stripeIntegration) {
                    throw new \Exception('Failed to create Stripe Customer + Bank Account from Plaid data');
                }

                $customer = $stripe->customers->retrieve($stripeIntegration['customer_id']);
                $bankAccount = $stripe->customers->retrieveSource($stripeIntegration['customer_id'], $stripeIntegration['bank_account_id']);

                Log::info('Created new Stripe objects', [
                    'customer_id' => $customer->id,
                    'bank_account_id' => $bankAccount->id,
                    'bank_account_status' => $bankAccount->status
                ]);
            }

            // Step 2: Check if bank account needs verification before attempting charge
            if ($bankAccount->status !== 'verified') {
                Log::warning('Bank account needs verification before ACH charge', [
                    'customer_id' => $customer->id,
                    'bank_account_id' => $bankAccount->id,
                    'current_status' => $bankAccount->status
                ]);

                // Initiate microdeposit verification for unverified accounts
                try {
                    // In Stripe, microdeposits are automatically sent when a bank account is created
                    // We don't need to explicitly initiate them - they're already pending
                    // Instead, we should return instructions for the user to wait for deposits

                    Log::info('Bank account created - microdeposits will be sent automatically', [
                        'customer_id' => $customer->id,
                        'bank_account_id' => $bankAccount->id,
                        'current_status' => $bankAccount->status
                    ]);

                    // Return verification pending response without trying to initiate
                    return response()->json([
                        'success' => false,
                        'requires_verification' => true,
                        'verification_method' => 'microdeposits',
                        'message' => 'Bank account created successfully. Microdeposits will be sent within 1-2 business days.',
                        'verification_details' => [
                            'customer_id' => $customer->id,
                            'bank_account_id' => $bankAccount->id,
                            'bank_name' => $bankAccount->bank_name ?? $fromAccount->institution_name,
                            'last4' => $bankAccount->last4 ?? 'unknown',
                            'verification_status' => $bankAccount->status,
                            'next_steps' => 'Wait for microdeposits (1-2 business days), then use the bank verification endpoint to complete the process.'
                        ]
                    ]);

                } catch (\Exception $verifyException) {
                    Log::error('Error handling unverified bank account', [
                        'error' => $verifyException->getMessage(),
                        'customer_id' => $customer->id,
                        'bank_account_id' => $bankAccount->id
                    ]);

                    // Even if verification handling fails, return helpful guidance
                    return response()->json([
                        'success' => false,
                        'requires_verification' => true,
                        'verification_method' => 'microdeposits',
                        'message' => 'Bank account created but verification setup encountered an issue. Microdeposits should still be sent automatically.',
                        'verification_details' => [
                            'customer_id' => $customer->id,
                            'bank_account_id' => $bankAccount->id,
                            'bank_name' => $bankAccount->bank_name ?? $fromAccount->institution_name,
                            'last4' => $bankAccount->last4 ?? 'unknown',
                            'verification_status' => $bankAccount->status,
                            'next_steps' => 'Wait 1-2 business days for microdeposits, then contact support if needed.'
                        ]
                    ], 400);
                }
            }

            // Step 3: Create the ACH charge using the verified bank account
            try {
                $charge = $stripe->charges->create([
                    'amount' => $amountInCents,
                    'currency' => 'usd',
                    'customer' => $customer->id,
                    'source' => $bankAccount->id, // Use the persistent bank account ID
                    'description' => $request->description ?? 'ACH Transfer via Plaid + Charges API (Persistent Account)',
                    'metadata' => [
                        'from_account_id' => $fromAccount->id,
                        'to_account_id' => $toAccount->id,
                        'plaid_from_account' => $fromAccount->plaid_account_id,
                        'plaid_to_account' => $toAccount->plaid_account_id,
                        'integration_method' => 'persistent_customer_account',
                        'transfer_network' => 'ach'
                    ]
                ]);

                Log::info('Successfully created charge using persistent Stripe objects', [
                    'charge_id' => $charge->id,
                    'customer_id' => $customer->id,
                    'bank_account_id' => $bankAccount->id,
                    'status' => $charge->status,
                    'amount' => $charge->amount / 100
                ]);
                Log::info('Attempting to use Plaid processor token with Charges API', [
                    'token_prefix' => substr($bankAccountToken, 0, 10) . '***'
                ]);

                try {
                    // Create bank account source using Plaid processor token
                    $bankAccount = $stripe->customers->createSource($customer->id, [
                        'source' => $bankAccountToken
                    ]);

                    Log::info('Successfully created bank account source from Plaid processor token', [
                        'bank_account_id' => $bankAccount->id,
                        'customer_id' => $customer->id
                    ]);

                } catch (\Exception $tokenException) {
                    Log::warning('Plaid processor token failed in Stripe, falling back to manual bank account creation', [
                        'token_error' => $tokenException->getMessage(),
                        'token_prefix' => substr($bankAccountToken, 0, 10) . '***'
                    ]);

                    // Fall back to manual creation
                    $bankAccount = null;
                }
            } catch (\Stripe\Exception\InvalidRequestException $chargeException) {
                Log::warning('Charge creation with persistent bank account failed, falling back to manual creation', [
                    'error' => $chargeException->getMessage(),
                    'customer_id' => $customer->id,
                    'bank_account_id' => $bankAccount->id
                ]);
                $bankAccount = null; // Force manual creation
            }

            // If Plaid token approach failed, create bank account source manually
            if (!$bankAccount) {
                Log::info('Creating bank account source manually using Plaid bank details');

                $bankAccount = $stripe->customers->createSource($customer->id, [
                    'source' => [
                        'object' => 'bank_account',
                        'country' => 'US',
                        'currency' => 'usd',
                        'account_holder_name' => $billingName,
                        'account_holder_type' => 'individual',
                        'routing_number' => $bankAccountDetails['routing_number'],
                        'account_number' => $bankAccountDetails['account_number'],
                    ]
                ]);

                Log::info('Created bank account source manually', [
                    'bank_account_id' => $bankAccount->id,
                    'customer_id' => $customer->id
                ]);

                // For manually created bank accounts, we need to handle verification
                // In test mode, try to proceed without explicit verification
                if (!config('app.use_production_apis', false)) {
                    Log::info('Test mode: attempting to use unverified bank account for testing');

                    // In Stripe test mode, some bank accounts can be used without verification
                    // Let's try the charge and see if it works
                } else {
                    Log::warning('Production mode: bank account may need verification before use');

                    // In production, we might need microdeposit verification
                    // For now, let's try the charge and handle the error if it occurs
                }
            }

            // Step 3: Create the ACH charge
            try {
                $charge = $stripe->charges->create([
                    'amount' => $amountInCents,
                    'currency' => 'usd',
                    'customer' => $customer->id,
                    'source' => $bankAccount->id, // Use the bank account source
                    'description' => $request->description ?? 'ACH Transfer via Plaid + Charges API',
                    'receipt_email' => $billingEmail,
                    'metadata' => [
                        'from_account_id' => $fromAccount->user_id,
                        'to_account_id' => $toAccount->user_id,
                        'plaid_from_account_id' => $fromAccount->id,
                        'plaid_to_account_id' => $toAccount->id,
                        'platform' => 'lending_platform',
                        'transfer_type' => 'ach_debit_charges_api',
                        'verification_method' => 'charges_api'
                    ]
                ]);

                Log::info('ACH charge created successfully', [
                    'charge_id' => $charge->id,
                    'status' => $charge->status,
                    'amount' => $charge->amount / 100
                ]);

            } catch (\Stripe\Exception\InvalidRequestException $chargeException) {
                // Handle verification-related errors specifically
                if (strpos($chargeException->getMessage(), 'must be verified') !== false) {
                    Log::info('Bank account verification required - initiating microdeposit verification', [
                        'bank_account_id' => $bankAccount->id,
                        'error' => $chargeException->getMessage()
                    ]);

                    // Automatically initiate microdeposit verification
                    try {
                        $verification = $stripe->customers->verifySource($customer->id, $bankAccount->id, []);

                        Log::info('Initiated microdeposit verification', [
                            'bank_account_id' => $bankAccount->id,
                            'customer_id' => $customer->id
                        ]);

                        // Store the pending transaction for later completion
                        $transaction = Transaction::create([
                            'from_account_id' => $fromAccount->user_id,
                            'to_account_id' => $toAccount->user_id,
                            'stripe_payment_intent_id' => null, // No charge yet
                            'amount' => number_format($amountInCents / 100, 2, '.', ''),
                            'currency' => 'usd',
                            'status' => 'pending_verification',
                            'description' => $request->description ?? 'ACH Transfer via Plaid + Charges API (Pending Verification)',
                            'transaction_type' => 'transfer',
                            'metadata' => [
                                'method_used' => 'charges_api_with_verification',
                                'verification_method' => 'microdeposits',
                                'verification_status' => 'pending',
                                'customer_id' => $customer->id,
                                'bank_account_id' => $bankAccount->id,
                                'transfer_network' => 'ach',
                                'original_request' => [
                                    'amount_in_cents' => $amountInCents,
                                    'description' => $request->description ?? 'ACH Transfer via Plaid + Charges API'
                                ],
                                'from_account_details' => [
                                    'plaid_account_id' => $fromAccount->plaid_account_id,
                                    'account_name' => $fromAccount->account_name,
                                    'institution_name' => $fromAccount->institution_name,
                                    'user_id' => $fromAccount->user_id
                                ],
                                'to_account_details' => [
                                    'plaid_account_id' => $toAccount->plaid_account_id,
                                    'account_name' => $toAccount->account_name,
                                    'institution_name' => $toAccount->institution_name,
                                    'user_id' => $toAccount->user_id
                                ]
                            ]
                        ]);

                        return response()->json([
                            'success' => true, // Changed to true since verification was initiated successfully
                            'requires_verification' => true,
                            'verification_type' => 'microdeposits',
                            'message' => 'Bank account verification required - microdeposits have been sent to your account',
                            'verification_details' => [
                                'status' => 'Microdeposits sent to your bank account',
                                'timeline' => 'Deposits will appear in 1-2 business days',
                                'next_step' => 'Check your bank statement and enter the deposit amounts',
                                'bank_account_id' => $bankAccount->id,
                                'customer_id' => $customer->id,
                                'transaction_id' => $transaction->id
                            ],
                            'verification_ui' => [
                                'verification_url' => '/bank-verification?customer_id=' . $customer->id . '&bank_account_id=' . $bankAccount->id . '&transaction_id=' . $transaction->id,
                                'api_endpoint' => '/plaid/complete-bank-verification',
                                'required_fields' => ['amount1', 'amount2']
                            ],
                            'instructions' => [
                                '1. Check your bank account in 1-2 business days',
                                '2. Look for two small deposits from STRIPE',
                                '3. Enter the exact amounts (in cents) to complete verification',
                                '4. Your transfer will be processed automatically after verification'
                            ]
                        ], 202); // 202 Accepted - processing will continue after verification

                    } catch (\Exception $verificationException) {
                        Log::error('Failed to initiate microdeposit verification', [
                            'bank_account_id' => $bankAccount->id,
                            'error' => $verificationException->getMessage()
                        ]);

                        return response()->json([
                            'success' => false,
                            'error' => 'Verification initiation failed',
                            'message' => 'Unable to start bank account verification: ' . $verificationException->getMessage(),
                            'details' => [
                                'issue' => 'Could not initiate microdeposit verification',
                                'bank_account_id' => $bankAccount->id,
                                'customer_id' => $customer->id
                            ],
                            'alternatives' => [
                                'option_1' => 'Try using Stripe test bank account numbers',
                                'option_2' => 'Use Stripe Financial Connections for instant verification',
                                'option_3' => 'Contact support for assistance'
                            ]
                        ], 400);
                    }
                } else {
                    // Other InvalidRequestException errors
                    Log::error('Stripe InvalidRequestException', [
                        'error' => $chargeException->getMessage(),
                        'bank_account_id' => $bankAccount->id ?? 'unknown'
                    ]);

                    throw $chargeException; // Re-throw to be caught by outer catch
                }

            } catch (\Stripe\Exception\CardException $cardException) {
                Log::error('Stripe CardException', [
                    'error' => $cardException->getMessage(),
                    'decline_code' => $cardException->getDeclineCode()
                ]);

                throw $cardException; // Re-throw to be caught by outer catch
            }

            // Store the transaction record
            $transaction = Transaction::create([
                'from_account_id' => $fromAccount->user_id,
                'to_account_id' => $toAccount->user_id,
                'stripe_payment_intent_id' => $charge->id, // Store charge ID instead of payment_intent_id
                'amount' => number_format($amountInCents / 100, 2, '.', ''),
                'currency' => 'usd',
                'status' => $this->mapStripeStatusToDatabase($charge->status),
                'description' => $request->description ?? 'ACH Transfer via Plaid + Charges API',
                'transaction_type' => 'transfer',
                'metadata' => [
                    'method_used' => 'charges_api_with_plaid_data',
                    'verification_method' => 'charges_api',
                    'stripe_charge' => $charge->toArray(),
                    'customer_id' => $customer->id,
                    'bank_account_id' => $bankAccount->id,
                    'transfer_network' => 'ach',
                    'from_account_details' => [
                        'plaid_account_id' => $fromAccount->plaid_account_id,
                        'account_name' => $fromAccount->account_name,
                        'institution_name' => $fromAccount->institution_name,
                        'user_id' => $fromAccount->user_id
                    ],
                    'to_account_details' => [
                        'plaid_account_id' => $toAccount->plaid_account_id,
                        'account_name' => $toAccount->account_name,
                        'institution_name' => $toAccount->institution_name,
                        'user_id' => $toAccount->user_id
                    ]
                ]
            ]);

            return response()->json([
                'success' => true,
                'message' => 'ACH transfer completed using Charges API',
                'transfer' => [
                    'id' => $transaction->id,
                    'charge_id' => $charge->id,
                    'amount' => $amountInCents / 100,
                    'status' => $charge->status,
                    'verification_method' => 'charges_api',
                    'customer_id' => $customer->id,
                    'bank_account_id' => $bankAccount->id
                ],
                'completion_details' => [
                    'api_used' => 'Stripe Charges API (simpler approach)',
                    'verification_used' => 'Bank account details from Plaid',
                    'processing_time' => 'Immediate charge creation',
                    'user_experience' => 'No additional verification steps required',
                    'compliance' => 'ACH authorization handled by Charges API'
                ],
                'estimated_completion' => 'ACH transfers typically complete in 1-3 business days'
            ]);

        } catch (\Exception $e) {
            Log::error('Charges API ACH Transfer failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'ACH transfer failed',
                'message' => 'Failed to create ACH transfer using Charges API: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Create ACH transfer with microdeposit verification using PaymentIntents API
     */
    private function createACHTransferWithMicrodeposits(Request $request, PlaidAccount $fromAccount, PlaidAccount $toAccount, int $amountInCents, \Stripe\StripeClient $stripe): JsonResponse
    {
        Log::info('Creating ACH Transfer with microdeposit verification');

        // This method uses the original logic for microdeposit verification
        $bankAccountDetails = $this->getBankAccountDetails($fromAccount);

        if (!$bankAccountDetails) {
            return response()->json([
                'error' => 'Unable to retrieve bank account information',
                'message' => 'Failed to get bank account details for microdeposit verification.'
            ], 400);
        }

        // Get user details with proper fallbacks
        $fromUser = $fromAccount->user;

        // Build billing name from available user data
        $billingName = null;
        if ($fromUser) {
            // Try different combinations for the name
            if (!empty($fromUser->name)) {
                $billingName = $fromUser->name;
            } elseif (!empty($fromUser->first_name) && !empty($fromUser->last_name)) {
                $billingName = $fromUser->first_name . ' ' . $fromUser->last_name;
            } elseif (!empty($fromUser->first_name)) {
                $billingName = $fromUser->first_name;
            } elseif (!empty($fromUser->business_name)) {
                $billingName = $fromUser->business_name;
            }
        }

        // Final fallback if no name found
        if (empty($billingName)) {
            $billingName = 'Account Holder';
        }

        $billingEmail = $fromUser && !empty($fromUser->email) ? $fromUser->email : $request->email;

        Log::info('Billing details for microdeposit payment method', [
            'billing_name' => $billingName,
            'billing_email' => $billingEmail,
            'user_id' => $fromUser ? $fromUser->id : null
        ]);

        // Create PaymentMethod with bank account details
        try {
            Log::info('Creating Stripe PaymentMethod for microdeposit verification', [
                'billing_name' => $billingName,
                'billing_email' => $billingEmail,
                'routing_number' => substr($bankAccountDetails['routing_number'], 0, 4) . '****',
                'account_type' => $bankAccountDetails['account_type'] ?? 'checking'
            ]);

            $paymentMethod = $stripe->paymentMethods->create([
                'type' => 'us_bank_account',
                'us_bank_account' => [
                    'routing_number' => $bankAccountDetails['routing_number'],
                    'account_number' => $bankAccountDetails['account_number'],
                    'account_holder_type' => 'individual',
                    'account_type' => $bankAccountDetails['account_type'] ?? 'checking',
                ],
                'billing_details' => [
                    'name' => $billingName,
                    'email' => $billingEmail,
                ],
            ]);

            Log::info('Successfully created PaymentMethod for microdeposit verification', [
                'payment_method_id' => $paymentMethod->id
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create Stripe PaymentMethod', [
                'error' => $e->getMessage(),
                'billing_name' => $billingName,
                'billing_email' => $billingEmail
            ]);

            return response()->json([
                'error' => 'Payment method creation failed',
                'message' => 'Failed to create payment method: ' . $e->getMessage()
            ], 500);
        }

        // Create PaymentIntent with microdeposit verification
        try {
            // Try to use Financial Connections even for microdeposit fallback
            $paymentIntentParams = [
                'amount' => $amountInCents,
                'currency' => 'usd',
                'payment_method' => $paymentMethod->id,
                'payment_method_types' => ['us_bank_account'],
                'description' => $request->description ?? 'ACH Transfer via Lending Platform (Microdeposit Verification)',
                'receipt_email' => $request->email,
                'metadata' => [
                    'from_account_id' => $fromAccount->user_id,
                    'to_account_id' => $toAccount->user_id,
                    'plaid_from_account_id' => $fromAccount->id,
                    'plaid_to_account_id' => $toAccount->id,
                    'platform' => 'lending_platform',
                    'transfer_type' => 'ach_debit_microdeposit',
                    'verification_method' => 'microdeposit'
                ],
                'mandate_data' => [
                    'customer_acceptance' => [
                        'type' => 'online',
                        'online' => [
                            'ip_address' => $request->ip(),
                            'user_agent' => $request->header('User-Agent')
                        ]
                    ]
                ],
                'confirm' => true,
                'return_url' => config('app.url') . '/transfer/return',
            ];

            // Add Financial Connections as fallback option
            if (config('services.stripe.financial_connections.enabled', true)) {
                $paymentIntentParams['payment_method_options'] = [
                    'us_bank_account' => [
                        'verification_method' => 'automatic', // Let Stripe decide best method
                        'financial_connections' => [
                            'permissions' => ['payment_method']
                        ]
                    ]
                ];
            }

            $paymentIntent = $stripe->paymentIntents->create($paymentIntentParams);

        } catch (\Exception $e) {
            Log::error('Failed to create PaymentIntent with microdeposit verification', [
                'error' => $e->getMessage(),
                'payment_method_id' => $paymentMethod->id
            ]);

            // Check if microdeposits are blocked by Stripe
            if (strpos($e->getMessage(), 'Microdeposit transfers have been blocked') !== false) {
                return response()->json([
                    'success' => false,
                    'error' => 'Microdeposit verification disabled',
                    'message' => 'Microdeposit verification has been disabled for this Stripe account. This usually happens in production accounts for security and compliance reasons. Please use instant verification by linking a bank account that supports it, or contact Stripe support to enable microdeposit verification.',
                    'details' => [
                        'reason' => 'Stripe has blocked microdeposit transfers for this account',
                        'solution' => 'Use banks that support instant verification (most major US banks)',
                        'support_url' => 'https://support.stripe.com/',
                        'verification_alternatives' => [
                            'Use a different bank account that supports instant verification',
                            'Contact Stripe support to request microdeposit access',
                            'Consider using Plaid Link with a supported financial institution'
                        ]
                    ]
                ], 400);
            }

            return response()->json([
                'error' => 'Payment intent creation failed',
                'message' => 'Failed to create PaymentIntent for microdeposit verification: ' . $e->getMessage()
            ], 500);
        }

        // Save transaction record
        $transaction = \App\Models\Transaction::create([
            'from_account_id' => $fromAccount->user_id,
            'to_account_id' => $toAccount->user_id,
            'amount' => $request->amount,
            'description' => $request->description ?? 'ACH Transfer via Lending Platform',
            'stripe_payment_intent_id' => $paymentIntent->id,
            'status' => $this->mapStripeStatusToDatabase($paymentIntent->status),
            'network' => 'ach',
            'metadata' => [
                'method_used' => 'payment_intents_microdeposit',
                'stripe_payment_intent' => $paymentIntent->toArray(),
                'payment_method_id' => $paymentMethod->id,
                'verification_method' => 'microdeposit',
                'from_account_details' => [
                    'plaid_account_id' => $fromAccount->plaid_account_id,
                    'account_name' => $fromAccount->account_name,
                    'institution_name' => $fromAccount->institution_name,
                    'user_id' => $fromAccount->user_id
                ],
                'to_account_details' => [
                    'plaid_account_id' => $toAccount->plaid_account_id,
                    'account_name' => $toAccount->account_name,
                    'institution_name' => $toAccount->institution_name,
                    'user_id' => $toAccount->user_id
                ]
            ]
        ]);

        return response()->json([
            'success' => true,
            'transfer' => [
                'id' => $paymentIntent->id,
                'status' => $paymentIntent->status,
                'amount' => $amountInCents,
                'type' => 'ach_debit',
                'network' => 'ach',
                'created' => now()->toISOString(),
                'description' => $request->description ?? 'ACH Transfer via Lending Platform',
                'next_action' => $paymentIntent->next_action,
                'verification_method' => 'microdeposit'
            ],
            'transaction_id' => $transaction->id,
            'message' => 'ACH transfer initiated with microdeposit verification',
            'method_used' => 'payment_intents_microdeposit',
            'verification_method' => 'microdeposit',
            'estimated_completion' => 'ACH transfers typically complete in 3-5 business days',
            'next_action' => $paymentIntent->next_action,
            'client_secret' => $paymentIntent->client_secret
        ]);
    }

    /**
     * Check verification options for a bank account
     * This helps users understand why certain verification methods might not be available
     */
    public function checkVerificationOptions(Request $request)
    {
        try {
            $validated = $request->validate([
                'from_account_id' => 'required|integer|exists:plaid_accounts,id'
            ]);

            $fromAccount = PlaidAccount::find($validated['from_account_id']);
            if (!$fromAccount) {
                return response()->json(['error' => 'From account not found'], 404);
            }

            // Check if instant verification might be available
            $bankAccountToken = $this->createStripeBankAccountToken($fromAccount);
            $canInstantVerify = !empty($bankAccountToken) && strpos($bankAccountToken, 'btok_') === 0;

            // Get institution information
            $institutionInfo = [
                'name' => $fromAccount->institution_name,
                'id' => $fromAccount->institution_id ?? 'unknown'
            ];

            // Check if microdeposits are available (this is environment dependent)
            $useProduction = config('app.use_production_apis', false);
            $microdepositsNote = $useProduction
                ? 'Microdeposit verification may be restricted in production accounts. Contact Stripe support if needed.'
                : 'Microdeposit verification available in sandbox mode.';

            return response()->json([
                'success' => true,
                'account_info' => [
                    'account_name' => $fromAccount->account_name,
                    'institution' => $institutionInfo,
                    'account_type' => $fromAccount->account_type ?? 'checking'
                ],
                'verification_options' => [
                    'instant_verification' => [
                        'available' => $canInstantVerify,
                        'description' => $canInstantVerify
                            ? 'This account supports instant verification through Plaid Processor API'
                            : 'Instant verification not available for this account',
                        'speed' => 'Funds typically available within minutes'
                    ],
                    'microdeposit_verification' => [
                        'available' => true,
                        'description' => 'Traditional verification using small test deposits',
                        'speed' => '1-2 business days for verification, then 3-5 days for transfer completion',
                        'note' => $microdepositsNote
                    ]
                ],
                'recommendation' => $canInstantVerify
                    ? 'Use instant verification for fastest processing'
                    : 'Microdeposit verification required for this account',
                'environment' => $useProduction ? 'production' : 'sandbox'
            ]);

        } catch (\Exception $e) {
            Log::error('Error checking verification options', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Failed to check verification options',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create ACH Transfer using Charges API (legacy, with microdeposit verification)
     */
    private function createACHTransferWithCharges(Request $request, PlaidAccount $fromAccount, PlaidAccount $toAccount, int $amountInCents, string $verificationMethod): JsonResponse
    {
        Log::info('Creating ACH Transfer with Charges API (Legacy)', [
            'verification_method' => $verificationMethod,
            'amount' => $amountInCents
        ]);

        try {
            // Initialize Stripe client
            $useProduction = config('app.use_production_apis', false);
            $stripeSecret = $useProduction
                ? (config('services.stripe.prod_secret') ?? env('STRIPE_PROD_SECRET_KEY'))
                : (config('services.stripe.secret') ?? env('STRIPE_SECRET_KEY'));

            $stripe = new \Stripe\StripeClient($stripeSecret);

            // Get bank account details (required for Charges API)
            $bankAccountDetails = $this->getBankAccountDetails($fromAccount);

            if (!$bankAccountDetails) {
                Log::error('Failed to get bank account details for Charges API');
                return response()->json([
                    'error' => 'Unable to retrieve bank account information',
                    'message' => 'Bank account details are required for legacy ACH processing.'
                ], 400);
            }

            // Get user details for billing information
            $fromUser = $fromAccount->user ?? \App\Models\LendingUser::find($fromAccount->user_id);
            $billingName = $fromUser ? $fromUser->business_name : 'Account Holder';
            $billingEmail = $fromUser ? $fromUser->email : null;

            // Create Source for ACH debit (legacy method)
            $source = $stripe->sources->create([
                'type' => 'ach_debit',
                'currency' => 'usd',
                'owner' => [
                    'name' => $billingName,
                    'email' => $billingEmail ?? $request->email,
                ],
                'ach_debit' => [
                    'routing_number' => $bankAccountDetails['routing_number'],
                    'account_number' => $bankAccountDetails['account_number'],
                    'account_holder_type' => 'individual',
                    'bank_name' => $fromAccount->institution_name,
                ]
            ]);

            Log::info('ACH Source created successfully', [
                'source_id' => $source->id,
                'status' => $source->status
            ]);

            // Create charge using the ACH source
            $charge = $stripe->charges->create([
                'amount' => $amountInCents,
                'currency' => 'usd',
                'source' => $source->id,
                'description' => $request->description ?? 'ACH Transfer via Lending Platform (Legacy Method)',
                'metadata' => [
                    'from_account_id' => $fromAccount->id,
                    'to_account_id' => $toAccount->id,
                    'verification_method' => $verificationMethod,
                    'integration_method' => 'charges_api_legacy'
                ]
            ]);

            // Create transaction record
            $transaction = \App\Models\Transaction::create([
                'from_account_id' => $fromAccount->id,
                'to_account_id' => $toAccount->id,
                'amount' => $request->amount,
                'type' => 'ach_debit',
                'status' => $this->mapStripeStatusToTransactionStatus($charge->status),
                'description' => $request->description ?? 'ACH Transfer via Lending Platform (Legacy)',
                'stripe_charge_id' => $charge->id,
                'stripe_source_id' => $source->id,
                'metadata' => [
                    'integration_method' => 'charges_api_legacy',
                    'verification_method' => $verificationMethod,
                    'stripe_status' => $charge->status,
                    'source_status' => $source->status,
                    'bank_details' => [
                        'routing_number' => substr($bankAccountDetails['routing_number'], 0, 4) . '****',
                        'account_number' => '****' . substr($bankAccountDetails['account_number'], -4)
                    ]
                ]
            ]);

            Log::info('Legacy ACH Transfer Created Successfully', [
                'method' => 'Charges API (Legacy)',
                'charge_id' => $charge->id,
                'source_id' => $source->id,
                'transaction_id' => $transaction->id,
                'amount' => $request->amount,
                'charge_status' => $charge->status,
                'source_status' => $source->status,
                'verification_method' => $verificationMethod
            ]);

            return response()->json([
                'success' => true,
                'transfer' => [
                    'id' => $charge->id,
                    'status' => $charge->status,
                    'amount' => $amountInCents,
                    'type' => 'ach_debit',
                    'network' => 'ach',
                    'created' => now()->toISOString(),
                    'description' => $charge->description,
                    'source_id' => $source->id,
                    'source_status' => $source->status
                ],
                'transaction_id' => $transaction->id,
                'message' => 'Legacy ACH transfer initiated via Charges API - verification required',
                'method_used' => 'charges_api_legacy',
                'verification_method' => $verificationMethod,
                'verification_info' => $verificationMethod === 'microdeposit'
                    ? 'Microdeposits will be sent to the bank account within 1-2 business days for verification'
                    : 'Account verification completed via Plaid',
                'estimated_completion' => 'ACH transfers typically complete in 3-5 business days after verification',
                'next_steps' => $verificationMethod === 'microdeposit'
                    ? 'Watch for small deposits in your bank account and verify them when received'
                    : 'Transfer is processing and will complete in 3-5 business days'
            ]);

        } catch (\Stripe\Exception\CardException $e) {
            Log::error('Stripe ACH Transfer Failed (Charges API)', [
                'error' => $e->getMessage(),
                'decline_code' => $e->getDeclineCode(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'error' => 'ACH transfer declined',
                'message' => $e->getMessage(),
                'decline_code' => $e->getDeclineCode(),
                'method' => 'charges_api_legacy'
            ], 400);

        } catch (\Stripe\Exception\ApiErrorException $e) {
            Log::error('Stripe API Error (Charges API)', [
                'error' => $e->getMessage(),
                'type' => $e->getError()->type ?? 'unknown',
                'code' => $e->getError()->code ?? 'unknown'
            ]);

            return response()->json([
                'error' => 'Payment processing error',
                'message' => $e->getMessage(),
                'method' => 'charges_api_legacy'
            ], 422);

        } catch (\Exception $e) {
            Log::error('ACH Transfer Creation Failed (Charges API)', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'error' => 'Transfer creation failed',
                'message' => 'Failed to create legacy ACH transfer: ' . $e->getMessage(),
                'method' => 'charges_api_legacy'
            ], 500);
        }
    }

    /**
     * Regenerate and refresh Stripe bank account for existing customer using fresh Plaid token
     * This is needed because Stripe bank account tokens expire and need to be regenerated
     */
    /**
     * Refresh an existing Stripe Customer's Bank Account with fresh Plaid token
     * REFACTORED: Now uses focused helper methods for better maintainability
     */
    private function refreshStripeCustomerBankAccount(PlaidAccount $plaidAccount): ?array
    {
        try {
            $stripe = $this->initializeStripeClient();

            Log::info('Regenerating Stripe bank account with fresh Plaid token', [
                'plaid_account_id' => $plaidAccount->plaid_account_id,
                'existing_customer_id' => $plaidAccount->stripe_customer_id,
                'existing_bank_account_id' => $plaidAccount->stripe_bank_account_id
            ]);

            // Step 1: Remove old bank account source if it exists
            $this->removeOldBankAccountSource($stripe, $plaidAccount);

            // Step 2: Create new Bank Account Source with fresh token (try Plaid token first, fallback to manual)
            $bankAccount = $this->createBankAccountSource($stripe, $plaidAccount->stripe_customer_id, $plaidAccount);

            // Step 3: Update database with new bank account ID
            $this->updateRefreshedBankAccountData($plaidAccount, $bankAccount);

            return [
                'customer_id' => $plaidAccount->stripe_customer_id,
                'bank_account_id' => $bankAccount->id,
                'bank_account_status' => $bankAccount->status,
                'verification_required' => $bankAccount->status !== 'verified',
                'method' => 'refreshed_bank_account'
            ];

        } catch (\Exception $e) {
            Log::error('Failed to refresh Stripe bank account with fresh token', [
                'error' => $e->getMessage(),
                'plaid_account_id' => $plaidAccount->plaid_account_id,
                'customer_id' => $plaidAccount->stripe_customer_id,
                'trace' => $e->getTraceAsString()
            ]);

            return null;
        }
    }

    /**
     * Create and store persistent Stripe Customer + Bank Account from Plaid data
     * This approach stores long-lived Stripe objects instead of short-lived tokens
     */
    /**
     * Create and store persistent Stripe Customer + Bank Account from Plaid data
     * REFACTORED: Now uses focused helper methods for better maintainability
     */
    private function createStripeCustomerWithBankAccount(PlaidAccount $plaidAccount): ?array
    {
        try {
            $stripe = $this->initializeStripeClient();

            Log::info('Creating persistent Stripe Customer + Bank Account', [
                'plaid_account_id' => $plaidAccount->plaid_account_id,
                'institution' => $plaidAccount->institution_name
            ]);

            // Step 1: Prepare and create Stripe Customer
            $customerData = $this->prepareCustomerData($plaidAccount);
            $customer = $this->createStripeCustomer($stripe, $customerData);

            // Step 2: Create Bank Account Source with fallback
            $bankAccount = $this->createBankAccountSource($stripe, $customer->id, $plaidAccount);

            // Step 3: Store persistent objects in database
            $this->updatePlaidAccountWithStripeData($plaidAccount, $customer, $bankAccount);

            return [
                'customer_id' => $customer->id,
                'bank_account_id' => $bankAccount->id,
                'bank_account_status' => $bankAccount->status,
                'verification_required' => $bankAccount->status !== 'verified',
                'method' => 'persistent_customer_account'
            ];

        } catch (\Exception $e) {
            Log::error('Failed to create persistent Stripe Customer + Bank Account', [
                'error' => $e->getMessage(),
                'plaid_account_id' => $plaidAccount->plaid_account_id,
                'trace' => $e->getTraceAsString()
            ]);

            // Clean up any partially created resources
            if (isset($customer)) {
                $this->cleanupStripeCustomer($stripe, $customer);
            }

            return null;
        }
    }
    private function createStripeBankAccountToken(PlaidAccount $plaidAccount): ?string
    {
        $useProduction = config('app.use_production_apis', false);

        if (!$useProduction) {
            // For sandbox/test mode, return test token
            Log::info('Returning test Stripe bank account token for sandbox mode');
            return 'pm_usBankAccount_success';
        }

        // Production mode - create real payment method via Plaid Processor API
        try {
            Log::info('Creating real Stripe payment method via Plaid Processor API for production', [
                'plaid_account_id' => $plaidAccount->plaid_account_id,
                'user_id' => $plaidAccount->user_id
            ]);

            // First, create a processor token from Plaid
            Log::info('Making Plaid Processor API call', [
                'endpoint' => "{$this->baseUrl}/processor/stripe/bank_account_token/create",
                'client_id' => substr($this->clientId, 0, 8) . '***',
                'access_token' => substr($plaidAccount->access_token, 0, 10) . '***',
                'account_id' => $plaidAccount->plaid_account_id
            ]);

            $processorTokenResponse = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/processor/stripe/bank_account_token/create", [
                'client_id' => $this->clientId,
                'secret' => $this->secret,
                'access_token' => $plaidAccount->access_token,
                'account_id' => $plaidAccount->plaid_account_id,
            ]);

            // Log the full response for debugging
            Log::info('Plaid Processor API response', [
                'status' => $processorTokenResponse->status(),
                'successful' => $processorTokenResponse->successful(),
                'body' => $processorTokenResponse->body(),
                'json' => $processorTokenResponse->json()
            ]);

            if (!$processorTokenResponse->successful()) {
                Log::error('Failed to create Plaid processor token', [
                    'response' => $processorTokenResponse->json(),
                    'status' => $processorTokenResponse->status(),
                    'body' => $processorTokenResponse->body()
                ]);
                throw new \Exception('Failed to create Plaid processor token: ' . $processorTokenResponse->body());
            }

            $processorData = $processorTokenResponse->json();

            Log::info('Plaid Processor API response data', [
                'processor_data' => $processorData,
                'has_stripe_bank_account_token' => isset($processorData['stripe_bank_account_token']),
                'keys' => array_keys($processorData ?? [])
            ]);

            $bankAccountToken = $processorData['stripe_bank_account_token'] ?? null;

            if (!$bankAccountToken) {
                Log::error('No stripe bank account token in Plaid Processor response', [
                    'full_response' => $processorData,
                    'response_keys' => array_keys($processorData ?? [])
                ]);
                throw new \Exception('No stripe bank account token returned from Plaid Processor API. Response: ' . json_encode($processorData));
            }

            Log::info('Successfully created Stripe bank account token from Plaid Processor API', [
                'bank_account_token' => substr($bankAccountToken, 0, 10) . '***',
                'plaid_account_id' => $plaidAccount->plaid_account_id
            ]);

            // The bank account token from Plaid Processor API can be used directly with Stripe
            return $bankAccountToken;

        } catch (\Exception $e) {
            Log::error('Failed to create Stripe payment method from Plaid data', [
                'error' => $e->getMessage(),
                'plaid_account_id' => $plaidAccount->plaid_account_id,
                'user_id' => $plaidAccount->user_id
            ]);

            // In production, if Processor API fails, we can still use Auth API for ACH
            // This is a fallback that works without Processor API approval
            Log::warning('Processor API failed, will use Auth API fallback for ACH transfers', [
                'plaid_account_id' => $plaidAccount->plaid_account_id,
                'error' => $e->getMessage()
            ]);

            // Return null to indicate we should use Auth API method instead
            return null;
        }
    }

    /**
     * Get bank account details (routing/account numbers) from Plaid Auth API or use test data
     */
    private function getBankAccountDetails(PlaidAccount $plaidAccount): ?array
    {
        $useProduction = config('app.use_production_apis', false);

        if (!$useProduction) {
            // For sandbox/test mode, return test bank account details
            Log::info('Returning test bank account details for sandbox mode');
            return [
                'routing_number' => '110000000', // Stripe test routing number
                'account_number' => '000123456789', // Stripe test account number
                'account_type' => 'checking'
            ];
        }

        // Production mode - get real bank account details from Plaid Auth API
        try {
            Log::info('Fetching real bank account details from Plaid Auth API for production', [
                'plaid_account_id' => $plaidAccount->plaid_account_id,
                'user_id' => $plaidAccount->user_id
            ]);

            $authResponse = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/auth/get", [
                'client_id' => $this->clientId,
                'secret' => $this->secret,
                'access_token' => $plaidAccount->access_token,
            ]);

            if (!$authResponse->successful()) {
                Log::error('Failed to get auth data from Plaid', [
                    'response' => $authResponse->json(),
                    'status' => $authResponse->status()
                ]);
                throw new \Exception('Failed to get auth data from Plaid: ' . $authResponse->body());
            }

            $authData = $authResponse->json();
            $accounts = $authData['accounts'] ?? [];

            // Find the specific account we're working with
            $targetAccount = null;
            foreach ($accounts as $account) {
                if ($account['account_id'] === $plaidAccount->plaid_account_id) {
                    $targetAccount = $account;
                    break;
                }
            }

            if (!$targetAccount) {
                throw new \Exception('Account not found in Plaid auth response');
            }

            // Get routing and account numbers
            $authNumbers = $authData['numbers']['ach'] ?? [];
            $accountNumbers = null;

            foreach ($authNumbers as $achAccount) {
                if ($achAccount['account_id'] === $plaidAccount->plaid_account_id) {
                    $accountNumbers = $achAccount;
                    break;
                }
            }

            if (!$accountNumbers) {
                throw new \Exception('ACH numbers not found for account');
            }

            $bankDetails = [
                'routing_number' => $accountNumbers['routing'],
                'account_number' => $accountNumbers['account'],
                'account_type' => strtolower($targetAccount['subtype']) === 'savings' ? 'savings' : 'checking'
            ];

            Log::info('Successfully retrieved real bank account details from Plaid', [
                'routing_number' => substr($bankDetails['routing_number'], 0, 4) . '****',
                'account_number' => '****' . substr($bankDetails['account_number'], -4),
                'account_type' => $bankDetails['account_type']
            ]);

            return $bankDetails;

        } catch (\Exception $e) {
            Log::error('Failed to get real bank account details from Plaid', [
                'error' => $e->getMessage(),
                'plaid_account_id' => $plaidAccount->plaid_account_id,
                'user_id' => $plaidAccount->user_id
            ]);

            // In production, we should not fall back to test data
            throw new \Exception('Production mode requires real bank account data: ' . $e->getMessage());
        }
    }

    private function mapStripeStatusToTransactionStatus($stripeStatus)
    {
        $statusMap = [
            'pending' => 'pending',
            'succeeded' => 'succeeded',
            'failed' => 'failed',
            'canceled' => 'cancelled',
        ];

        return $statusMap[$stripeStatus] ?? 'pending';
    }

    /**
     * Get current API configuration status and credentials being used
     */
    public function getConfigurationStatus(): JsonResponse
    {
        try {
            $useProduction = config('app.use_production_apis', false);

            // Get Plaid configuration
            $plaidConfig = [
                'environment' => $this->environment,
                'client_id' => $this->clientId ? substr($this->clientId, 0, 8) . '***' : 'Not Set',
                'base_url' => $this->baseUrl,
                'secret_key' => $this->secret ? substr($this->secret, 0, 8) . '***' : 'Not Set'
            ];

            // Get Stripe configuration
            $stripeSecretKey = $useProduction
                ? (config('services.stripe.prod_secret') ?? env('STRIPE_PROD_SECRET_KEY'))
                : (config('services.stripe.secret') ?? env('STRIPE_SECRET_KEY'));

            $stripeConfig = [
                'environment' => $useProduction ? 'production' : 'test',
                'secret_key' => $stripeSecretKey ? substr($stripeSecretKey, 0, 8) . '***' : 'Not Set'
            ];

            return response()->json([
                'success' => true,
                'use_production_apis' => $useProduction,
                'message' => $useProduction
                    ? '🚨 PRODUCTION MODE: Using live APIs and real money'
                    : '🧪 SANDBOX MODE: Using test APIs and fake money',
                'plaid' => $plaidConfig,
                'stripe' => $stripeConfig
            ]);

        } catch (\Exception $e) {
            Log::error('Configuration Status Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to load configuration status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create Stripe Checkout Session with Financial Connections prioritizing instant verification
     */
    public function createCheckoutSessionWithFinancialConnections(Request $request)
    {
        try {
            $validated = $request->validate([
                'customer_id' => 'nullable|string',
                'customer_email' => 'required|email',
                'amount' => 'required|numeric|min:0.01',
                'currency' => 'string|in:usd',
                'description' => 'nullable|string',
                'success_url' => 'required|url',
                'cancel_url' => 'required|url',
                'setup_future_usage' => 'boolean',
            ]);

            // Initialize Stripe client
            $useProduction = config('app.use_production_apis', false);
            $stripeSecret = $useProduction
                ? (config('services.stripe.prod_secret') ?? env('STRIPE_PROD_SECRET_KEY'))
                : (config('services.stripe.secret') ?? env('STRIPE_SECRET_KEY'));
            $stripe = new \Stripe\StripeClient($stripeSecret);

            // Create or retrieve customer
            if (empty($validated['customer_id'])) {
                $customer = $stripe->customers->create([
                    'email' => $validated['customer_email'],
                    'metadata' => [
                        'created_via' => 'plaid_stripe_integration',
                        'platform' => 'lending_platform'
                    ]
                ]);
                $customerId = $customer->id;
                Log::info('Created new Stripe customer', ['customer_id' => $customerId]);
            } else {
                $customerId = $validated['customer_id'];
                Log::info('Using existing Stripe customer', ['customer_id' => $customerId]);
            }

            $amount = intval($validated['amount'] * 100); // Convert to cents
            $currency = $validated['currency'] ?? 'usd';

            // Configure session parameters with Financial Connections optimization
            $sessionParams = [
                'mode' => 'payment',
                'customer' => $customerId,
                'payment_method_types' => ['card', 'us_bank_account'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => $currency,
                        'unit_amount' => $amount,
                        'product_data' => [
                            'name' => $validated['description'] ?? 'ACH Transfer via Lending Platform',
                        ],
                    ],
                    'quantity' => 1,
                ]],
                'success_url' => $validated['success_url'],
                'cancel_url' => $validated['cancel_url'],
                'payment_intent_data' => [
                    'metadata' => [
                        'platform' => 'lending_platform',
                        'payment_type' => 'ach_transfer',
                        'verification_preference' => 'instant'
                    ]
                ]
            ];

            // Add setup future usage if requested
            if ($validated['setup_future_usage'] ?? false) {
                $sessionParams['payment_intent_data']['setup_future_usage'] = 'off_session';
            }

            // Configure Financial Connections to prioritize instant verification
            $financialConnectionsConfig = config('services.stripe.financial_connections');
            if ($financialConnectionsConfig['enabled'] ?? true) {
                // This tells Stripe to prioritize Financial Connections (instant verification)
                // over manual entry + microdeposits
                $sessionParams['payment_method_options'] = [
                    'us_bank_account' => [
                        'financial_connections' => [
                            'permissions' => ['payment_method'] // Request minimal permissions for faster flow
                        ],
                        'verification_method' => 'instant' // Prioritize instant verification
                    ]
                ];

                // Only allow microdeposit fallback if configured
                if (!($financialConnectionsConfig['require_financial_connections'] ?? false)) {
                    $sessionParams['payment_method_options']['us_bank_account']['verification_method'] = 'automatic';
                }
            }

            Log::info('Creating Checkout session with Financial Connections', [
                'customer_id' => $customerId,
                'amount' => $amount,
                'currency' => $currency,
                'financial_connections_enabled' => $financialConnectionsConfig['enabled'] ?? true,
                'verification_priority' => $financialConnectionsConfig['verification_priority'] ?? 'instant'
            ]);

            $session = $stripe->checkout->sessions->create($sessionParams);

            return response()->json([
                'success' => true,
                'session' => [
                    'id' => $session->id,
                    'url' => $session->url,
                    'customer_id' => $customerId,
                    'payment_intent' => $session->payment_intent,
                ],
                'configuration' => [
                    'verification_method' => 'instant_priority',
                    'financial_connections_enabled' => $financialConnectionsConfig['enabled'] ?? true,
                    'fallback_enabled' => $financialConnectionsConfig['fallback_to_microdeposits'] ?? true
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create Checkout session with Financial Connections', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to create checkout session',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle return from Financial Connections flow
     */
    public function handleFinancialConnectionsReturn(Request $request)
    {
        Log::info('Financial Connections return', [
            'query_params' => $request->all()
        ]);

        return view('financial-connections-return', [
            'success' => $request->query('success', true),
            'payment_intent' => $request->query('payment_intent'),
            'message' => $request->query('success')
                ? 'Bank account successfully connected and payment processed!'
                : 'There was an issue with the bank authentication.'
        ]);
    }

    /**
     * Create PaymentMethod with Financial Connections (instant verification priority)
     */
    public function createPaymentMethodWithFinancialConnections(Request $request)
    {
        try {
            $validated = $request->validate([
                'customer_id' => 'required|string',
            ]);

            // Initialize Stripe client
            $useProduction = config('app.use_production_apis', false);
            $stripeSecret = $useProduction
                ? (config('services.stripe.prod_secret') ?? env('STRIPE_PROD_SECRET_KEY'))
                : (config('services.stripe.secret') ?? env('STRIPE_SECRET_KEY'));
            $stripe = new \Stripe\StripeClient($stripeSecret);

            // Create a Financial Connections session for instant verification
            $fcSession = $stripe->financialConnections->sessions->create([
                'account_holder' => [
                    'type' => 'customer',
                    'customer' => $validated['customer_id'],
                ],
                'permissions' => ['payment_method'], // Minimal permissions for payment use
                'filters' => [
                    'countries' => ['US'], // US bank accounts only
                ],
                'return_url' => config('app.url') . '/financial-connections/return',
            ]);

            Log::info('Created Financial Connections session for instant verification', [
                'session_id' => $fcSession->id,
                'customer_id' => $validated['customer_id']
            ]);

            return response()->json([
                'success' => true,
                'financial_connections_session' => [
                    'id' => $fcSession->id,
                    'client_secret' => $fcSession->client_secret,
                ],
                'instructions' => [
                    'step_1' => 'Use the Financial Connections client_secret to initialize the Financial Connections SDK',
                    'step_2' => 'Customer will authenticate with their bank for instant verification',
                    'step_3' => 'Retrieve the payment method after successful connection'
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create Financial Connections session', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to create Financial Connections session',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Manually confirm a PaymentIntent that requires confirmation
     */
    public function confirmPaymentIntent(Request $request)
    {
        $request->validate([
            'payment_intent_id' => 'required|string',
        ]);

        try {
            $useProduction = config('app.use_production_apis', false);
            $stripeSecret = $useProduction
                ? (config('services.stripe.prod_secret') ?? env('STRIPE_PROD_SECRET_KEY'))
                : (config('services.stripe.secret') ?? env('STRIPE_SECRET_KEY'));
            $stripe = new \Stripe\StripeClient($stripeSecret);

            // Confirm PaymentIntent with mandate data if needed
            $confirmParams = [];

            // Add mandate data for US bank account payments
            $confirmParams['mandate_data'] = [
                'customer_acceptance' => [
                    'type' => 'online',
                    'online' => [
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->header('User-Agent'),
                    ],
                    'accepted_at' => time(),
                ]
            ];

            $paymentIntent = $stripe->paymentIntents->confirm($request->payment_intent_id, $confirmParams);

            // Update the transaction record if it exists
            $transaction = Transaction::where('stripe_payment_intent_id', $request->payment_intent_id)->first();
            if ($transaction) {
                $transaction->update([
                    'status' => $this->mapStripeStatusToDatabase($paymentIntent->status)
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'PaymentIntent confirmed successfully',
                'payment_intent' => [
                    'id' => $paymentIntent->id,
                    'status' => $paymentIntent->status,
                    'amount' => $paymentIntent->amount / 100,
                ],
                'transaction_updated' => $transaction ? true : false
            ]);

        } catch (\Exception $e) {
            Log::error('Manual PaymentIntent confirmation failed', [
                'payment_intent_id' => $request->payment_intent_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Confirmation failed',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Complete bank account verification with microdeposit amounts
     */
    public function completeBankVerification(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|string',
            'bank_account_id' => 'required|string',
            'transaction_id' => 'required|integer',
            'amount1' => 'required|integer|min:1|max:99',
            'amount2' => 'required|integer|min:1|max:99',
        ]);

        try {
            $useProduction = config('app.use_production_apis', false);
            $stripeSecret = $useProduction
                ? (config('services.stripe.prod_secret') ?? env('STRIPE_PROD_SECRET_KEY'))
                : (config('services.stripe.secret') ?? env('STRIPE_SECRET_KEY'));
            $stripe = new \Stripe\StripeClient($stripeSecret);

            // Verify the bank account with the provided microdeposit amounts
            $verifiedBankAccount = $stripe->customers->verifySource(
                $request->customer_id,
                $request->bank_account_id,
                [
                    'amounts' => [$request->amount1, $request->amount2]
                ]
            );

            Log::info('Bank account verification successful', [
                'bank_account_id' => $request->bank_account_id,
                'customer_id' => $request->customer_id,
                'transaction_id' => $request->transaction_id
            ]);

            // Find the pending transaction
            $transaction = Transaction::find($request->transaction_id);
            if (!$transaction) {
                return response()->json([
                    'success' => false,
                    'error' => 'Transaction not found',
                    'message' => 'Could not find the pending transaction.'
                ], 404);
            }

            // Now create the charge since the bank account is verified
            $originalRequest = $transaction->metadata['original_request'];
            $charge = $stripe->charges->create([
                'amount' => $originalRequest['amount_in_cents'],
                'currency' => 'usd',
                'customer' => $request->customer_id,
                'source' => $request->bank_account_id,
                'description' => $originalRequest['description'],
                'metadata' => [
                    'transaction_id' => $transaction->id,
                    'verification_completed' => true,
                    'platform' => 'lending_platform'
                ]
            ]);

            // Update the transaction with the charge details
            $transaction->update([
                'stripe_payment_intent_id' => $charge->id,
                'status' => $this->mapStripeStatusToDatabase($charge->status),
                'metadata' => array_merge($transaction->metadata, [
                    'verification_status' => 'completed',
                    'verification_completed_at' => now(),
                    'stripe_charge' => $charge->toArray()
                ])
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Bank account verified and payment processed successfully',
                'verification' => [
                    'status' => 'completed',
                    'bank_account_verified' => true
                ],
                'charge' => [
                    'id' => $charge->id,
                    'amount' => $charge->amount / 100,
                    'status' => $charge->status,
                    'description' => $charge->description
                ],
                'transaction' => [
                    'id' => $transaction->id,
                    'status' => $transaction->status,
                    'amount' => $transaction->amount
                ]
            ]);

        } catch (\Stripe\Exception\InvalidRequestException $e) {
            if (strpos($e->getMessage(), 'amounts') !== false) {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid verification amounts',
                    'message' => 'The amounts you entered do not match the microdeposits. Please check your bank statement and try again.',
                    'details' => [
                        'hint' => 'Make sure to enter amounts in cents (e.g., for $0.32, enter 32)',
                        'retry_allowed' => true
                    ]
                ], 400);
            }

            throw $e;

        } catch (\Exception $e) {
            Log::error('Bank verification completion failed', [
                'customer_id' => $request->customer_id,
                'bank_account_id' => $request->bank_account_id,
                'transaction_id' => $request->transaction_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Verification failed',
                'message' => 'Failed to complete bank account verification: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Create a simple verification UI page
     */
    public function showBankVerificationPage(Request $request)
    {
        $customerId = $request->query('customer_id');
        $bankAccountId = $request->query('bank_account_id');
        $transactionId = $request->query('transaction_id');

        if (!$customerId || !$bankAccountId || !$transactionId) {
            abort(400, 'Missing required parameters');
        }

        $transaction = Transaction::find($transactionId);
        if (!$transaction) {
            abort(404, 'Transaction not found');
        }

        return view('bank-verification', compact('customerId', 'bankAccountId', 'transactionId', 'transaction'));
    }

    /**
     * Validate Plaid tokens and diagnose Stripe verification issues
     */
    public function validatePlaidStripeIntegration(Request $request)
    {
        $request->validate([
            'plaid_account_id' => 'required|integer|exists:plaid_accounts,id'
        ]);

        try {
            $plaidAccount = PlaidAccount::findOrFail($request->plaid_account_id);
            $useProduction = config('app.use_production_apis', false);

            $validation = [
                'plaid_account_info' => [
                    'id' => $plaidAccount->id,
                    'plaid_account_id' => $plaidAccount->plaid_account_id,
                    'institution' => $plaidAccount->institution_name,
                    'account_name' => $plaidAccount->account_name,
                    'has_access_token' => !empty($plaidAccount->access_token),
                    'environment' => $useProduction ? 'production' : 'sandbox'
                ]
            ];

            // Test 1: Validate Plaid Processor Token Creation
            Log::info('=== PLAID TOKEN VALIDATION TEST ===');

            try {
                // Generate a FRESH token for validation (old tokens expire quickly)
                $bankAccountToken = $this->createStripeBankAccountToken($plaidAccount);

                $validation['plaid_processor_token'] = [
                    'status' => $bankAccountToken ? 'success' : 'failed',
                    'token_type' => $bankAccountToken ?
                        (str_starts_with($bankAccountToken, 'btok_') ? 'production_bank_token' :
                         ($bankAccountToken === 'pm_usBankAccount_success' ? 'test_payment_method' : 'unknown')) : 'none',
                    'token_prefix' => $bankAccountToken ? substr($bankAccountToken, 0, 15) . '***' : null,
                    'plaid_integration' => $bankAccountToken ? 'working' : 'failed',
                    'note' => 'Fresh token generated for validation - Plaid processor tokens expire quickly'
                ];
            } catch (\Exception $e) {
                $validation['plaid_processor_token'] = [
                    'status' => 'error',
                    'error' => $e->getMessage(),
                    'plaid_integration' => 'failed',
                    'note' => 'Failed to generate fresh processor token'
                ];
            }

            // Test 2: Validate Stripe Integration
            if (!empty($bankAccountToken)) {
                Log::info('=== STRIPE INTEGRATION TEST ===');

                $stripeSecret = $useProduction
                    ? (config('services.stripe.prod_secret') ?? env('STRIPE_PROD_SECRET_KEY'))
                    : (config('services.stripe.secret') ?? env('STRIPE_SECRET_KEY'));
                $stripe = new \Stripe\StripeClient($stripeSecret);

                try {
                    // Create a test customer
                    $testCustomer = $stripe->customers->create([
                        'email' => 'test@example.com',
                        'name' => 'Token Validation Test',
                        'metadata' => ['validation_test' => 'true']
                    ]);

                    // Test creating bank account source
                    if (str_starts_with($bankAccountToken, 'btok_')) {
                        // Production token - create bank account source
                        try {
                            $bankAccount = $stripe->customers->createSource($testCustomer->id, [
                                'source' => $bankAccountToken
                            ]);

                            $validation['stripe_integration'] = [
                                'status' => 'token_valid',
                                'customer_created' => true,
                                'bank_account_created' => true,
                                'bank_account_id' => $bankAccount->id,
                                'verification_status' => $bankAccount->status ?? 'new',
                                'verification_required' => $bankAccount->status !== 'verified',
                                'next_step' => $bankAccount->status !== 'verified' ?
                                    'Bank account verification required via microdeposits' :
                                    'Ready for ACH transfers'
                            ];

                        } catch (\Stripe\Exception\InvalidRequestException $e) {
                            $validation['stripe_integration'] = [
                                'status' => 'token_invalid',
                                'error' => $e->getMessage(),
                                'suggestion' => 'The Plaid processor token may be invalid or expired'
                            ];
                        }

                    } else if ($bankAccountToken === 'pm_usBankAccount_success') {
                        // Test mode - validate test token
                        $validation['stripe_integration'] = [
                            'status' => 'test_mode',
                            'token_valid' => true,
                            'verification_required' => false,
                            'note' => 'Test mode - verification not required for test tokens'
                        ];
                    }

                    // Clean up test customer
                    $stripe->customers->delete($testCustomer->id);

                } catch (\Exception $e) {
                    $validation['stripe_integration'] = [
                        'status' => 'error',
                        'error' => $e->getMessage(),
                        'stripe_connection' => 'failed'
                    ];
                }
            }

            // Test 3: Bank Account Details Validation
            try {
                $bankDetails = $this->getBankAccountDetails($plaidAccount);
                $validation['bank_account_details'] = [
                    'status' => $bankDetails ? 'success' : 'failed',
                    'routing_number' => $bankDetails ? substr($bankDetails['routing_number'], 0, 4) . '****' : null,
                    'account_number' => $bankDetails ? '****' . substr($bankDetails['account_number'], -4) : null,
                    'account_type' => $bankDetails['account_type'] ?? null,
                    'note' => 'These details can be used as fallback if processor token fails'
                ];
            } catch (\Exception $e) {
                $validation['bank_account_details'] = [
                    'status' => 'error',
                    'error' => $e->getMessage()
                ];
            }

            // Summary and Recommendations
            $plaidWorking = !empty($bankAccountToken);
            $stripeTokenValid = isset($validation['stripe_integration']) &&
                in_array($validation['stripe_integration']['status'], ['token_valid', 'test_mode']);
            $verificationNeeded = isset($validation['stripe_integration']['verification_required']) &&
                $validation['stripe_integration']['verification_required'];

            $validation['diagnosis'] = [
                'overall_status' => 'analysis_complete',
                'plaid_working' => $plaidWorking,
                'stripe_token_valid' => $stripeTokenValid,
                'verification_needed' => $verificationNeeded,
                'recommendation' => $this->getIntegrationRecommendation($plaidWorking, $stripeTokenValid, $verificationNeeded)
            ];

            return response()->json([
                'success' => true,
                'message' => 'Plaid-Stripe integration validation completed',
                'validation_results' => $validation,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error('Token validation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get integration recommendations based on validation results
     */
    private function getIntegrationRecommendation(bool $plaidWorking, bool $stripeTokenValid, bool $verificationNeeded): string
    {
        if (!$plaidWorking) {
            return 'Fix Plaid processor token creation first - check Plaid API credentials and account permissions';
        }

        if (!$stripeTokenValid) {
            return 'Plaid tokens are being generated but failing in Stripe. This is likely due to: (1) Token expiration - Plaid processor tokens expire within minutes and must be used immediately, (2) Environment mismatch between Plaid and Stripe accounts, or (3) Invalid Stripe API configuration';
        }

        if ($verificationNeeded) {
            return 'Token is valid but bank account verification is required. The error you received is expected - use microdeposit verification or Financial Connections';
        }

        return 'Integration is working correctly - ready for ACH transfers';
    }

    // ====================================================================
    // REFACTORED HELPER METHODS - Single Responsibility Functions
    // ====================================================================

    /**
     * Initialize Stripe client based on production settings
     */
    private function initializeStripeClient(): \Stripe\StripeClient
    {
        $useProduction = config('app.use_production_apis', false);
        $stripeSecret = $useProduction
            ? (config('services.stripe.prod_secret') ?? env('STRIPE_PROD_SECRET_KEY'))
            : (config('services.stripe.secret') ?? env('STRIPE_SECRET_KEY'));

        return new \Stripe\StripeClient($stripeSecret);
    }

    /**
     * Extract customer information from PlaidAccount for Stripe customer creation
     */
    private function prepareCustomerData(PlaidAccount $plaidAccount): array
    {
        $user = $plaidAccount->user ?? \App\Models\LendingUser::find($plaidAccount->user_id);

        return [
            'name' => $user ? $user->business_name : 'Account Holder',
            'email' => $user ? $user->email : null,
            'description' => "Customer for {$plaidAccount->institution_name} - {$plaidAccount->account_name}",
            'metadata' => [
                'plaid_account_id' => $plaidAccount->plaid_account_id,
                'plaid_institution' => $plaidAccount->institution_name,
                'account_name' => $plaidAccount->account_name,
                'user_id' => $plaidAccount->user_id,
                'integration_method' => 'plaid_processor_api',
                'created_via' => 'plaid_stripe_integration'
            ]
        ];
    }

    /**
     * Create Stripe customer with prepared data
     */
    private function createStripeCustomer(\Stripe\StripeClient $stripe, array $customerData): \Stripe\Customer
    {
        $customer = $stripe->customers->create($customerData);

        Log::info('Created Stripe Customer', [
            'customer_id' => $customer->id,
            'plaid_account_id' => $customerData['metadata']['plaid_account_id']
        ]);

        return $customer;
    }

    /**
     * Create bank account source using Plaid token with fallback to manual creation
     */
    private function createBankAccountSource(\Stripe\StripeClient $stripe, string $customerId, PlaidAccount $plaidAccount): \Stripe\Source
    {
        // Try Plaid token first
        $bankAccountToken = $this->createStripeBankAccountToken($plaidAccount);

        if (!$bankAccountToken) {
            throw new \Exception('Failed to generate Stripe bank account token from Plaid');
        }

        Log::info('Generated fresh bank account token for customer creation', [
            'customer_id' => $customerId,
            'token_prefix' => substr($bankAccountToken, 0, 15) . '***'
        ]);

        try {
            return $this->createBankAccountFromToken($stripe, $customerId, $bankAccountToken);
        } catch (\Exception $tokenException) {
            Log::warning('Plaid bank account token failed, falling back to manual creation', [
                'token_error' => $tokenException->getMessage(),
                'customer_id' => $customerId,
                'token_prefix' => substr($bankAccountToken, 0, 15) . '***'
            ]);

            return $this->createBankAccountManually($stripe, $customerId, $plaidAccount);
        }
    }

    /**
     * Create bank account source from Plaid token
     */
    private function createBankAccountFromToken(\Stripe\StripeClient $stripe, string $customerId, string $token): \Stripe\Source
    {
        $bankAccount = $stripe->customers->createSource($customerId, [
            'source' => $token
        ]);

        Log::info('Successfully created persistent Stripe Bank Account Source from Plaid token', [
            'customer_id' => $customerId,
            'bank_account_id' => $bankAccount->id,
            'bank_account_status' => $bankAccount->status,
            'method' => 'plaid_token'
        ]);

        return $bankAccount;
    }

    /**
     * Create bank account source manually using Plaid Auth API data
     */
    private function createBankAccountManually(\Stripe\StripeClient $stripe, string $customerId, PlaidAccount $plaidAccount): \Stripe\Source
    {
        $bankAccountDetails = $this->getBankAccountDetails($plaidAccount);

        if (!$bankAccountDetails) {
            throw new \Exception('Failed to get bank account details from Plaid for manual creation');
        }

        $user = $plaidAccount->user ?? \App\Models\LendingUser::find($plaidAccount->user_id);
        $accountHolderName = $user ? ($user->business_name ?? $user->name ?? 'Account Holder') : 'Account Holder';

        $bankAccount = $stripe->customers->createSource($customerId, [
            'source' => [
                'object' => 'bank_account',
                'country' => 'US',
                'currency' => 'usd',
                'account_holder_name' => $accountHolderName,
                'account_holder_type' => 'individual',
                'routing_number' => $bankAccountDetails['routing_number'],
                'account_number' => $bankAccountDetails['account_number'],
            ]
        ]);

        Log::info('Successfully created persistent Stripe Bank Account Source manually', [
            'customer_id' => $customerId,
            'bank_account_id' => $bankAccount->id,
            'bank_account_status' => $bankAccount->status,
            'method' => 'manual_creation'
        ]);

        return $bankAccount;
    }

    /**
     * Update PlaidAccount with Stripe customer and bank account information
     */
    private function updatePlaidAccountWithStripeData(PlaidAccount $plaidAccount, \Stripe\Customer $customer, \Stripe\Source $bankAccount): void
    {
        $plaidAccount->update([
            'stripe_customer_id' => $customer->id,
            'stripe_bank_account_id' => $bankAccount->id,
            'stripe_bank_account_details' => [
                'status' => $bankAccount->status,
                'bank_name' => $bankAccount->bank_name ?? $plaidAccount->institution_name,
                'last4' => $bankAccount->last4 ?? null,
                'routing_number' => $bankAccount->routing_number ?? null,
                'account_holder_type' => $bankAccount->account_holder_type ?? 'individual',
                'account_type' => $bankAccount->account_type ?? 'checking',
                'fingerprint' => $bankAccount->fingerprint ?? null
            ],
            'stripe_bank_account_created_at' => now(),
            'stripe_integration_status' => 'active',
            'stripe_bank_account_token' => null, // Don't store the temporary token
            'metadata' => array_merge($plaidAccount->metadata ?? [], [
                'stripe_integration' => [
                    'method' => 'persistent_customer_account',
                    'customer_created_at' => now()->toISOString(),
                    'bank_account_status' => $bankAccount->status,
                    'verification_required' => $bankAccount->status !== 'verified',
                    'note' => 'Using persistent Stripe objects instead of temporary tokens'
                ]
            ])
        ]);

        Log::info('Stored persistent Stripe objects in database', [
            'plaid_account_id' => $plaidAccount->plaid_account_id,
            'stripe_customer_id' => $customer->id,
            'stripe_bank_account_id' => $bankAccount->id,
            'verification_status' => $bankAccount->status
        ]);
    }

    /**
     * Clean up partially created Stripe customer on failure
     */
    private function cleanupStripeCustomer(\Stripe\StripeClient $stripe, \Stripe\Customer $customer): void
    {
        try {
            $stripe->customers->delete($customer->id);
            Log::info('Cleaned up partially created Stripe customer', [
                'customer_id' => $customer->id
            ]);
        } catch (\Exception $cleanupException) {
            Log::warning('Failed to clean up Stripe customer', [
                'customer_id' => $customer->id,
                'cleanup_error' => $cleanupException->getMessage()
            ]);
        }
    }

    /**
     * Handle verification requirements for unverified bank accounts
     */
    private function handleBankAccountVerification(\Stripe\Source $bankAccount, PlaidAccount $fromAccount, \Stripe\Customer $customer): JsonResponse
    {
        Log::info('Bank account created - microdeposits will be sent automatically', [
            'customer_id' => $customer->id,
            'bank_account_id' => $bankAccount->id,
            'current_status' => $bankAccount->status
        ]);

        return response()->json([
            'success' => false,
            'requires_verification' => true,
            'verification_method' => 'microdeposits',
            'message' => 'Bank account created successfully. Microdeposits will be sent within 1-2 business days.',
            'verification_details' => [
                'customer_id' => $customer->id,
                'bank_account_id' => $bankAccount->id,
                'bank_name' => $bankAccount->bank_name ?? $fromAccount->institution_name,
                'last4' => $bankAccount->last4 ?? 'unknown',
                'verification_status' => $bankAccount->status,
                'next_steps' => 'Wait for microdeposits (1-2 business days), then use the bank verification endpoint to complete the process.'
            ]
        ]);
    }

    /**
     * Remove old bank account source from Stripe Customer
     * Single responsibility: Clean up old bank account before creating new one
     */
    private function removeOldBankAccountSource(\Stripe\StripeClient $stripe, PlaidAccount $plaidAccount): void
    {
        if (!$plaidAccount->stripe_bank_account_id) {
            return;
        }

        try {
            $stripe->customers->deleteSource(
                $plaidAccount->stripe_customer_id,
                $plaidAccount->stripe_bank_account_id
            );

            Log::info('Removed old bank account source', [
                'customer_id' => $plaidAccount->stripe_customer_id,
                'old_bank_account_id' => $plaidAccount->stripe_bank_account_id
            ]);
        } catch (\Exception $deleteException) {
            Log::warning('Could not delete old bank account source, continuing with new one', [
                'customer_id' => $plaidAccount->stripe_customer_id,
                'old_bank_account_id' => $plaidAccount->stripe_bank_account_id,
                'error' => $deleteException->getMessage()
            ]);
        }
    }

    /**
     * Update PlaidAccount with refreshed bank account data
     * Single responsibility: Store refreshed bank account information
     */
    private function updateRefreshedBankAccountData(PlaidAccount $plaidAccount, \Stripe\Source $bankAccount): void
    {
        $plaidAccount->update([
            'stripe_bank_account_id' => $bankAccount->id,
            'stripe_bank_account_details' => [
                'status' => $bankAccount->status,
                'bank_name' => $bankAccount->bank_name ?? $plaidAccount->institution_name,
                'last4' => $bankAccount->last4 ?? null,
                'routing_number' => $bankAccount->routing_number ?? null,
                'account_holder_type' => $bankAccount->account_holder_type ?? 'individual',
                'account_type' => $bankAccount->account_type ?? 'checking',
                'fingerprint' => $bankAccount->fingerprint ?? null
            ],
            'stripe_bank_account_created_at' => now(),
            'metadata' => array_merge($plaidAccount->metadata ?? [], [
                'stripe_integration' => array_merge(
                    $plaidAccount->metadata['stripe_integration'] ?? [],
                    [
                        'bank_account_refreshed_at' => now()->toISOString(),
                        'refresh_reason' => 'fresh_token_regeneration',
                        'note' => 'Bank account refreshed with fresh Plaid processor token'
                    ]
                )
            ])
        ]);

        Log::info('Updated database with refreshed bank account', [
            'plaid_account_id' => $plaidAccount->plaid_account_id,
            'customer_id' => $plaidAccount->stripe_customer_id,
            'new_bank_account_id' => $bankAccount->id
        ]);
    }
}

