<?php

namespace Tests\Unit;

use App\Support\Phone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneTest extends TestCase
{
    public static function nigerianNumbers(): array
    {
        return [
            'local' => ['08031234567', '+2348031234567'],
            'local with spaces' => ['0803 123 4567', '+2348031234567'],
            'international' => ['+234 803 123 4567', '+2348031234567'],
            'without plus' => ['2348031234567', '+2348031234567'],
        ];
    }

    #[DataProvider('nigerianNumbers')]
    public function test_nigerian_numbers_are_stored_in_one_format(string $input, string $expected): void
    {
        $this->assertSame($expected, Phone::normalize($input));
    }

    public function test_empty_input_stays_empty(): void
    {
        $this->assertNull(Phone::normalize(null));
        $this->assertNull(Phone::normalize(''));
    }

    public function test_emails_are_not_mistaken_for_phones(): void
    {
        $this->assertTrue(Phone::looksLikePhone('0803 123 4567'));
        $this->assertFalse(Phone::looksLikePhone('someone@example.org'));
    }
}
