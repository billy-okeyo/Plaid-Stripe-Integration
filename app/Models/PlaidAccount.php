<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\AsCollection;

class PlaidAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'plaid_account_id',
        'plaid_item_id',
        'access_token',
        'account_name',
        'account_type',
        'account_subtype',
        'institution_name',
        'institution_id',
        'available_balance',
        'current_balance',
        'currency_code',
        'metadata',
        'connection_status',
        'last_sync_at',
        'is_active',
        'stripe_bank_account_token', // Deprecated - use stripe_customer_id + stripe_bank_account_id instead
        'stripe_customer_id',
        'stripe_bank_account_id',
        'stripe_bank_account_details',
        'stripe_bank_account_created_at',
        'stripe_token_created_at',
        'stripe_integration_status'
    ];

    protected $casts = [
        'metadata' => 'array',
        'stripe_bank_account_details' => 'array',
        'last_sync_at' => 'datetime',
        'stripe_token_created_at' => 'datetime',
        'stripe_bank_account_created_at' => 'datetime',
        'is_active' => 'boolean',
        'available_balance' => 'decimal:2',
        'current_balance' => 'decimal:2'
    ];

    /**
     * Get the formatted available balance
     */
    public function getFormattedAvailableBalanceAttribute(): string
    {
        return '$' . number_format($this->available_balance, 2);
    }

    /**
     * Get the formatted current balance
     */
    public function getFormattedCurrentBalanceAttribute(): string
    {
        return '$' . number_format($this->current_balance, 2);
    }

    /**
     * Scope to get active accounts only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get accounts by user
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get accounts by institution
     */
    public function scopeByInstitution($query, $institutionId)
    {
        return $query->where('institution_id', $institutionId);
    }

    /**
     * Get the user that owns this account
     */
    public function user()
    {
        return $this->belongsTo(\App\Models\LendingUser::class, 'user_id');
    }

    /**
     * Check if account has persistent Stripe customer and bank account
     * This is the recommended approach for long-term storage
     */
    public function hasStripeCustomerAccount(): bool
    {
        return !empty($this->stripe_customer_id) &&
               !empty($this->stripe_bank_account_id) &&
               $this->stripe_integration_status === 'active';
    }

    /**
     * Check if account can generate Stripe bank account tokens
     * Note: Stripe tokens (btok_*) are short-lived and should be generated fresh each time
     */
    public function canGenerateStripeTokens(): bool
    {
        return !empty($this->access_token) &&
               !empty($this->plaid_account_id);
    }

    /**
     * Get the preferred Stripe payment method (customer + bank account vs fresh token)
     * Returns 'customer_account' if persistent objects exist, 'fresh_token' otherwise
     */
    public function getStripePaymentMethod(): string
    {
        if ($this->hasStripeCustomerAccount()) {
            return 'customer_account'; // Use existing customer + bank account
        } elseif ($this->canGenerateStripeTokens()) {
            return 'fresh_token'; // Generate fresh token and create customer + bank account
        } else {
            return 'unavailable';
        }
    }

    /**
     * Check if stored Stripe token exists (but may be expired)
     * @deprecated Use hasStripeCustomerAccount() instead - stored tokens expire quickly
     */
    public function hasValidStripeToken(): bool
    {
        return !empty($this->stripe_bank_account_token) &&
               $this->stripe_integration_status === 'active';
    }

    /**
     * Check if account connection is still valid
     */
    public function isConnectionActive(): bool
    {
        return $this->connection_status === 'connected' && $this->is_active;
    }

    /**
     * Get Stripe integration status with emoji
     */
    public function getStripeStatusDisplayAttribute(): string
    {
        return match($this->stripe_integration_status) {
            'active' => '✅ Ready',
            'pending' => '⏳ Processing',
            'failed' => '❌ Failed',
            'error' => '⚠️ Error',
            default => '❓ Unknown'
        };
    }

    /**
     * Get connection status with emoji
     */
    public function getConnectionStatusDisplayAttribute(): string
    {
        return match($this->connection_status) {
            'connected' => '🟢 Connected',
            'expired' => '🟡 Expired',
            'error' => '🔴 Error',
            default => '❓ Unknown'
        };
    }
}
