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
            $table->string('stripe_bank_account_token')->nullable()->after('metadata');
            $table->timestamp('stripe_token_created_at')->nullable()->after('stripe_bank_account_token');
            $table->string('stripe_integration_status')->default('pending')->after('stripe_token_created_at'); // pending, active, failed, error
            $table->string('connection_status')->default('connected')->after('stripe_integration_status'); // connected, expired, error
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plaid_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'stripe_bank_account_token',
                'stripe_token_created_at',
                'stripe_integration_status',
                'connection_status'
            ]);
        });
    }
};
