<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('plaid_accounts', function (Blueprint $table) {
            // Replace the temporary token approach with persistent Stripe objects
            $table->string('stripe_customer_id')->nullable()->after('stripe_bank_account_token');
            $table->string('stripe_bank_account_id')->nullable()->after('stripe_customer_id');
            $table->json('stripe_bank_account_details')->nullable()->after('stripe_bank_account_id');
            $table->timestamp('stripe_bank_account_created_at')->nullable()->after('stripe_bank_account_details');

            // Keep the existing token field for backward compatibility but mark it as deprecated
            // We'll phase this out in favor of the persistent approach
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plaid_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'stripe_customer_id',
                'stripe_bank_account_id',
                'stripe_bank_account_details',
                'stripe_bank_account_created_at'
            ]);
        });
    }
};
