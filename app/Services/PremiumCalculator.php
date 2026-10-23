<?php

namespace App\Services;

use Carbon\Carbon;
use InvalidArgumentException;

class PremiumCalculator
{
    public const DAILY_RATES = [
        'BASIC' => '12',
        'PLUS' => '20',
        'PREMIUM' => '30',
    ];

    public const AGE_MULTIPLIERS = [
        ['min' => 0, 'max' => 17, 'value' => '1.10'],
        ['min' => 18, 'max' => 60, 'value' => '1.00'],
        ['min' => 61, 'max' => 70, 'value' => '1.50'],
        ['min' => 71, 'max' => 150, 'value' => '2.00'],
    ];

    public const DESTINATION_MULTIPLIERS = [
        'DOMESTIC' => '1.00',
        'INTERNATIONAL' => '1.80',
    ];

    public const COVERAGE_MULTIPLIERS = [
        100000 => '1.00',
        300000 => '1.25',
        500000 => '1.50',
    ];

    public static function calculate(
        string|Carbon $tripStart,
        string|Carbon $tripEnd,
        string|Carbon $dateOfBirth,
        string $destination,
        string $planCode,
        int $sumInsured,
    ): string {
        $tripStart = Carbon::parse($tripStart);
        $tripEnd = Carbon::parse($tripEnd);
        $dateOfBirth = Carbon::parse($dateOfBirth);

        if (! isset(self::DAILY_RATES[$planCode])) {
            throw new InvalidArgumentException('Unsupported plan code.');
        }

        if (! isset(self::DESTINATION_MULTIPLIERS[$destination])) {
            throw new InvalidArgumentException('Unsupported destination.');
        }

        if (! isset(self::COVERAGE_MULTIPLIERS[$sumInsured])) {
            throw new InvalidArgumentException('Unsupported sum insured.');
        }

        $days = max(1, $tripStart->diffInDays($tripEnd) + 1);
        $age = (int) $dateOfBirth->diffInYears($tripStart);
        $ageMultiplier = self::ageMultiplierForAge($age);
        $destinationMultiplier = self::DESTINATION_MULTIPLIERS[$destination];
        $coverageMultiplier = self::COVERAGE_MULTIPLIERS[$sumInsured];
        $dailyRate = self::DAILY_RATES[$planCode];

        $amount = bcmul($dailyRate, (string) $days, 4);
        $amount = bcmul($amount, $ageMultiplier, 4);
        $amount = bcmul($amount, $destinationMultiplier, 4);
        $amount = bcmul($amount, $coverageMultiplier, 4);

        return self::roundToTwoDecimalPlaces($amount);
    }

    public static function ageMultiplierForAge(int $age): string
    {
        foreach (self::AGE_MULTIPLIERS as $range) {
            if ($age >= $range['min'] && $age <= $range['max']) {
                return $range['value'];
            }
        }

        throw new InvalidArgumentException('Unsupported age range.');
    }

    private static function roundToTwoDecimalPlaces(string $value): string
    {
        $scaled = bcmul($value, '100', 4);
        $rounded = bcadd($scaled, '0.5', 0);

        return bcdiv($rounded, '100', 2);
    }
}
