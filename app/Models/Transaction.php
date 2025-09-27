<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'from_account_id',
        'to_account_id',
        'amount',
        'currency',
        'status',
        'stripe_payment_intent_id',
        'plaid_transfer_id',
        'description',
        'transaction_type',
        'network',
        'metadata'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'array'
    ];

    public function fromAccount(): BelongsTo
    {
        return $this->belongsTo(LendingUser::class, 'from_account_id');
    }

    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(LendingUser::class, 'to_account_id');
    }

    public function scopeHighValue($query)
    {
        return $query->where('amount', '>=', 10000);
    }

    public function scopeSuccessful($query)
    {
        return $query->where('status', 'succeeded');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function getFormattedAmountAttribute(): string
    {
        return '$' . number_format($this->amount, 2);
    }

    public function isHighValue(): bool
    {
        return $this->amount >= 10000;
    }
}
