<?php

namespace Rodrigolopespt\SibsMbwayAP\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * SIBS MBWay Authorized Payment Model
 *
 * Represents a customer authorization for future automatic charges
 *
 * @property int $id
 * @property string|null $authorization_id SIBS authorization ID (set when approved)
 * @property string $customer_phone Customer phone number (351919999999)
 * @property string $customer_email Customer email
 * @property float $max_amount Maximum authorized amount
 * @property string $currency Currency code (EUR)
 * @property Carbon $validity_date When authorization expires
 * @property string $status Authorization status (pending, active, expired, cancelled)
 * @property string $description Description shown to customer
 * @property string|null $merchant_reference Internal merchant reference
 * @property array|null $metadata Additional metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class AuthorizedPayment extends Model
{
    protected $table = 'sibs_authorized_payments';

    protected $fillable = [
        'authorization_id',
        'customer_phone',
        'customer_email',
        'max_amount',
        'currency',
        'validity_date',
        'status',
        'description',
        'merchant_reference',
        'metadata',
    ];

    protected $casts = [
        'max_amount' => 'decimal:2',
        'validity_date' => 'datetime',
        'metadata' => 'array',
    ];

    protected $attributes = [
        'currency' => 'EUR',
        'status' => 'pending',
    ];

    /**
     * Authorization status constants
     */
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Get all charges for this authorization
     */
    public function charges(): HasMany
    {
        return $this->hasMany(Charge::class, 'authorized_payment_id');
    }

    /**
     * Get all transactions for this authorization
     */
    public function transactions(): MorphMany
    {
        return $this->morphMany(Transaction::class, 'transactionable');
    }

    /**
     * Get successful charges only
     */
    public function successfulCharges(): HasMany
    {
        return $this->charges()->where('status', Charge::STATUS_SUCCESS);
    }

    /**
     * Get pending charges
     */
    public function pendingCharges(): HasMany
    {
        return $this->charges()->where('status', Charge::STATUS_PENDING);
    }

    /**
     * Check if authorization is active
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE && ! $this->isExpired();
    }

    /**
     * Check if authorization is pending approval
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if authorization has expired
     */
    public function isExpired(): bool
    {
        return $this->validity_date->isPast();
    }

    /**
     * Check if authorization is cancelled
     */
    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Get total amount charged so far
     */
    public function getTotalChargedAmount(): float
    {
        return (float) $this->successfulCharges()->sum('amount');
    }

    /**
     * Get remaining amount that can be charged
     */
    public function getRemainingAmount(): float
    {
        return $this->max_amount - $this->getTotalChargedAmount();
    }

    /**
     * Check if can charge specific amount
     */
    public function canChargeAmount(float $amount): bool
    {
        return $this->isActive() && $amount <= $this->getRemainingAmount();
    }

    /**
     * Get charges count
     */
    public function getChargesCount(): int
    {
        return $this->charges()->count();
    }

    /**
     * Get successful charges count
     */
    public function getSuccessfulChargesCount(): int
    {
        return $this->successfulCharges()->count();
    }

    /**
     * Mark authorization as active (after SIBS approval)
     */
    public function markAsActive(string $authorizationId): self
    {
        $this->update([
            'authorization_id' => $authorizationId,
            'status' => self::STATUS_ACTIVE,
        ]);

        return $this;
    }

    /**
     * Mark authorization as cancelled
     */
    public function markAsCancelled(): self
    {
        $this->update(['status' => self::STATUS_CANCELLED]);

        return $this;
    }

    /**
     * Mark authorization as expired
     */
    public function markAsExpired(): self
    {
        $this->update(['status' => self::STATUS_EXPIRED]);

        return $this;
    }

    /**
     * Scope for active authorizations
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where('validity_date', '>', now());
    }

    /**
     * Scope for pending authorizations
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for expiring soon (within specified days)
     */
    public function scopeExpiringSoon($query, int $days = 30)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->whereBetween('validity_date', [now(), now()->addDays($days)]);
    }

    /**
     * Scope for specific customer
     */
    public function scopeForCustomer($query, string $customerPhone)
    {
        return $query->where('customer_phone', $customerPhone);
    }

    /**
     * Format phone number for display (hide middle digits)
     */
    public function getFormattedPhoneAttribute(): string
    {
        $phone = $this->customer_phone;
        if (strlen($phone) > 8) {
            return substr($phone, 0, 6).'***'.substr($phone, -2);
        }

        return $phone;
    }

    /**
     * Get days until expiry
     */
    public function getDaysUntilExpiryAttribute(): int
    {
        return max(0, now()->diffInDays($this->validity_date, false));
    }

    /**
     * Check if authorization expires within specified days
     */
    public function expiresWithin(int $days): bool
    {
        return $this->validity_date->isBefore(now()->addDays($days));
    }
}
