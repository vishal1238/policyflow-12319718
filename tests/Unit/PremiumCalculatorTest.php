<?php

namespace Tests\Unit;

use App\Services\PremiumCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PremiumCalculatorTest extends TestCase
{
    public function test_example_calculation(): void
    {
        $premium = PremiumCalculator::calculate(
            '2026-05-10',
            '2026-05-14',
            '1998-01-15',
            'INTERNATIONAL',
            'PLUS',
            300000,
        );

        $this->assertSame('225.00', $premium);
    }

    #[DataProvider('ageBoundaryProvider')]
    public function test_age_boundaries(string $dob, string $tripStart, string $expected): void
    {
        $premium = PremiumCalculator::calculate(
            $tripStart,
            $tripStart,
            $dob,
            'DOMESTIC',
            'BASIC',
            100000,
        );

        $this->assertSame($expected, $premium);
    }

    public static function ageBoundaryProvider(): array
    {
        return [
            ['2009-06-15', '2026-06-15', '13.20'],
            ['2009-06-15', '2026-06-14', '13.20'],
            ['2008-06-15', '2026-06-15', '12.00'],
            ['2008-06-15', '2026-06-14', '13.20'],
            ['1966-06-15', '2026-06-15', '12.00'],
            ['1965-06-15', '2026-06-15', '18.00'],
            ['1956-06-15', '2026-06-15', '18.00'],
            ['1955-06-15', '2026-06-14', '18.00'],
            ['1955-06-15', '2026-06-15', '24.00'],
            ['1954-06-15', '2026-06-15', '24.00'],
        ];
    }

    public function test_domestic_vs_international_and_one_day_minimum(): void
    {
        $domestic = PremiumCalculator::calculate('2026-06-01', '2026-06-01', '1990-01-01', 'DOMESTIC', 'BASIC', 100000);
        $international = PremiumCalculator::calculate('2026-06-01', '2026-06-01', '1990-01-01', 'INTERNATIONAL', 'BASIC', 100000);
        $oneDay = PremiumCalculator::calculate('2026-06-01', '2026-06-01', '1990-01-01', 'DOMESTIC', 'BASIC', 100000);

        $this->assertSame('12.00', $domestic);
        $this->assertSame('21.60', $international);
        $this->assertSame('12.00', $oneDay);
    }
}
