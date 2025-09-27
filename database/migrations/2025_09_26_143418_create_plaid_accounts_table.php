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
        Schema::create('plaid_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('user_id')->nullable(); // In a real app, this would be a foreign key to users table
            $table->string('plaid_account_id')->unique();
            $table->string('plaid_item_id');
            $table->string('access_token');
            $table->string('account_name');
            $table->string('account_type'); // depository, credit, loan, investment
            $table->string('account_subtype'); // checking, savings, credit card, etc.
            $table->string('institution_name');
            $table->string('institution_id');
            $table->decimal('available_balance', 15, 2)->nullable();
            $table->decimal('current_balance', 15, 2)->nullable();
            $table->string('currency_code', 3)->default('USD');
            $table->json('metadata')->nullable(); // Store additional account metadata
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plaid_accounts');
    }
};
