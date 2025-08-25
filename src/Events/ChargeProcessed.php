<?php

namespace Rodrigolopespt\SibsMbwayAP\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Rodrigolopespt\SibsMbwayAP\Models\AuthorizedPayment;
use Rodrigolopespt\SibsMbwayAP\Models\Charge;

/**
 * Event fired when a charge is successfully processed
 */
class ChargeProcessed
{
    use Dispatchable, SerializesModels;

    public Charge $charge;

    public AuthorizedPayment $authorization;

    public function __construct(Charge $charge, AuthorizedPayment $authorization)
    {
        $this->charge = $charge;
        $this->authorization = $authorization;
    }
}
