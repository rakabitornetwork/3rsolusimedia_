<?php

namespace Tests\Unit;

use App\Support\PhoneNumber;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PhoneNumberTest extends TestCase
{
    #[Test]
    public function it_normalizes_international_indonesia_numbers(): void
    {
        $this->assertSame('081234567890', PhoneNumber::normalize('+62 812-3456-7890'));
        $this->assertSame('081234567890', PhoneNumber::normalize('6281234567890'));
        $this->assertSame('081234567890', PhoneNumber::normalize('081234567890'));
    }

    #[Test]
    public function it_matches_equivalent_phone_numbers(): void
    {
        $this->assertTrue(PhoneNumber::matches('081234567890', '6281234567890'));
        $this->assertTrue(PhoneNumber::matches('081234567890', '81234567890'));
        $this->assertFalse(PhoneNumber::matches('081234567890', '081111111111'));
        $this->assertFalse(PhoneNumber::matches('081234567890', '123'));
    }

    #[Test]
    public function it_converts_local_numbers_to_international(): void
    {
        $this->assertSame('6281234567890', PhoneNumber::toInternational('081234567890'));
        $this->assertSame('6281234567890', PhoneNumber::toInternational('6281234567890@s.whatsapp.net'));
        $this->assertSame('6281234567890', PhoneNumber::toInternational('+62 812-3456-7890'));
        $this->assertSame('', PhoneNumber::toInternational('89374763012229@lid'));
    }
}
