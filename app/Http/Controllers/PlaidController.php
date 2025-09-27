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

class PlaidController extends Controller
{
    private string $clientId;
    private string $secret;
    private string $baseUrl;
    private string $environment;

    public function __construct()
    {
        $this->clientId = config('services.plaid.client_id');
        $this->secret = config('services.plaid.secret');
        $this->baseUrl = config('services.plaid.base_url');
        $this->environment = config('services.plaid.environment');
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

            $requestData = [
                'client_id' => $this->clientId,
                'secret' => $this->secret,
                'user' => [
                    'client_user_id' => $clientUserId,
                ],
                'client_name' => config('app.name') . ' - Plaid Integration Demo',
                'products' => ['auth', 'transactions'],
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
            // Exchange the public token for an access token
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/item/public_token/exchange", [
                'client_id' => $this->clientId,
                'secret' => $this->secret,
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
                'secret' => $this->secret,
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
            $metadata = $request->metadata;
            $savedAccounts = [];

            // Save each account to the database
            foreach ($accountsData['accounts'] as $account) {
                $plaidAccount = PlaidAccount::updateOrCreate(
                    [
                        'plaid_account_id' => $account['account_id'],
                    ],
                    [
                        'user_id' => $request->user_id,
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
                ];
            }

            Log::info('Plaid accounts saved to database', [
                'item_id' => $itemId,
                'accounts_count' => count($savedAccounts),
                'institution' => $metadata['institution']['name']
            ]);

            return response()->json([
                'success' => true,
                'access_token' => $accessToken,
                'item_id' => $itemId,
                'request_id' => $tokenData['request_id'],
                'accounts_saved' => count($savedAccounts),
                'accounts' => $savedAccounts,
                'message' => 'Token exchange successful and accounts saved to database'
            ]);

        } catch (\Exception $e) {
            Log::error('Plaid Token Exchange Exception', [
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
        ]);

        try {
            Log::info('Starting ACH Transfer Creation', [
                'from_account_id' => $request->from_account_id,
                'to_account_id' => $request->to_account_id,
                'amount' => $request->amount
            ]);

            // Get source and destination accounts with user relationships
            $fromAccount = PlaidAccount::with('user')->findOrFail($request->from_account_id);
            $toAccount = PlaidAccount::with('user')->findOrFail($request->to_account_id);

            Log::info('Accounts loaded successfully', [
                'from_account_loaded' => !is_null($fromAccount),
                'to_account_loaded' => !is_null($toAccount),
                'from_user_loaded' => !is_null($fromAccount->user),
                'to_user_loaded' => !is_null($toAccount->user)
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

            Log::info('Creating Real ACH Transfer', [
                'from_account' => $fromAccount->plaid_account_id,
                'to_account' => $toAccount->plaid_account_id,
                'amount' => $request->amount,
                'from_user' => $fromAccount->user_id,
                'to_user' => $toAccount->user_id
            ]);

            // First try Plaid Processor API, fallback to Auth API if needed
            Log::info('Attempting Plaid Processor API for bank token');
            $bankAccountToken = $this->createStripeBankAccountToken($fromAccount);
            $useProcessorMethod = !empty($bankAccountToken);

            $bankAccountDetails = null;
            if (!$useProcessorMethod) {
                Log::info('Plaid Processor method failed, falling back to Auth API method');
                $bankAccountDetails = $this->getBankAccountDetails($fromAccount);
                if (!$bankAccountDetails) {
                    Log::error('Both Processor and Auth API methods failed');
                    return response()->json([
                        'error' => 'Unable to retrieve bank account information',
                        'message' => 'Failed to get bank account details via both Processor and Auth API. Please try again later.'
                    ], 400);
                }
            }

            Log::info('Bank account method determined', [
                'use_processor' => $useProcessorMethod,
                'has_auth_details' => !is_null($bankAccountDetails)
            ]);

            // Create Stripe ACH debit
            Log::info('Initializing Stripe client');
            $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));

            // Note: setRequestTimeout() doesn't exist in newer Stripe PHP SDK
            // Stripe handles timeouts internally

            // Get user details for billing information
            $fromUser = $fromAccount->user ?? \App\Models\LendingUser::find($fromAccount->user_id);
            $billingName = $fromUser ? $fromUser->business_name : 'Account Holder';
            $billingEmail = $fromUser ? $fromUser->email : null;

            Log::info('Creating Stripe payment method', [
                'billing_name' => $billingName,
                'use_processor' => $useProcessorMethod,
                'bank_account_token' => $useProcessorMethod ? substr($bankAccountToken ?? '', 0, 20) . '...' : null
            ]);

            if ($useProcessorMethod) {
                // Method 1: Using Plaid Processor bank account token (preferred) or test token
                if ($bankAccountToken === 'pm_usBankAccount_success') {
                    // Use Stripe's test payment method directly - don't create a new one
                    $paymentIntent = $stripe->paymentIntents->create([
                        'amount' => $amountInCents,
                        'currency' => 'usd',
                        'payment_method' => $bankAccountToken, // Use test token directly
                        'payment_method_types' => ['us_bank_account'],
                        'description' => $request->description ?? 'ACH Transfer via Lending Platform',
                        'metadata' => [
                            'from_account_id' => $fromAccount->user_id, // Use user_id for consistency
                            'to_account_id' => $toAccount->user_id, // Use user_id for consistency
                            'plaid_from_account_id' => $fromAccount->id, // Store plaid account ID separately
                            'plaid_to_account_id' => $toAccount->id, // Store plaid account ID separately
                            'from_user_id' => $fromAccount->user_id,
                            'to_user_id' => $toAccount->user_id,
                            'platform' => 'lending_platform',
                            'transfer_type' => 'ach_debit_test_token',
                            'bank_account_token' => $bankAccountToken
                        ],
                        'confirm' => true,
                        'return_url' => config('app.url') . '/transfer/return',
                    ]);
                    $paymentMethod = (object) ['id' => $bankAccountToken]; // Mock for consistency
                } else {
                    // Real Plaid Processor token - create payment method
                    $paymentMethod = $stripe->paymentMethods->create([
                        'type' => 'us_bank_account',
                        'us_bank_account' => [
                            'account_holder_type' => 'individual',
                        ],
                        'billing_details' => [
                            'name' => $billingName,
                            'email' => $billingEmail,
                        ],
                    ]);

                    $paymentIntent = $stripe->paymentIntents->create([
                        'amount' => $amountInCents,
                        'currency' => 'usd',
                        'payment_method_types' => ['us_bank_account'],
                        'description' => $request->description ?? 'ACH Transfer via Lending Platform',
                        'metadata' => [
                            'from_account_id' => $fromAccount->user_id, // Use user_id for consistency
                            'to_account_id' => $toAccount->user_id, // Use user_id for consistency
                            'plaid_from_account_id' => $fromAccount->id, // Store plaid account ID separately
                            'plaid_to_account_id' => $toAccount->id, // Store plaid account ID separately
                            'from_user_id' => $fromAccount->user_id,
                            'to_user_id' => $toAccount->user_id,
                            'platform' => 'lending_platform',
                            'transfer_type' => 'ach_debit_processor',
                            'bank_account_token' => $bankAccountToken
                        ],
                        'setup_future_usage' => 'off_session',
                        'confirm' => true,
                        'return_url' => config('app.url') . '/transfer/return',
                        'payment_method' => $paymentMethod->id,
                    ]);
                }
            } else {
                // Method 2: Using Auth API bank details (fallback)
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

                $paymentIntent = $stripe->paymentIntents->create([
                    'amount' => $amountInCents,
                    'currency' => 'usd',
                    'payment_method' => $paymentMethod->id,
                    'payment_method_types' => ['us_bank_account'],
                    'description' => $request->description ?? 'ACH Transfer via Lending Platform',
                    'metadata' => [
                        'from_account_id' => $fromAccount->user_id, // Use user_id for consistency
                        'to_account_id' => $toAccount->user_id, // Use user_id for consistency
                        'plaid_from_account_id' => $fromAccount->id, // Store plaid account ID separately
                        'plaid_to_account_id' => $toAccount->id, // Store plaid account ID separately
                        'from_user_id' => $fromAccount->user_id,
                        'to_user_id' => $toAccount->user_id,
                        'platform' => 'lending_platform',
                        'transfer_type' => 'ach_debit_auth'
                    ],
                    'confirm' => true,
                    'return_url' => config('app.url') . '/transfer/return',
                ]);
            }

            // Save transfer record to database
            $transaction = \App\Models\Transaction::create([
                'from_account_id' => $fromAccount->user_id, // Use user_id, not plaid_account.id
                'to_account_id' => $toAccount->user_id, // Use user_id, not plaid_account.id
                'amount' => $request->amount,
                'description' => $request->description ?? 'ACH Transfer via Lending Platform',
                'stripe_payment_intent_id' => $paymentIntent->id,
                'status' => $paymentIntent->status,
                'network' => 'ach',
                'metadata' => [
                    'method_used' => $useProcessorMethod ? 'plaid_processor' : 'plaid_auth_fallback',
                    'stripe_payment_intent' => $paymentIntent->toArray(),
                    'bank_account_token' => $bankAccountToken ?? null,
                    'payment_method_id' => $paymentMethod->id,
                    'plaid_from_account_id' => $fromAccount->id, // Store plaid account IDs in metadata
                    'plaid_to_account_id' => $toAccount->id, // Store plaid account IDs in metadata
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
                    ],
                    'bank_details' => $useProcessorMethod ? null : [
                        'routing_number' => substr($bankAccountDetails['routing_number'], 0, 4) . '****',
                        'account_number' => '****' . substr($bankAccountDetails['account_number'], -4)
                    ]
                ]
            ]);

            Log::info('Real ACH Transfer Created Successfully', [
                'method' => $useProcessorMethod ? 'Plaid Processor' : 'Plaid Auth Fallback',
                'payment_intent_id' => $paymentIntent->id,
                'transaction_id' => $transaction->id,
                'amount' => $request->amount,
                'status' => $paymentIntent->status
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
                    'description' => $request->description ?? 'ACH Transfer via Lending Platform'
                ],
                'transaction_id' => $transaction->id,
                'message' => $useProcessorMethod
                    ? 'Real ACH transfer initiated via Plaid Processor'
                    : 'Real ACH transfer initiated via Plaid Auth (fallback)',
                'method_used' => $useProcessorMethod ? 'plaid_processor' : 'plaid_auth_fallback',
                'estimated_completion' => 'ACH transfers typically complete in 3-5 business days',
                'next_action' => $paymentIntent->next_action,
                'client_secret' => $paymentIntent->client_secret
            ]);

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
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            // Determine which service failed based on error message
            $failedService = $this->determineFailedService($e);

            // Check if this is a service failure that should be queued
            if ($this->shouldQueueTransaction($e)) {
                try {
                    Log::info('Service failure detected, queueing transaction for retry', [
                        'failed_service' => $failedService,
                        'error' => $e->getMessage()
                    ]);

                    $queuedTransaction = QueuedTransaction::create([
                        'from_account_id' => $request->from_account_id,
                        'to_account_id' => $request->to_account_id,
                        'amount' => $request->amount,
                        'description' => $request->description,
                        'failed_service' => $failedService,
                        'status' => 'queued',
                        'retry_count' => 0,
                        'max_retries' => 3,
                        'original_request_data' => $request->all(),
                        'failure_details' => [
                            'error_message' => $e->getMessage(),
                            'error_code' => $e->getCode(),
                            'failed_at' => now()->toISOString(),
                            'error_type' => get_class($e)
                        ],
                        'failed_at' => now(),
                        'next_retry_at' => now()->addMinutes(5) // First retry in 5 minutes
                    ]);

                    return response()->json([
                        'success' => false,
                        'queued' => true,
                        'message' => 'Service temporarily unavailable. Your transaction has been queued and will be processed when the service is restored.',
                        'queue_id' => $queuedTransaction->id,
                        'failed_service' => $failedService,
                        'retry_at' => $queuedTransaction->next_retry_at->toISOString()
                    ], 202); // 202 Accepted - request received but not yet processed

                } catch (\Exception $queueError) {
                    Log::error('Failed to queue transaction', [
                        'original_error' => $e->getMessage(),
                        'queue_error' => $queueError->getMessage()
                    ]);

                    // Fall through to regular error response
                }
            }

            return response()->json([
                'error' => 'Internal server error',
                'message' => 'Failed to create ACH transfer: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get bank account details (routing/account numbers) from Plaid Auth API or use test data
     */
    private function getBankAccountDetails(PlaidAccount $plaidAccount): ?array
    {
        try {
            Log::info('Getting bank account details via Plaid Auth API', [
                'account_id' => $plaidAccount->plaid_account_id,
                'environment' => $this->environment
            ]);

            // In sandbox environment, use Stripe's test bank account details if Plaid fails
            if ($this->environment === 'sandbox' && config('app.env') !== 'production') {
                Log::info('Using Stripe test bank account details for sandbox testing');
                return [
                    'routing_number' => '110000000', // Stripe test routing number
                    'account_number' => '000123456789', // Stripe test account number
                    'account_type' => 'checking'
                ];
            }

            // Get Auth data from Plaid which includes routing and account numbers
            $response = Http::timeout(30)->withHeaders([
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/auth/get", [
                'client_id' => $this->clientId,
                'secret' => $this->secret,
                'access_token' => $plaidAccount->access_token,
            ]);

            if (!$response->successful()) {
                Log::error('Plaid Auth Get Failed - falling back to test data for sandbox', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'account_id' => $plaidAccount->plaid_account_id,
                    'environment' => $this->environment
                ]);

                // Fallback to test data for sandbox testing
                if ($this->environment === 'sandbox') {
                    Log::info('Using Stripe test bank account details as fallback');
                    return [
                        'routing_number' => '110000000', // Stripe test routing number
                        'account_number' => '000123456789', // Stripe test account number
                        'account_type' => 'checking'
                    ];
                }

                return null;
            }

            $authData = $response->json();

            // Find the specific account in the auth response
            foreach ($authData['accounts'] as $account) {
                if ($account['account_id'] === $plaidAccount->plaid_account_id) {
                    // Find matching ACH numbers
                    foreach ($authData['numbers']['ach'] as $achNumber) {
                        if ($achNumber['account_id'] === $plaidAccount->plaid_account_id) {
                            return [
                                'routing_number' => $achNumber['routing'],
                                'account_number' => $achNumber['account'],
                                'account_type' => $account['subtype'] ?? 'checking'
                            ];
                        }
                    }
                }
            }

            Log::error('Account not found in Auth response - using test data for sandbox', [
                'target_account_id' => $plaidAccount->plaid_account_id,
                'available_accounts' => array_column($authData['accounts'] ?? [], 'account_id'),
                'environment' => $this->environment
            ]);

            // Fallback to test data for sandbox testing
            if ($this->environment === 'sandbox') {
                Log::info('Account not found - using Stripe test bank account details as fallback');
                return [
                    'routing_number' => '110000000', // Stripe test routing number
                    'account_number' => '000123456789', // Stripe test account number
                    'account_type' => 'checking'
                ];
            }

            return null;

        } catch (\Exception $e) {
            Log::error('Failed to get bank account details from Plaid - using test data for sandbox', [
                'error' => $e->getMessage(),
                'account_id' => $plaidAccount->plaid_account_id,
                'environment' => $this->environment
            ]);

            // Fallback to test data for sandbox testing
            if ($this->environment === 'sandbox' && config('app.env') !== 'production') {
                Log::info('Exception occurred - using Stripe test bank account details as fallback');
                return [
                    'routing_number' => '110000000', // Stripe test routing number
                    'account_number' => '000123456789', // Stripe test account number
                    'account_type' => 'checking'
                ];
            }

            return null;
        }
    }

    /**
     * Create Stripe bank account token via Plaid Processor API or use test token for sandbox
     */
    private function createStripeBankAccountToken(PlaidAccount $plaidAccount): ?string
    {
        try {
            // Check if Plaid service is enabled for this operation
            if (!Setting::isServiceEnabled('plaid')) {
                Log::info('Plaid service disabled - cannot create bank account token');
                return null;
            }

            Log::info('Creating Stripe bank account token via Plaid Processor', [
                'account_id' => $plaidAccount->plaid_account_id,
                'has_access_token' => !empty($plaidAccount->access_token),
                'environment' => $this->environment
            ]);

            // If we're in sandbox and testing, check if we should use Stripe's test token
            if ($this->environment === 'sandbox' && config('app.env') !== 'production') {
                // Use Stripe's test token for successful payments in sandbox
                Log::info('Using Stripe test bank account token for sandbox testing');
                return 'pm_usBankAccount_success';
            }

            // Use Plaid's Stripe Processor API to create a bank account token
            $requestData = [
                'access_token' => $plaidAccount->access_token,
                'account_id' => $plaidAccount->plaid_account_id,
            ];

            Log::info('Plaid Processor API Request', [
                'url' => "{$this->baseUrl}/processor/stripe/bank_account_token/create",
                'client_id' => substr($this->clientId, 0, 10) . '...',
                'has_secret' => !empty($this->secret),
                'account_id' => $plaidAccount->plaid_account_id
            ]);

            $response = Http::timeout(30)->withHeaders([
                'Content-Type' => 'application/json',
                'PLAID-CLIENT-ID' => $this->clientId,
                'PLAID-SECRET' => $this->secret,
                'Plaid-Version' => '2020-09-14',
            ])->post("{$this->baseUrl}/processor/stripe/bank_account_token/create", $requestData);

            Log::info('Plaid Processor API Response', [
                'status' => $response->status(),
                'successful' => $response->successful(),
                'response_body' => $response->body()
            ]);

            if (!$response->successful()) {
                $responseData = $response->json();
                $errorCode = $responseData['error_code'] ?? 'UNKNOWN';
                $errorMessage = $responseData['error_message'] ?? 'Unknown error';

                // Check if it's a known limitation (sandbox keys without Stripe integration)
                if ($errorCode === 'INVALID_PRODUCT' && str_contains($errorMessage, 'Stripe integration')) {
                    Log::warning('Plaid Stripe Processor not available - using Stripe test token for sandbox', [
                        'error_code' => $errorCode,
                        'error_message' => $errorMessage,
                        'account_id' => $plaidAccount->plaid_account_id
                    ]);

                    // Return Stripe's test token for sandbox testing
                    if ($this->environment === 'sandbox') {
                        return 'pm_usBankAccount_success';
                    }

                    return null; // This will trigger fallback to Auth API
                }

                Log::error('Plaid Stripe Processor Token Creation Failed', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'error_code' => $errorCode,
                    'error_message' => $errorMessage,
                    'account_id' => $plaidAccount->plaid_account_id,
                    'request_data' => $requestData
                ]);
                return null;
            }

            $tokenData = $response->json();

            if (isset($tokenData['stripe_bank_account_token'])) {
                Log::info('Stripe bank account token created successfully', [
                    'token' => substr($tokenData['stripe_bank_account_token'], 0, 10) . '...',
                    'account_id' => $plaidAccount->plaid_account_id,
                    'request_id' => $tokenData['request_id'] ?? null
                ]);

                return $tokenData['stripe_bank_account_token'];
            }

            Log::error('No bank account token in Plaid response', [
                'response' => $tokenData,
                'account_id' => $plaidAccount->plaid_account_id
            ]);
            return null;

        } catch (\Exception $e) {
            Log::error('Failed to create Stripe bank account token via Plaid', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'account_id' => $plaidAccount->plaid_account_id
            ]);

            // Fallback to Stripe test token in sandbox for testing
            if ($this->environment === 'sandbox' && config('app.env') !== 'production') {
                Log::info('Exception occurred - falling back to Stripe test token for sandbox testing');
                return 'pm_usBankAccount_success';
            }

            return null;
        }
    }

    /**
     * Determine which service failed based on the exception
     */
    private function determineFailedService(\Exception $e): string
    {
        $message = strtolower($e->getMessage());
        $className = get_class($e);

        // Check for simulated service failures first
        if (str_contains($message, 'plaid service temporarily unavailable') ||
            str_contains($message, 'plaid service manually disabled')) {
            return 'plaid';
        }

        if (str_contains($message, 'stripe service temporarily unavailable') ||
            str_contains($message, 'stripe service manually disabled')) {
            return 'stripe';
        }

        // Check for Stripe-related errors
        if (str_contains($className, 'Stripe') ||
            str_contains($message, 'stripe') ||
            str_contains($message, 'payment method') ||
            str_contains($message, 'payment intent') ||
            str_contains($message, 'card')) {
            return 'stripe';
        }

        // Check for Plaid-related errors
        if (str_contains($message, 'plaid') ||
            str_contains($message, 'access_token') ||
            str_contains($message, 'item') ||
            str_contains($message, 'account_id') ||
            str_contains($message, 'institution')) {
            return 'plaid';
        }

        // Check for network/timeout errors that could affect both
        if (str_contains($message, 'timeout') ||
            str_contains($message, 'connection') ||
            str_contains($message, 'network') ||
            str_contains($message, 'curl')) {
            return 'both';
        }

        // Default to 'both' if we can't determine
        return 'both';
    }

    /**
     * Determine if transaction should be queued based on error type
     */
    private function shouldQueueTransaction(\Exception $e): bool
    {
        $message = strtolower($e->getMessage());
        $code = $e->getCode();

        // Always queue simulated service failures (for testing)
        if (str_contains($message, 'simulated for testing') ||
            str_contains($message, 'manually disabled')) {
            return true;
        }

        // Queue for network/timeout errors
        if (str_contains($message, 'timeout') ||
            str_contains($message, 'connection') ||
            str_contains($message, 'network') ||
            str_contains($message, 'curl') ||
            str_contains($message, 'service unavailable') ||
            str_contains($message, 'temporarily unavailable') ||
            str_contains($message, 'rate limit') ||
            str_contains($message, '500') ||
            str_contains($message, '502') ||
            str_contains($message, '503') ||
            str_contains($message, '504') ||
            $code === 503) {
            return true;
        }

        // Queue for Plaid service errors
        if (str_contains($message, 'plaid_error') ||
            str_contains($message, 'institution_error') ||
            str_contains($message, 'item_login_required')) {
            return true;
        }

        // Queue for Stripe service errors
        if (str_contains($message, 'api_connection_error') ||
            str_contains($message, 'api_error')) {
            return true;
        }

        // Don't queue validation errors or permanent failures
        if (str_contains($message, 'invalid') ||
            str_contains($message, 'declined') ||
            str_contains($message, 'insufficient') ||
            str_contains($message, 'forbidden') ||
            str_contains($message, 'unauthorized') ||
            str_contains($message, 'not found')) {
            return false;
        }

        // Default to not queueing for unknown errors
        return false;
    }
}
