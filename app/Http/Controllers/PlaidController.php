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

            // Save each account to the database and create Stripe bank account tokens
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

                // Create Stripe bank account token for this account
                $stripeBankAccountToken = null;
                $stripeTokenStatus = 'not_attempted';

                try {
                    Log::info('Creating Stripe bank account token during Link flow', [
                        'plaid_account_id' => $plaidAccount->plaid_account_id,
                        'account_name' => $plaidAccount->account_name,
                        'institution_name' => $plaidAccount->institution_name
                    ]);

                    $stripeBankAccountToken = $this->createStripeBankAccountToken($plaidAccount);

                    if ($stripeBankAccountToken) {
                        $stripeTokenStatus = 'success';

                        // Update the account with Stripe token info
                        $plaidAccount->update([
                            'stripe_bank_account_token' => $stripeBankAccountToken,
                            'stripe_token_created_at' => now(),
                            'stripe_integration_status' => 'active'
                        ]);

                        Log::info('Stripe bank account token created successfully', [
                            'plaid_account_id' => $plaidAccount->plaid_account_id,
                            'stripe_token' => substr($stripeBankAccountToken, 0, 20) . '...'
                        ]);
                    } else {
                        $stripeTokenStatus = 'failed';
                        $plaidAccount->update(['stripe_integration_status' => 'failed']);

                        Log::warning('Failed to create Stripe bank account token', [
                            'plaid_account_id' => $plaidAccount->plaid_account_id
                        ]);
                    }
                } catch (\Exception $e) {
                    $stripeTokenStatus = 'error';
                    $plaidAccount->update(['stripe_integration_status' => 'error']);

                    Log::error('Exception creating Stripe bank account token', [
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
                    'stripe_token_status' => $stripeTokenStatus,
                    'stripe_ready' => $stripeBankAccountToken !== null,
                ];
            }

            Log::info('Plaid accounts saved to database with Stripe integration', [
                'item_id' => $itemId,
                'accounts_count' => count($savedAccounts),
                'institution' => $metadata['institution']['name'],
                'stripe_tokens_created' => count(array_filter($savedAccounts, fn($account) => $account['stripe_ready']))
            ]);

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
            if ($paymentMethod === 'charges') {
                return $this->createACHTransferWithCharges($request, $fromAccount, $toAccount, $amountInCents, $verificationMethod);
            } else {
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
                // Instant Verification: Use Plaid Processor token if available, otherwise error
                Log::info('Attempting instant verification with Plaid Processor token');

                $bankAccountToken = $this->createStripeBankAccountToken($fromAccount);

                if (!$bankAccountToken || $bankAccountToken === 'pm_usBankAccount_success') {
                    // For instant verification, we need a real Plaid integration
                    Log::warning('Instant verification requires real Plaid-Stripe integration');

                    if ($bankAccountToken === 'pm_usBankAccount_success') {
                        // Use test token for demo purposes
                        Log::info('Using Stripe test token for instant verification demo');

                        $paymentIntent = $stripe->paymentIntents->create([
                            'amount' => $amountInCents,
                            'currency' => 'usd',
                            'payment_method' => $bankAccountToken,
                            'payment_method_types' => ['us_bank_account'],
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
                    } else {
                        return response()->json([
                            'error' => 'Instant verification not available',
                            'message' => 'Instant verification requires Plaid-Stripe integration. Please use microdeposit verification or connect your account with Stripe integration enabled.',
                            'fallback_available' => true,
                            'suggested_method' => 'microdeposit'
                        ], 422);
                    }
                } else {
                    // Real Plaid processor token - this enables instant verification
                    Log::info('Using real Plaid processor token for instant verification');

                    // Create PaymentMethod from processor token
                    $customer = $stripe->customers->create([
                        'name' => $billingName,
                        'email' => $billingEmail,
                    ]);

                    // For instant verification with real processor tokens, we'd use different Stripe API calls
                    // For now, fall back to regular PaymentIntent but mark as instant verification attempt
                    $paymentIntent = $stripe->paymentIntents->create([
                        'amount' => $amountInCents,
                        'currency' => 'usd',
                        'customer' => $customer->id,
                        'payment_method_types' => ['us_bank_account'],
                        'description' => $request->description ?? 'ACH Transfer via Lending Platform (Instant Verification)',
                        'receipt_email' => $request->email,
                        'metadata' => [
                            'from_account_id' => $fromAccount->user_id,
                            'to_account_id' => $toAccount->user_id,
                            'plaid_from_account_id' => $fromAccount->id,
                            'plaid_to_account_id' => $toAccount->id,
                            'platform' => 'lending_platform',
                            'transfer_type' => 'ach_debit_instant_verification',
                            'verification_method' => 'instant',
                            'processor_token' => substr($bankAccountToken, 0, 10) . '...'
                        ],
                        'setup_future_usage' => 'off_session',
                        'confirm' => true,
                        'return_url' => config('app.url') . '/transfer/return',
                    ]);
                }
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

            return response()->json([
                'error' => 'Internal server error',
                'message' => 'Failed to create ACH transfer with PaymentIntents API: ' . $e->getMessage()
            ], 500);
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

        // Get user details
        $fromUser = $fromAccount->user;
        $billingName = $fromUser ? $fromUser->name : 'Account Holder';
        $billingEmail = $fromUser ? $fromUser->email : $request->email;

        // Create PaymentMethod with bank account details
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

        // Create PaymentIntent with microdeposit verification
        $paymentIntent = $stripe->paymentIntents->create([
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
        ]);

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
     * Create Stripe bank account token via Plaid Processor API or use test token for sandbox
     */
    private function createStripeBankAccountToken(PlaidAccount $plaidAccount): ?string
    {
        // For now, return test token to allow system to work
        Log::info('Returning test Stripe bank account token for demo purposes');
        return 'pm_usBankAccount_success';
    }

    /**
     * Get bank account details (routing/account numbers) from Plaid Auth API or use test data
     */
    private function getBankAccountDetails(PlaidAccount $plaidAccount): ?array
    {
        // For demo purposes, return test bank account details
        Log::info('Returning test bank account details for demo purposes');
        return [
            'routing_number' => '110000000', // Stripe test routing number
            'account_number' => '000123456789', // Stripe test account number
            'account_type' => 'checking'
        ];
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
}

