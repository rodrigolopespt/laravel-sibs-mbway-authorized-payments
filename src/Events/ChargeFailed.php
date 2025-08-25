<?php

namespace Rodrigolopespt\SibsMbwayAP\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Rodrigolopespt\SibsMbwayAP\Models\Charge;

/**
 * Event fired when a charge fails
 */
class ChargeFailed
{
    use Dispatchable, SerializesModels;

    public Charge $charge;

    public string $errorMessage;

    public function __construct(Charge $charge, string $errorMessage)
    {
        $this->charge = $charge;
        $this->errorMessage = $errorMessage;
    }
}
