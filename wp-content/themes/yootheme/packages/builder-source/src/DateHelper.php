<?php

namespace YOOtheme\Builder;

use DateTime;
use DateTimeZone;

class DateHelper
{
    /**
     * @param array{
     *     date_range?: 'fixed'|'relative'|'custom',
     *     date_start?: string,
     *     date_end?: string,
     *     date_start_custom?: string,
     *     date_end_custom?: string,
     *     date_relative?: 'this'|'last'|'next',
     *     date_relative_value?: int,
     *     date_relative_unit?: 'day'|'week'|'month'|'year',
     *     date_relative_unit_this?: 'day'|'week'|'week_calendar'|'month'|'month_calendar'|'year'|'year_calendar',
     *     date_relative_include_today?: bool,
     *     date_relative_start_today?: bool,
     * } $args
     *
     * @param string|DateTimeZone $timezone
     *
     * @return array<string, mixed>
     */
    public static function parseStartEndArguments(array $args = [], $timezone = 'UTC'): array
    {
        switch ($args['date_range'] ?? '') {
            case 'relative':
                $unit = 'date_relative_unit' . ($args['date_relative'] === 'this' ? '_this' : '');
                [$args['date_start'], $args['date_end']] = static::parseRelativeDate(
                    $args['date_relative'],
                    (int) ($args['date_relative_value'] ?? null),
                    $args[$unit],
                    $args['date_relative_start_today'] ?? false,
                    $timezone,
                );
                break;
            case 'custom':
                $args['date_start'] = $args['date_start_custom'] ?? null;
                $args['date_end'] = $args['date_end_custom'] ?? null;
                break;
        }

        $args['date_start'] = static::getDate($args['date_start'] ?? null, $timezone);
        $args['date_end'] = static::getDate($args['date_end'] ?? null, $timezone);

        return $args;
    }

    /**
     * @param string|DateTime|null $date
     * @param string|DateTimeZone $timezone
     */
    public static function toSql($date, $timezone = 'UTC'): ?string
    {
        $date = static::getDate($date);

        return $date
            ? $date->setTimezone(static::toTimezone($timezone))->format('Y-m-d H:i:s')
            : null;
    }

    /**
     * @param string|DateTime|null $date
     */
    public static function toTimestamp($date): ?int
    {
        $date = static::getDate($date);

        return $date ? $date->getTimestamp() : null;
    }

    /**
     * @param string|DateTime|null $date
     * @param string|DateTimeZone $timezone
     */
    protected static function getDate($date = 'now', $timezone = 'UTC'): ?DateTime
    {
        if ($date && is_string($date)) {
            $date = date_create($date, static::toTimezone($timezone));
        }

        return $date ?: null;
    }

    /**
     * @param string|DateTimeZone $timezone
     */
    protected static function toTimezone($timezone): DateTimeZone
    {
        return is_string($timezone) ? new DateTimeZone($timezone) : $timezone;
    }

    /**
     * @param 'this'|'last'|'next' $type
     * @param 'day'|'week'|'week_calendar'|'month'|'month_calendar'|'year'|'year_calendar' $unit
     * @param string|DateTimeZone $timezone
     *
     * @return array{?DateTime, ?DateTime}
     */
    protected static function parseRelativeDate(
        string $type,
        ?int $value,
        string $unit,
        ?bool $includeToday,
        $timezone
    ): array {
        $isCalendar = str_ends_with($unit, '_calendar');

        if ($isCalendar) {
            $unit = substr($unit, 0, -9);
        }

        $dateStart = static::getDate('now', $timezone);
        $dateEnd = static::getDate('now', $timezone);

        if ($type === 'this') {
            static::modify([$dateStart], 'this', $unit);
            static::modify([$dateEnd], 'next', $unit);
        } else {
            $range = [&$dateStart, &$dateEnd];

            if ($type === 'next') {
                $range = array_reverse($range);
            }

            if (!$value) {
                $range[0] = null;
            }

            static::modify($range, ($type === 'last' xor !$includeToday) ? 'next' : 'this', 'day');

            if ($value) {
                if ($isCalendar) {
                    $nominator = $type === 'last' ? 'this' : 'next';
                    static::modify($includeToday ? [$range[0]] : $range, $nominator, $unit);

                    if ($includeToday) {
                        $value--;
                    }
                }

                if ($value) {
                    if ($type === 'last') {
                        $value *= -1;
                    }
                    $range[0]->modify("{$value} {$unit}");
                }
            }
        }

        return [$dateStart, $dateEnd ? $dateEnd->modify('-1 second') : null];
    }

    /**
     * @param array<null|DateTime> $dates
     * @param string $nominator
     * @param string $unit
     */
    protected static function modify($dates, string $nominator, string $unit): void
    {
        foreach (array_filter($dates) as $date) {
            if ($unit === 'day') {
                $modifier = $nominator === 'this' ? 'today' : 'tomorrow';
            } elseif ($unit === 'week') {
                $modifier = 'monday %s week';
            } elseif ($unit === 'year') {
                $modifier = "first day of January %s {$unit} today";
            } else {
                $modifier = "first day of %s {$unit} today";
            }

            $date->modify(sprintf($modifier, $nominator));
        }
    }
}
