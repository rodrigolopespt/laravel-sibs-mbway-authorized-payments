<?php

namespace Rodrigolopespt\SibsMbwayAP\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * SIBS Transaction Model
 *
 * Generic transaction record for all SIBS API interactions
 *
 * @property int $id
 * @property string $transaction_id SIBS transaction ID
 * @property string|null $merchant_transaction_id Internal merchant transaction ID
 * @property string $type Transaction type (authorization_request, charge, refund, cancellation)
 * @property string $transactionable_type Related model type
 * @property int $transactionable_id Related model ID
 * @property float $amount Transaction amount
 * @property string $currency Currency code (EUR)
 * @property string $status Transaction status (pending, success, failed, cancelled)
 * @property array|null $request_data Original request sent to SIBS
 * @property array|null $response_data Response from SIBS
 * @property string|null $return_code SIBS return code
 * @property string|null $return_message SIBS return message
 * @property Carbon $requested_at When request was made
 * @property Carbon|null $completed_at When response was received
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Transaction extends Model
{
    protected $table = 'sibs_transactions';

    protected $fillable = [
        'transaction_id',
        'merchant_transaction_id',
        'type',
        'transactionable_type',
        'transactionable_id',
        'amount',
        'currency',
        'status',
        'request_data',
        'response_data',
        'return_code',
        'return_message',
        'requested_at',
        'completed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'request_data' => 'array',
        'response_data' => 'array',
        'requested_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $attributes = [
        'currency' => 'EUR',
        'status' => 'pending',
    ];

    /**
     * Transaction type constants
     */
    public const TYPE_AUTHORIZATION_REQUEST = 'authorization_request';

    public const TYPE_CHARGE = 'charge';

    public const TYPE_REFUND = 'refund';

    public const TYPE_CANCELLATION = 'cancellation';

    /**
     * Transaction status constants
     */
    public const STATUS_PENDING = 'pending';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Get the owning transactionable model
     */
    public function transactionable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Check if transaction is successful
     */
    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    /**
     * Check if transaction failed
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Check if transaction is pending
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if transaction was cancelled
     */
    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Mark transaction as successful
     */
    public function markAsSuccessful(array $responseData = [], ?string $returnCode = null, ?string $returnMessage = null): self
    {
        $this->update([
            'status' => self::STATUS_SUCCESS,
            'response_data' => $responseData,
            'return_code' => $returnCode,
            'return_message' => $returnMessage,
            'completed_at' => now(),
        ]);

        return $this;
    }

    /**
     * Mark transaction as failed
     */
    public function markAsFailed(array $responseData = [], ?string $returnCode = null, ?string $returnMessage = null): self
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'response_data' => $responseData,
            'return_code' => $returnCode,
            'return_message' => $returnMessage,
            'completed_at' => now(),
        ]);

        return $this;
    }

    /**
     * Mark transaction as cancelled
     */
    public function markAsCancelled(): self
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'completed_at' => now(),
        ]);

        return $this;
    }

    /**
     * Scope for successful transactions
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', self::STATUS_SUCCESS);
    }

    /**
     * Scope for failed transactions
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope for pending transactions
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope by transaction type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope for transactions within date range
     */
    public function scopeBetweenDates($query, Carbon $startDate, Carbon $endDate)
    {
        return $query->whereBetween('requested_at', [$startDate, $endDate]);
    }

    /**
     * Get processing duration in seconds
     */
    public function getProcessingDurationAttribute(): ?int
    {
        if (! $this->completed_at) {
            return null;
        }

        return (int) $this->requested_at->diffInSeconds($this->completed_at);
    }

    /**
     * Get formatted amount for display
     */
    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount, 2).' '.$this->currency;
    }

    /**
     * Create new transaction record
     */
    public static function createForModel(
        Model $model,
        string $type,
        float $amount,
        array $requestData = [],
        ?string $merchantTransactionId = null
    ): self {
        return self::create([
            'transaction_id' => 'temp_'.uniqid(), // Will be updated with SIBS ID
            'merchant_transaction_id' => $merchantTransactionId,
            'type' => $type,
            'transactionable_type' => get_class($model),
            'transactionable_id' => $model->getKey(),
            'amount' => $amount,
            'request_data' => $requestData,
            'requested_at' => now(),
        ]);
    }

    /**
     * Update with SIBS transaction ID
     */
    public function updateWithSibsId(string $sibsTransactionId): self
    {
        $this->update(['transaction_id' => $sibsTransactionId]);

        return $this;
    }
}
