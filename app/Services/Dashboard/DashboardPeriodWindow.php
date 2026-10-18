<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use Carbon\CarbonImmutable;
use Hekmatinasser\Verta\Verta;

/**
 * An inclusive window of Tehran days, normalized to midnight at the start and 23:59:59.999999 at the end.
 */
final readonly class DashboardPeriodWindow
{
    public CarbonImmutable $start;

    public CarbonImmutable $end;

    public function __construct(CarbonImmutable $start, CarbonImmutable $end)
    {
        $this->start = $start->startOfDay();
        $this->end   = $end->endOfDay();
    }

    /**
     * One Jalali Y-m-d entry per day covered by the window, oldest first.
     *
     * @return list<string>
     */
    public function days(): array
    {
        $days = [];

        for ($day = $this->start; $day->lessThanOrEqualTo($this->end); $day = $day->addDay()) {
            $days[] = Verta::instance($day)->format('Y-m-d');
        }

        return $days;
    }
}
