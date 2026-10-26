<?php

namespace App\Services;

use App\Models\Holiday;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class WorkingDayService
{
    public function calculate(Carbon $start, Carbon $end): array
    {
        $holidays = Holiday::query()
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                    ->orWhere('repeat_every_year', true);
            })
            ->get();
        $holidayDates = [];
        foreach (CarbonPeriod::create($start, $end) as $date) {
            $holiday = $holidays->first(function (Holiday $holiday) use ($date) {
                return $holiday->repeat_every_year
                    ? $holiday->date->format('m-d') === $date->format('m-d')
                    : $holiday->date->isSameDay($date);
            });
            if ($holiday) {
                $holidayDates[$date->toDateString()] = $holiday->name;
            }
        }

        $working = 0;
        $sundays = 0;
        $saturdays = 0;
        $excludedSaturdays = 0;
        $excludedHolidays = [];
        foreach (CarbonPeriod::create($start, $end) as $date) {
            if ($date->isSunday()) {
                $sundays++;

                continue;
            }
            if ($date->isSaturday()) {
                $saturdays++;
                $weekOfMonth = (int) ceil($date->day / 7);
                if (in_array($weekOfMonth, [2, 4], true)) {
                    $excludedSaturdays++;

                    continue;
                }
            }
            if (isset($holidayDates[$date->toDateString()])) {
                $excludedHolidays[] = [
                    'date' => $date->toDateString(),
                    'name' => $holidayDates[$date->toDateString()],
                ];

                continue;
            }
            $working++;
        }

        return [
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'calendar_days' => $start->diffInDays($end) + 1,
            'working_days' => $working,
            'excluded' => [
                'sundays' => $sundays,
                'all_saturdays' => $saturdays,
                'second_and_fourth_saturdays' => $excludedSaturdays,
                'holidays' => count($excludedHolidays),
                'holiday_dates' => $excludedHolidays,
            ],
            'rule' => 'Sundays, holidays, and only the second and fourth Saturdays are excluded. Other Saturdays are working days.',
        ];
    }
}
