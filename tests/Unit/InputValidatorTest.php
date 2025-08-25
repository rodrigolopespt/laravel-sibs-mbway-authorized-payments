<?php

namespace Rodrigolopespt\SibsMbwayAP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rodrigolopespt\SibsMbwayAP\Exceptions\SibsException;
use Rodrigolopespt\SibsMbwayAP\Support\InputValidator;

class InputValidatorTest extends TestCase
{
    /** @test */
    public function it_validates_clean_portuguese_phone_numbers()
    {
        $phone = '351919999999';

        $this->assertTrue(InputValidator::validatePortuguesePhone($phone));
        $this->assertEquals('351919999999', InputValidator::formatPortuguesePhone($phone));
    }

    /** @test */
    public function it_handles_international_format_with_plus()
    {
        $phone = '+351911016149';

        $this->assertTrue(InputValidator::validatePortuguesePhone($phone));
        $this->assertEquals('351911016149', InputValidator::formatPortuguesePhone($phone));
    }

    /** @test */
    public function it_handles_spaced_formats()
    {
        $testCases = [
            '351 919 999 999',
            '+351 919 999 999',
            '351-919-999-999',
            '+351-919-999-999',
            '351.919.999.999',
        ];

        foreach ($testCases as $phone) {
            $this->assertTrue(InputValidator::validatePortuguesePhone($phone), "Failed for: {$phone}");
            $this->assertEquals('351919999999', InputValidator::formatPortuguesePhone($phone));
        }
    }

    /** @test */
    public function it_rejects_invalid_phone_numbers()
    {
        $invalidPhones = [
            '919999999', // Missing country code
            '123919999999', // Wrong country code
            '35191999999', // Too few digits
            '3519199999999', // Too many digits
            '+1234567890', // Wrong country
            '', // Empty
            'abc351919999999', // Contains letters
        ];

        foreach ($invalidPhones as $phone) {
            $this->assertFalse(InputValidator::validatePortuguesePhone($phone), "Should fail for: {$phone}");
        }
    }

    /** @test */
    public function it_throws_exception_for_invalid_format()
    {
        $this->expectException(SibsException::class);
        $this->expectExceptionMessage('Invalid Portuguese phone number format');

        InputValidator::formatPortuguesePhone('123456789');
    }
}
