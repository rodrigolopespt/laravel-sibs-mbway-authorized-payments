<?php

namespace Rodrigolopespt\SibsMbwayAP\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Rodrigolopespt\SibsMbwayAP\Models\AuthorizedPayment;

/**
 * Event fired when an authorization expires
 */
class AuthorizationExpired
{
    use Dispatchable, SerializesModels;

    public AuthorizedPayment $authorization;

    public function __construct(AuthorizedPayment $authorization)
    {
        $this->authorization = $authorization;
    }
}
