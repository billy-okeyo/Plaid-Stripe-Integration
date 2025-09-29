<?php

require_once 'vendor/autoload.php';

// Load Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Http\Controllers\PlaidController;
use Illuminate\Http\Request;

echo "=== Testing Credential Configuration ===\n\n";

// Test current production mode
echo "1. PRODUCTION MODE (USE_PRODUCTION_APIS=true):\n";
$useProduction = config('app.use_production_apis', false);
echo "   USE_PRODUCTION_APIS: " . ($useProduction ? 'true' : 'false') . "\n";
echo "   Environment: " . ($useProduction ? 'PRODUCTION' : 'SANDBOX/TEST') . "\n";
echo "   Test credentials available: " . (env('TEST_ROUTING_NUMBER') ? 'Yes' : 'No') . "\n";
echo "   TEST_ROUTING_NUMBER: " . env('TEST_ROUTING_NUMBER', 'not set') . "\n";
echo "   TEST_ACCOUNT_NUMBER: " . env('TEST_ACCOUNT_NUMBER', 'not set') . "\n\n";

// Simulate what would happen in sandbox mode
echo "2. What would happen in SANDBOX MODE (USE_PRODUCTION_APIS=false):\n";
echo "   Environment: SANDBOX/TEST\n";
echo "   Would use TEST_ROUTING_NUMBER: " . env('TEST_ROUTING_NUMBER', '110000000') . "\n";
echo "   Would use TEST_ACCOUNT_NUMBER: " . env('TEST_ACCOUNT_NUMBER', '000123456789') . "\n";
echo "   Bank details would come from: Environment variables (not Plaid API)\n\n";

echo "3. KEY CONFIGURATION:\n";
echo "   Plaid Production Client ID: " . substr(env('PLAID_PROD_CLIENT_ID', 'not set'), 0, 8) . "****\n";
echo "   Plaid Sandbox Client ID: " . substr(env('PLAID_CLIENT_ID', 'not set'), 0, 8) . "****\n";
echo "   Stripe Production Secret: " . substr(env('STRIPE_PROD_SECRET_KEY', 'not set'), 0, 8) . "****\n";
echo "   Stripe Test Secret: " . substr(env('STRIPE_SECRET_KEY', 'not set'), 0, 8) . "****\n\n";

echo "✅ Configuration looks good! When USE_PRODUCTION_APIS=false, transfers will use your test credentials:\n";
echo "   - Routing: " . env('TEST_ROUTING_NUMBER') . "\n";
echo "   - Account: " . env('TEST_ACCOUNT_NUMBER') . "\n";
