<?php

namespace Rodrigolopespt\SibsMbwayAP\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * SIBS MBWay Charge Model
 *
 * Represents a charge against an authorized payment
 *
 * @property int $id
 * @property int $authorized_payment_id FK to authorized payment
 * @property string|null $transaction_id SIBS transaction ID (set when processed)
 * @property float $amount Amount charged
 * @property string $currency Currency code (EUR)
 * @property string $status Charge status (pending, success, failed, refunded, partially_refunded)
 * @property Carbon $charge_date When charge was attempted
 * @property string $description Description of the charge
 * @property string|null $merchant_reference Internal merchant reference
 * @property string|null $error_message Error message if failed
 * @property int $retry_count Number of retry attempts
 * @property Carbon|null $last_retry_at Last retry timestamp
 * @property float $refunded_amount Total refunded amount
 * @property Carbon|null $refunded_at When refund was processed
 * @property array|null $metadata Additional metadata
 * @property array|null $sibs_response Full SIBS response
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Charge extends Model
{
    protected $table = 'sibs_charges';

    protected $fillable = [
        'authorized_payment_id',
        'transaction_id',
        'amount',
        'currency',
        'status',
        'charge_date',
        'description',
        'merchant_reference',
        'error_message',
        'retry_count',
        'last_retry_at',
        'refunded_amount',
        'refunded_at',
        'metadata',
        'sibs_response',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'charge_date' => 'datetime',
        'last_retry_at' => 'datetime',
        'refunded_amount' => 'decimal:2',
        'refunded_at' => 'datetime',
        'metadata' => 'array',
        'sibs_response' => 'array',
    ];

    protected $attributes = [
        'currency' => 'EUR',
        'status' => 'pending',
        'retry_count' => 0,
        'refunded_amount' => 0,
    ];

    /**
     * Charge status constants
     */
    public const STATUS_PENDING = 'pending';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REFUNDED = 'refunded';

    public const STATUS_PARTIALLY_REFUNDED = 'partially_refunded';

    /**
     * Get the authorized payment this charge belongs to
     */
    public function authorizedPayment(): BelongsTo
    {
        return $this->belongsTo(AuthorizedPayment::class, 'authorized_payment_id');
    }

    /**
     * Get all transactions for this charge
     */
    public function transactions(): MorphMany
    {
        return $this->morphMany(Transaction::class, 'transactionable');
    }

    /**
     * Check if charge was successful
     */
    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    /**
     * Check if charge failed
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Check if charge is pending
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if charge is refunded (fully or partially)
     */
    public function isRefunded(): bool
    {
        return in_array($this->status, [self::STATUS_REFUNDED, self::STATUS_PARTIALLY_REFUNDED]);
    }

    /**
     * Check if charge is fully refunded
     */
    public function isFullyRefunded(): bool
    {
        return $this->status === self::STATUS_REFUNDED;
    }

    /**
     * Check if charge is partially refunded
     */
    public function isPartiallyRefunded(): bool
    {
        return $this->status === self::STATUS_PARTIALLY_REFUNDED;
    }

    /**
     * Check if charge can be refunded
     */
    public function canBeRefunded(): bool
    {
        return $this->isSuccessful() && $this->refunded_amount < $this->amount;
    }

    /**
     * Get remaining amount that can be refunded
     */
    public function getRemainingRefundableAmount(): float
    {
        if (! $this->canBeRefunded()) {
            return 0;
        }

        return $this->amount - $this->refunded_amount;
    }

    /**
     * Check if can be retried
     */
    public function canBeRetried(): bool
    {
        $maxRetries = config('sibs-mbway-authorized-payments.authorized_payments.retry_attempts', 3);

        return $this->isFailed() &&
               $this->retry_count < $maxRetries &&
               $this->authorizedPayment !== null &&
               $this->authorizedPayment->isActive();
    }

    /**
     * Mark charge as successful
     */
    public function markAsSuccessful(string $transactionId, ?array $sibsResponse = null): self
    {
        $this->update([
            'transaction_id' => $transactionId,
            'status' => self::STATUS_SUCCESS,
            'error_message' => null,
            'sibs_response' => $sibsResponse,
        ]);

        return $this;
    }

    /**
     * Mark charge as failed
     */
    public function markAsFailed(string $errorMessage, ?array $sibsResponse = null): self
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'sibs_response' => $sibsResponse,
        ]);

        return $this;
    }

    /**
     * Record retry attempt
     */
    public function recordRetryAttempt(): self
    {
        $this->increment('retry_count');
        $this->update(['last_retry_at' => now()]);

        return $this;
    }

    /**
     * Process refund (full or partial)
     */
    public function processRefund(float $amount): self
    {
        $newRefundedAmount = $this->refunded_amount + $amount;

        $status = ($newRefundedAmount >= $this->amount)
            ? self::STATUS_REFUNDED
            : self::STATUS_PARTIALLY_REFUNDED;

        $this->update([
            'refunded_amount' => $newRefundedAmount,
            'refunded_at' => now(),
            'status' => $status,
        ]);

        return $this;
    }

    /**
     * Scope for successful charges
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', self::STATUS_SUCCESS);
    }

    /**
     * Scope for failed charges
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope for pending charges
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for charges that can be retried
     */
    public function scopeRetryable($query)
    {
        $maxRetries = config('sibs-mbway-authorized-payments.authorized_payments.retry_attempts', 3);
        $retryDelay = config('sibs-mbway-authorized-payments.authorized_payments.retry_delay_minutes', 60);

        return $query->where('status', self::STATUS_FAILED)
            ->where('retry_count', '<', $maxRetries)
            ->where(function ($q) use ($retryDelay) {
                $q->whereNull('last_retry_at')
                    ->orWhere('last_retry_at', '<=', now()->subMinutes($retryDelay));
            });
    }

    /**
     * Scope for refundable charges
     */
    public function scopeRefundable($query)
    {
        return $query->where('status', self::STATUS_SUCCESS)
            ->whereColumn('refunded_amount', '<', 'amount');
    }

    /**
     * Scope for charges within date range
     */
    public function scopeBetweenDates($query, Carbon $startDate, Carbon $endDate)
    {
        return $query->whereBetween('charge_date', [$startDate, $endDate]);
    }

    /**
     * Scope for charges by merchant reference
     */
    public function scopeByMerchantReference($query, string $reference)
    {
        return $query->where('merchant_reference', $reference);
    }

    /**
     * Get formatted amount for display
     */
    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount, 2).' '.$this->currency;
    }

    /**
     * Get days since charge
     */
    public function getDaysSinceChargeAttribute(): int
    {
        return (int) $this->charge_date->diffInDays(now());
    }
}
