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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();

            // Simple transaction parties (matches our controller)
            $table->foreignId('from_account_id')->constrained('lending_users')->onDelete('cascade');
            $table->foreignId('to_account_id')->constrained('lending_users')->onDelete('cascade');

            // Transaction details
            $table->decimal('amount', 15, 2); // Transaction amount in dollars
            $table->string('currency', 3)->default('USD');
            $table->text('description')->nullable();
            $table->enum('transaction_type', ['transfer', 'fee', 'refund'])->default('transfer');

            // Stripe integration (regular Stripe)
            $table->string('stripe_payment_intent_id')->nullable(); // Stripe Payment Intent ID
            $table->string('stripe_charge_id')->nullable(); // Stripe Charge ID (if applicable)

            // Transaction status
            $table->enum('status', [
                'pending', 'processing', 'succeeded', 'failed',
                'cancelled', 'refunded'
            ])->default('pending');
            $table->text('failure_reason')->nullable();

            // High-value transaction tracking
            $table->boolean('is_high_value')->default(false); // >$10k transactions
            $table->boolean('requires_manual_review')->default(false);

            // Metadata for additional info
            $table->json('metadata')->nullable(); // Store platform fees, ACH info, etc.

            $table->timestamps();

            // Indexes for performance
            $table->index(['from_account_id', 'status']);
            $table->index(['to_account_id', 'status']);
            $table->index(['is_high_value', 'status']);
            $table->index('stripe_payment_intent_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
