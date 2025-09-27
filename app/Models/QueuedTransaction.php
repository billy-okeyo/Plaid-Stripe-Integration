<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class QueuedTransaction extends Model
{
    protected $fillable = [
        'from_account_id',
        'to_account_id',
        'amount',
        'description',
        'failed_service',
        'status',
        'retry_count',
        'max_retries',
        'original_request_data',
        'failure_details',
        'next_retry_at',
        'failed_at',
        'processed_at'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'original_request_data' => 'array',
        'failure_details' => 'array',
        'next_retry_at' => 'datetime',
        'failed_at' => 'datetime',
        'processed_at' => 'datetime'
    ];

    public function fromAccount(): BelongsTo
    {
        return $this->belongsTo(PlaidAccount::class, 'from_account_id');
    }

    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(PlaidAccount::class, 'to_account_id');
    }

    /**
     * Check if transaction can be retried
     */
    public function canRetry(bool $manualRetry = false): bool
    {
        // Must be in queued status and not exceed max retries
        if ($this->status !== 'queued' || $this->retry_count >= $this->max_retries) {
            return false;
        }

        // For manual retries, allow retry regardless of scheduled time
        if ($manualRetry) {
            return true;
        }

        // For automatic retries, respect the scheduled retry time
        return $this->next_retry_at === null || $this->next_retry_at->isPast();
    }

    /**
     * Increment retry count and set next retry time
     */
    public function incrementRetry(): void
    {
        $this->update([
            'retry_count' => $this->retry_count + 1,
            'status' => 'queued',
            'next_retry_at' => now()->addMinutes(pow(2, $this->retry_count)) // Exponential backoff
        ]);
    }

    /**
     * Mark as failed after max retries
     */
    public function markAsFailed(): void
    {
        $this->update([
            'status' => 'failed',
            'next_retry_at' => null
        ]);
    }

    /**
     * Mark as processed successfully
     */
    public function markAsProcessed(): void
    {
        $this->update([
            'status' => 'processed',
            'processed_at' => now(),
            'next_retry_at' => null
        ]);
    }

    /**
     * Get transactions ready for retry
     */
    public static function readyForRetry()
    {
        return static::where('status', 'queued')
            ->where('retry_count', '<', \DB::raw('max_retries'))
            ->where(function ($query) {
                $query->whereNull('next_retry_at')
                      ->orWhere('next_retry_at', '<=', now());
            });
    }
}
