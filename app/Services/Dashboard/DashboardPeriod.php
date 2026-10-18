<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Hekmatinasser\Verta\Verta;
use InvalidArgumentException;

/**
 * Resolves a dashboard period key into Gregorian query boundaries and Jalali response dates.
 *
 * Supported keys, all resolved in Asia/Tehran (default when no key is given: `30d`):
 * - `today`: today; the previous window is yesterday.
 * - `7d` / `30d`: today and the 6 / 29 days before it; the previous window is the equal-length
 *   window immediately before it.
 * - `mtd`: the first day of the current Jalali month through today; the previous window is the
 *   same day span of the previous Jalali month.
 *
 * The trend window is the period itself, except for `today` where it is the seven days ending
 * today (overlaid with the seven days before that) so the chart is more than one point.
 * A window carries Gregorian boundaries for querying and Jalali Y-m-d output for responses.
 */
final readonly class DashboardPeriod
{
    public const string TIMEZONE = 'Asia/Tehran';

    public const string DEFAULT_KEY = '30d';

    /** @var list<string> */
    public const array KEYS = ['today', '7d', '30d', 'mtd'];

    private function __construct(
        private string $key,
        private DashboardPeriodWindow $current,
        private DashboardPeriodWindow $previous,
        private DashboardPeriodWindow $trend,
        private DashboardPeriodWindow $previousTrend,
    ) {}

    /**
     * @throws InvalidArgumentException when the key is not one of self::KEYS
     */
    public static function resolve(?string $key = null, ?DateTimeInterface $now = null): self
    {
        $key   = $key ?? self::DEFAULT_KEY;
        $today = self::tehranToday($now);

        [$current, $previous] = match ($key) {
            'today' => [
                new DashboardPeriodWindow($today, $today),
                new DashboardPeriodWindow($today->subDay(), $today->subDay()),
            ],
            '7d'    => self::rollingWindows($today, 7),
            '30d'   => self::rollingWindows($today, 30),
            'mtd'   => self::monthToDateWindows($today),
            default => throw new InvalidArgumentException(sprintf(
                'Unsupported dashboard period [%s]. Allowed: %s.',
                $key,
                implode(', ', self::KEYS),
            )),
        };

        $trend         = $key === 'today' ? new DashboardPeriodWindow($today->subDays(6), $today) : $current;
        $previousTrend = $key === 'today' ? new DashboardPeriodWindow($today->subDays(13), $today->subDays(7)) : $previous;

        return new self($key, $current, $previous, $trend, $previousTrend);
    }

    public function key(): string
    {
        return $this->key;
    }

    public function current(): DashboardPeriodWindow
    {
        return $this->current;
    }

    public function previous(): DashboardPeriodWindow
    {
        return $this->previous;
    }

    public function trend(): DashboardPeriodWindow
    {
        return $this->trend;
    }

    public function previousTrend(): DashboardPeriodWindow
    {
        return $this->previousTrend;
    }

    private static function tehranToday(?DateTimeInterface $now): CarbonImmutable
    {
        return CarbonImmutable::instance($now ?? CarbonImmutable::now(self::TIMEZONE))
            ->setTimezone(self::TIMEZONE)
            ->startOfDay();
    }

    /**
     * A window of $days Tehran days ending today, paired with the equal-length window before it.
     *
     * @return array{0: DashboardPeriodWindow, 1: DashboardPeriodWindow}
     */
    private static function rollingWindows(CarbonImmutable $today, int $days): array
    {
        $previousEnd = $today->subDays($days);

        return [
            new DashboardPeriodWindow($today->subDays($days - 1), $today),
            new DashboardPeriodWindow($previousEnd->subDays($days - 1), $previousEnd),
        ];
    }

    /**
     * The current Jalali month through today, paired with the same day span of the previous Jalali
     * month (clamped to that month's last day when it is shorter than today's day of month).
     *
     * @return array{0: DashboardPeriodWindow, 1: DashboardPeriodWindow}
     */
    private static function monthToDateWindows(CarbonImmutable $today): array
    {
        $jalaliToday  = Verta::instance($today);
        $previousFrom = $jalaliToday->copy()->startMonth()->subMonths(1);

        $previousEnd = $previousFrom->copy()
            ->setDateJalali(
                $previousFrom->year,
                $previousFrom->month,
                min($jalaliToday->day, $previousFrom->daysInMonth),
            )
            ->endDay();

        return [
            new DashboardPeriodWindow(self::toGregorianCarbon($jalaliToday->copy()->startMonth()), $today),
            new DashboardPeriodWindow(self::toGregorianCarbon($previousFrom), self::toGregorianCarbon($previousEnd)),
        ];
    }

    private static function toGregorianCarbon(Verta $jalali): CarbonImmutable
    {
        return CarbonImmutable::instance($jalali->toCarbon())->setTimezone(self::TIMEZONE);
    }
}
