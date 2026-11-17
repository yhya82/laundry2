<?php

namespace Tests\Unit;

use App\Support\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneNumberTest extends TestCase
{
    public static function conversions(): array
    {
        return [
            '3 -> 83' => ['3123456', '+220833123456'],
            '5 -> 83 (the example)' => ['5190199', '+220835190199'],
            '6 -> 86' => ['6123456', '+220866123456'],
            '8 -> 86' => ['8123456', '+220868123456'],
            '2 -> 87' => ['2123456', '+220872123456'],
            '4 -> 87' => ['4123456', '+220874123456'],
            '7 -> 87' => ['7123456', '+220877123456'],
            '9 stays 7 digits (unmapped)' => ['9123456', '+2209123456'],
            'already 9 digits' => ['835190199', '+220835190199'],
            'spaces and dashes' => ['519-01 99', '+220835190199'],
            'with +220' => ['+220 519 0199', '+220835190199'],
            'with 00220' => ['00220 5190199', '+220835190199'],
            'with 220, no plus' => ['2205190199', '+220835190199'],
            '9-digit with +220' => ['+220 87 7123456', '+220877123456'],
        ];
    }

    #[DataProvider('conversions')]
    public function test_numbers_are_normalized_and_7_digits_converted(string $typed, string $stored): void
    {
        $this->assertSame($stored, PhoneNumber::normalize($typed));
        $this->assertTrue(PhoneNumber::isValid($stored));
    }

    public static function invalid(): array
    {
        return [
            '9 digits, prefix 55' => ['555123456'],
            '9 digits, prefix 70' => ['700123456'],
            '7 digits starting 0' => ['0123456'],
            '7 digits starting 1' => ['1123456'],
            '8 digits' => ['51901990'],
            '10 digits' => ['5190199000'],
            'another country' => ['+44 20 7946 0958'],
        ];
    }

    #[DataProvider('invalid')]
    public function test_invalid_numbers_are_refused(string $typed): void
    {
        $this->assertFalse(PhoneNumber::isValid(PhoneNumber::normalize($typed)));
    }

    public function test_a_number_and_its_old_7_digit_form_are_the_same_line(): void
    {
        $this->assertSame(['+220835190199', '+2205190199'], PhoneNumber::equivalents('+220835190199'));
        $this->assertSame(['+2205190199', '+220835190199'], PhoneNumber::equivalents('+2205190199'));

        // 86 + a number starting 5 was never 5190199 (5 maps to 83) -- different line.
        $this->assertSame(['+220865190199'], PhoneNumber::equivalents('+220865190199'));
        $this->assertSame(['+2209123456'], PhoneNumber::equivalents('+2209123456'));
        $this->assertSame(['+220901234567'], PhoneNumber::equivalents('+220901234567'));
    }

    public function test_search_variants_cover_both_forms_and_leave_names_alone(): void
    {
        $this->assertSame(['5190199', '835190199'], PhoneNumber::searchVariants('5190199'));
        $this->assertSame(['835190199', '+2205190199'], PhoneNumber::searchVariants('835 190 199'));
        $this->assertSame(['835190199', '+2205190199'], PhoneNumber::searchVariants('+220835190199'));
        $this->assertSame([], PhoneNumber::searchVariants('Awa Jallow'));
        $this->assertSame([], PhoneNumber::searchVariants('519'));
    }
}
