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
        'is_active',
        'stripe_bank_account_token',
        'stripe_token_created_at',
        'stripe_integration_status',
        'connection_status',
    ];

    protected $casts = [
        'metadata' => 'array',
        'available_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'is_active' => 'boolean',
        'stripe_token_created_at' => 'datetime',
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
     * Check if account has a valid Stripe bank account token
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
