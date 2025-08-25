<?php

namespace Rodrigolopespt\SibsMbwayAP\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Rodrigolopespt\SibsMbwayAP\Models\AuthorizedPayment;

/**
 * Event fired when a new authorization is created
 */
class AuthorizationCreated
{
    use Dispatchable, SerializesModels;

    public AuthorizedPayment $authorization;

    public function __construct(AuthorizedPayment $authorization)
    {
        $this->authorization = $authorization;
    }
}
