<?php

namespace Rodrigolopespt\SibsMbwayAP\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Rodrigolopespt\SibsMbwayAP\Events\AuthorizationCreated;
use Rodrigolopespt\SibsMbwayAP\Events\ChargeProcessed;
use Rodrigolopespt\SibsMbwayAP\Facades\SibsMbwayAP;
use Rodrigolopespt\SibsMbwayAP\Models\AuthorizedPayment;
use Rodrigolopespt\SibsMbwayAP\Models\Charge;
use Rodrigolopespt\SibsMbwayAP\Tests\TestCase;

class AuthorizedPaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_create_an_authorization_request()
    {
        Event::fake();

        $authorizationData = [
            'customerPhone' => '351919999999',
            'customerEmail' => 'customer@example.com',
            'maxAmount' => 100.00,
            'description' => 'Netflix Premium Subscription',
            'merchantReference' => 'NETFLIX_123',
            'validityDate' => now()->addYear(),
        ];

        $authorization = SibsMbwayAP::createAuthorization($authorizationData);

        $this->assertInstanceOf(AuthorizedPayment::class, $authorization);
        $this->assertEquals('351919999999', $authorization->customer_phone);
        $this->assertEquals('customer@example.com', $authorization->customer_email);
        $this->assertEquals(100.00, $authorization->max_amount);
        $this->assertEquals('pending', $authorization->status);

        Event::assertDispatched(AuthorizationCreated::class);
    }

    /** @test */
    public function it_can_process_charges_on_active_authorization()
    {
        Event::fake();

        // Create an active authorization
        $authorization = AuthorizedPayment::create([
            'customer_phone' => '351919999999',
            'customer_email' => 'customer@example.com',
            'max_amount' => 100.00,
            'currency' => 'EUR',
            'validity_date' => now()->addYear(),
            'status' => AuthorizedPayment::STATUS_ACTIVE,
            'authorization_id' => 'SIBS_AUTH_123',
            'description' => 'Test Authorization',
        ]);

        $charge = SibsMbwayAP::processCharge(
            $authorization,
            29.99,
            'Monthly subscription charge'
        );

        $this->assertInstanceOf(Charge::class, $charge);
        $this->assertEquals(29.99, $charge->amount);
        $this->assertEquals($authorization->id, $charge->authorized_payment_id);

        Event::assertDispatched(ChargeProcessed::class);
    }
}
