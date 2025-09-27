<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LendingUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_type',
        'email',
        'business_name',
        'phone',
        'country',
        'status',
        'kyc_status',
        'verification_data'
    ];

    protected $casts = [
        'verification_data' => 'array'
    ];



    public function sentTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'from_account_id');
    }

    public function receivedTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'to_account_id');
    }

    public function plaidAccounts(): HasMany
    {
        return $this->hasMany(PlaidAccount::class, 'user_id');
    }

    public function isLender(): bool
    {
        return $this->user_type === 'lender';
    }

    public function isBorrower(): bool
    {
        return $this->user_type === 'borrower';
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->kyc_status === 'approved';
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeLenders($query)
    {
        return $query->where('user_type', 'lender');
    }

    public function scopeBorrowers($query)
    {
        return $query->where('user_type', 'borrower');
    }
}
