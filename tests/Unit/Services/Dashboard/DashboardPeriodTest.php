<?php

declare(strict_types=1);

use App\Services\Dashboard\DashboardPeriod;
use App\Services\Dashboard\DashboardPeriodWindow;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Hekmatinasser\Verta\Verta;

mutates(DashboardPeriod::class);

afterEach(function (): void {
    Carbon::setTestNow();
});

/**
 * @return array{start: string, end: string, start_jalali: string, end_jalali: string}
 */
function dashboardWindowBounds(DashboardPeriodWindow $window): array
{
    return [
        'start'        => $window->start->format('Y-m-d H:i:s'),
        'end'          => $window->end->format('Y-m-d H:i:s'),
        'start_jalali' => dashboardJalali($window->start),
        'end_jalali'   => dashboardJalali($window->end),
    ];
}

function dashboardJalali(CarbonImmutable $date): string
{
    return Verta::instance($date)->format('Y-m-d');
}

function dashboardPeriodNow(string $dateTime): CarbonImmutable
{
    return CarbonImmutable::parse($dateTime, DashboardPeriod::TIMEZONE);
}

it('resolves today to the Tehran day with yesterday as the previous window', function (): void {
    $period = DashboardPeriod::resolve('today', dashboardPeriodNow('2025-08-27 12:00:00'));

    expect($period->key())->toBe('today')
        ->and(dashboardWindowBounds($period->current()))->toBe([
            'start'        => '2025-08-27 00:00:00',
            'end'          => '2025-08-27 23:59:59',
            'start_jalali' => '1404-06-05',
            'end_jalali'   => '1404-06-05',
        ])
        ->and(dashboardWindowBounds($period->previous()))->toBe([
            'start'        => '2025-08-26 00:00:00',
            'end'          => '2025-08-26 23:59:59',
            'start_jalali' => '1404-06-04',
            'end_jalali'   => '1404-06-04',
        ]);
});

it('resolves rolling windows that include today with the immediately preceding equal-length window', function (string $key, array $current, array $previous): void {
    $period = DashboardPeriod::resolve($key, dashboardPeriodNow('2025-08-27 12:00:00'));

    expect($period->key())->toBe($key)
        ->and(dashboardWindowBounds($period->current()))->toBe($current)
        ->and(dashboardWindowBounds($period->previous()))->toBe($previous);
})->with([
    '7d' => [
        '7d',
        [
            'start'        => '2025-08-21 00:00:00',
            'end'          => '2025-08-27 23:59:59',
            'start_jalali' => '1404-05-30',
            'end_jalali'   => '1404-06-05',
        ],
        [
            'start'        => '2025-08-14 00:00:00',
            'end'          => '2025-08-20 23:59:59',
            'start_jalali' => '1404-05-23',
            'end_jalali'   => '1404-05-29',
        ],
    ],
    '30d' => [
        '30d',
        [
            'start'        => '2025-07-29 00:00:00',
            'end'          => '2025-08-27 23:59:59',
            'start_jalali' => '1404-05-07',
            'end_jalali'   => '1404-06-05',
        ],
        [
            'start'        => '2025-06-29 00:00:00',
            'end'          => '2025-07-28 23:59:59',
            'start_jalali' => '1404-04-08',
            'end_jalali'   => '1404-05-06',
        ],
    ],
]);

it('starts month-to-date on the first Jalali day and mirrors the same day span into the previous Jalali month', function (string $now, array $current, array $previous): void {
    $period = DashboardPeriod::resolve('mtd', dashboardPeriodNow($now));

    expect($period->key())->toBe('mtd')
        ->and(dashboardWindowBounds($period->current()))->toBe($current)
        ->and(dashboardWindowBounds($period->previous()))->toBe($previous);
})->with([
    'mid Shahrivar' => [
        '2025-08-27 12:00:00',
        [
            'start'        => '2025-08-23 00:00:00',
            'end'          => '2025-08-27 23:59:59',
            'start_jalali' => '1404-06-01',
            'end_jalali'   => '1404-06-05',
        ],
        [
            'start'        => '2025-07-23 00:00:00',
            'end'          => '2025-07-27 23:59:59',
            'start_jalali' => '1404-05-01',
            'end_jalali'   => '1404-05-05',
        ],
    ],
    'across Nowruz into the 29-day Esfand' => [
        '2026-03-25 12:00:00',
        [
            'start'        => '2026-03-21 00:00:00',
            'end'          => '2026-03-25 23:59:59',
            'start_jalali' => '1405-01-01',
            'end_jalali'   => '1405-01-05',
        ],
        [
            'start'        => '2026-02-20 00:00:00',
            'end'          => '2026-02-24 23:59:59',
            'start_jalali' => '1404-12-01',
            'end_jalali'   => '1404-12-05',
        ],
    ],
    'Farvardin 30 clamps to the last day of the 29-day Esfand' => [
        '2026-04-19 12:00:00',
        [
            'start'        => '2026-03-21 00:00:00',
            'end'          => '2026-04-19 23:59:59',
            'start_jalali' => '1405-01-01',
            'end_jalali'   => '1405-01-30',
        ],
        [
            'start'        => '2026-02-20 00:00:00',
            'end'          => '2026-03-20 23:59:59',
            'start_jalali' => '1404-12-01',
            'end_jalali'   => '1404-12-29',
        ],
    ],
]);

it('shows a seven-day trend ending today for the today period', function (): void {
    $period = DashboardPeriod::resolve('today', dashboardPeriodNow('2025-08-27 12:00:00'));

    expect($period->trend()->days())->toBe([
        '1404-05-30',
        '1404-05-31',
        '1404-06-01',
        '1404-06-02',
        '1404-06-03',
        '1404-06-04',
        '1404-06-05',
    ])->and($period->previousTrend()->days())->toBe([
        '1404-05-23',
        '1404-05-24',
        '1404-05-25',
        '1404-05-26',
        '1404-05-27',
        '1404-05-28',
        '1404-05-29',
    ]);
});

it('uses the period itself as the trend for every other period', function (string $key, int $days, string $first, string $last): void {
    $period = DashboardPeriod::resolve($key, dashboardPeriodNow('2025-08-27 12:00:00'));

    expect(dashboardWindowBounds($period->trend()))->toBe(dashboardWindowBounds($period->current()))
        ->and(dashboardWindowBounds($period->previousTrend()))->toBe(dashboardWindowBounds($period->previous()))
        ->and($period->trend()->days())->toHaveCount($days)
        ->and($period->trend()->days()[0])->toBe($first)
        ->and($period->trend()->days()[$days - 1])->toBe($last)
        ->and($period->previousTrend()->days())->toHaveCount($days);
})->with([
    '7d'  => ['7d', 7, '1404-05-30', '1404-06-05'],
    '30d' => ['30d', 30, '1404-05-07', '1404-06-05'],
    'mtd' => ['mtd', 5, '1404-06-01', '1404-06-05'],
]);

it('keeps the month-to-date previous trend on the clamped previous-month span', function (): void {
    $period = DashboardPeriod::resolve('mtd', dashboardPeriodNow('2026-04-19 12:00:00'));

    expect($period->trend()->days())->toHaveCount(30)
        ->and($period->previousTrend()->days())->toHaveCount(29);
});

it('rejects unknown period keys instead of falling back to a default', function (string $key): void {
    DashboardPeriod::resolve($key, dashboardPeriodNow('2025-08-27 12:00:00'));
})->with(['90d', 'week', '', 'TODAY'])->throws(InvalidArgumentException::class);

it('defaults to the last 30 days when no period is given', function (): void {
    $period = DashboardPeriod::resolve(null, dashboardPeriodNow('2025-08-27 12:00:00'));

    expect($period->key())->toBe('30d')
        ->and(dashboardWindowBounds($period->current()))->toBe([
            'start'        => '2025-07-29 00:00:00',
            'end'          => '2025-08-27 23:59:59',
            'start_jalali' => '1404-05-07',
            'end_jalali'   => '1404-06-05',
        ]);
});

it('resolves the current time in Tehran when no now is injected', function (): void {
    Carbon::setTestNow(Carbon::parse('2025-08-26 21:30:00', 'UTC'));

    $period = DashboardPeriod::resolve('today');

    expect($period->current()->start->format('Y-m-d H:i:s'))->toBe('2025-08-27 00:00:00')
        ->and($period->current()->start->timezoneName)->toBe('Asia/Tehran')
        ->and(dashboardJalali($period->current()->start))->toBe('1404-06-05');
});

it('resolves every advertised period key', function (string $key): void {
    $period = DashboardPeriod::resolve($key, dashboardPeriodNow('2025-08-27 12:00:00'));

    expect($period->key())->toBe($key);
})->with(DashboardPeriod::KEYS);
