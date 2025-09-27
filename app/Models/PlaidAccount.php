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
    ];

    protected $casts = [
        'metadata' => 'array',
        'available_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'is_active' => 'boolean',
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
}
