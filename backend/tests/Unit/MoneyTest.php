<?php

namespace Tests\Unit;

use App\Support\Money;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MoneyTest extends TestCase
{
    public function test_decimal_calculations_do_not_lose_cents(): void
    {
        $this->assertSame('0.30', Money::add('0.10', '0.20'));
        $this->assertSame('39.98', Money::multiply('19.99', 2));
        $this->assertSame('-50.00', Money::subtract('500.00', '550.00'));
        $this->assertSame('5.00', Money::parse('5'));
    }

    public function test_float_input_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        Money::parse(0.1);
    }

    public function test_more_than_two_decimal_places_are_rejected(): void
    {
        $this->expectException(ValidationException::class);
        Money::parse('19.999');
    }
}
