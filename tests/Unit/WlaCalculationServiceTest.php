<?php

namespace Tests\Unit;

use App\Enums\WlaPeriodUnit;
use App\Services\Workforce\WlaCalculationService;
use App\Services\Workforce\WorkCalendarService;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WlaCalculationServiceTest extends TestCase
{
    #[DataProvider('periodFactors')]
    public function test_period_factor_uses_the_business_formula(
        WlaPeriodUnit $periodUnit,
        int $workingDays,
        int $expected,
    ): void {
        $this->assertSame($expected, $this->service()->periodFactor($periodUnit, $workingDays));
    }

    /** @return array<string, array{WlaPeriodUnit, int, int}> */
    public static function periodFactors(): array
    {
        return [
            'day follows calendar' => [WlaPeriodUnit::Day, 226, 226],
            'week is always 52' => [WlaPeriodUnit::Week, 200, 52],
            'month is always 12' => [WlaPeriodUnit::Month, 200, 12],
            'year is always 1' => [WlaPeriodUnit::Year, 200, 1],
        ];
    }

    public function test_decimal_frequency_and_time_are_preserved_in_annual_workload(): void
    {
        $this->assertSame(
            '339.0000',
            $this->service()->calculateAnnualWorkload('1.50', '1.00', WlaPeriodUnit::Day, 226),
        );
        $this->assertSame(
            '227.5000',
            $this->service()->calculateAnnualWorkload('2.50', '1.75', WlaPeriodUnit::Week, 226),
        );
    }

    public function test_fte_is_decimal_and_recommendation_uses_full_precision(): void
    {
        $service = $this->service();

        $this->assertSame('1.000000', $service->calculateFte('100.0000', '100.00'));
        $this->assertSame(1, $service->calculateRecommendedEmployees('100.0000', '100.00'));
        $this->assertSame('1.000100', $service->calculateFte('100.0100', '100.00'));
        $this->assertSame(2, $service->calculateRecommendedEmployees('100.0100', '100.00'));
    }

    public function test_zero_effective_annual_hours_are_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->service()->calculateFte('10.0000', '0.00');
    }

    private function service(): WlaCalculationService
    {
        return new WlaCalculationService(new WorkCalendarService);
    }
}
