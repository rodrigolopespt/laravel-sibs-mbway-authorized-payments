<?php

namespace Rodrigolopespt\SibsMbwayAP\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Rodrigolopespt\SibsMbwayAP\Models\AuthorizedPayment createAuthorization(array $data)
 * @method static \Rodrigolopespt\SibsMbwayAP\Models\AuthorizedPayment|null getAuthorization(string $authorizationId)
 * @method static bool cancelAuthorization(string $authorizationId)
 * @method static array checkAuthorizationStatus(string $authorizationId)
 * @method static \Illuminate\Support\Collection listActiveAuthorizations(array $filters = [])
 * @method static \Illuminate\Support\Collection listExpiringAuthorizations(int $days = 30)
 * @method static \Rodrigolopespt\SibsMbwayAP\Models\Charge processCharge(\Rodrigolopespt\SibsMbwayAP\Models\AuthorizedPayment $authorization, float $amount, ?string $description = null)
 * @method static bool refundCharge(\Rodrigolopespt\SibsMbwayAP\Models\Charge $charge, ?float $amount = null)
 * @method static array getChargeStatus(string $transactionId)
 * @method static array processBatchCharges(array $charges)
 * @method static \Illuminate\Support\Collection processRecurringCharges()
 * @method static \Illuminate\Support\Collection retryFailedCharges()
 *
 * @see \Rodrigolopespt\SibsMbwayAP\SibsMbwayAPManager
 */
class SibsMbwayAP extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'sibs-mbway-ap';
    }
}
