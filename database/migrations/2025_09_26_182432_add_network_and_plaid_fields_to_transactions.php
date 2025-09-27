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
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('network')->nullable()->after('status'); // ach, wire, etc.
            $table->string('plaid_transfer_id')->nullable()->after('stripe_charge_id'); // Plaid Transfer ID

            // Add index for plaid_transfer_id
            $table->index('plaid_transfer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['plaid_transfer_id']);
            $table->dropColumn(['network', 'plaid_transfer_id']);
        });
    }
};
