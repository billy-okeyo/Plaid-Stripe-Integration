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
        Schema::create('queued_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('from_account_id');
            $table->unsignedBigInteger('to_account_id');
            $table->decimal('amount', 10, 2);
            $table->string('description')->nullable();
            $table->enum('failed_service', ['plaid', 'stripe', 'both']); // Which service failed
            $table->enum('status', ['queued', 'retrying', 'failed', 'processed'])->default('queued');
            $table->integer('retry_count')->default(0);
            $table->integer('max_retries')->default(3);
            $table->json('original_request_data'); // Store original request for retry
            $table->json('failure_details')->nullable(); // Store error details
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('failed_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('from_account_id')->references('id')->on('plaid_accounts');
            $table->foreign('to_account_id')->references('id')->on('plaid_accounts');

            // Indexes
            $table->index(['status', 'next_retry_at']);
            $table->index('failed_service');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('queued_transactions');
    }
};
