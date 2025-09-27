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
        Schema::create('lending_users', function (Blueprint $table) {
            $table->id();

            // Core user information
            $table->enum('user_type', ['lender', 'borrower']); // Primary role
            $table->string('email')->unique();
            $table->string('business_name');
            $table->string('phone');
            $table->string('country', 2)->default('US');

            // Platform status tracking
            $table->enum('status', ['pending_verification', 'active', 'suspended', 'inactive'])->default('pending_verification');
            $table->enum('kyc_status', ['pending', 'approved', 'rejected', 'requires_info'])->default('pending');

            // Optional detailed information
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->enum('account_type', ['individual', 'business'])->default('individual');

            // Business information (for business accounts)
            $table->string('business_tax_id')->nullable();
            $table->string('business_type')->nullable(); // LLC, Corporation, etc.

            // Address information (required for high-value transfers)
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();

            // Identity verification for individuals
            $table->date('date_of_birth')->nullable();
            $table->string('ssn_last_4')->nullable();

            // Platform capabilities
            $table->boolean('can_process_high_value')->default(false); // >$10k capability
            $table->boolean('is_active')->default(true);

            // Metadata and tracking
            $table->json('verification_data')->nullable(); // Store verification documents/info
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index(['user_type', 'is_active']);
            $table->index(['status', 'kyc_status']);
            $table->index('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lending_users');
    }
};
