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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // Setting key (e.g., 'plaid_enabled', 'stripe_enabled')
            $table->text('value'); // Setting value (JSON or string)
            $table->string('type')->default('string'); // Type: string, boolean, json, integer
            $table->string('description')->nullable(); // Human readable description
            $table->timestamps();

            // Index for faster lookups
            $table->index('key');
        });

        // Insert default settings
        \DB::table('settings')->insert([
            [
                'key' => 'plaid_enabled',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Enable/disable Plaid service for testing queue functionality',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'stripe_enabled',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Enable/disable Stripe service for testing queue functionality',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'queue_auto_retry',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Automatically retry queued transactions when services become available',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'max_retry_attempts',
                'value' => '3',
                'type' => 'integer',
                'description' => 'Maximum number of retry attempts for failed transactions',
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
