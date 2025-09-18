<?php

namespace App\util;

use DateInterval;
use DateTime;

class TimeUtil
{
    static function dateDiff($d1, $d2)
    {
        return round(abs(strtotime($d1) - strtotime($d2)) / 86400);
    }

    static function getDayDifference($from, $to)
    {
        $interval = date_diff(date_create($from), date_create($to));
        return intval($interval->format('%a')) + 1;
    }

    static function epochDateDiff($d1, $d2)
    {
        return round(abs($d1 - $d2) / 86400);
    }

    static function calculateAge($d1, $d2)
    {
        $date1 = new DateTime($d1);
        $date2 = new DateTime($d2);
        $diff = $date1->diff($date2);

        // Age in years, including decimals
        $years = $diff->y;
        $months = $diff->m;
        $days = $diff->d;

        // Convert to fractional years
        $age = $years + ($months / 12) + ($days / 365);
        return round($age, 3);
    }

    static function calculateAgeV2($d1, $d2)
    {
        $d1 = date_create($d1);
        $d2 = date_create($d2);
        $diff = date_diff($d2, $d1);
        return self::formatInterval($diff);
    }

    static function formatInterval(DateInterval $interval)
    {
        // https://stackoverflow.com/a/13648096
        $result = "";
        if ($interval->y) {
            $result .= $interval->format("%y years ");
        }
        if ($interval->m) {
            $result .= $interval->format("%m months ");
        }
        if ($interval->d) {
            $result .= $interval->format("%d days ");
        }
        if ($interval->h) {
            $result .= $interval->format("%h hours ");
        }
        if ($interval->i) {
            $result .= $interval->format("%i minutes ");
        }
        if ($interval->s) {
            $result .= $interval->format("%s seconds ");
        }

        return $result;
    }

    static function generateDateListArray($dateCount = 30)
    {
        $result = [];

        for ($i = $dateCount; $i > -1; $i--) {
            $result[date("Y-m-d", strtotime("-$i days"))] = 0;
        }

        return $result;
    }

    static function generateDateListKV($dateCount = 30)
    {
        $result = [];

        for ($i = $dateCount; $i > -1; $i--) {
            $result[] = date("Y-m-d", strtotime("-$i days"));
        }

        return $result;
    }

    static function calculateTimeRemaining($targetDate)
    {
        $now = new \DateTime();
        $target = new \DateTime($targetDate);

        $diff = $now->diff($target);

        $days = $diff->days;
        $weeks = floor($days / 7);
        $months = $diff->m + ($diff->y * 12);
        $hours = $diff->h + ($diff->days * 24);
        $minutes = $diff->i + ($hours * 60);

        return [
            'days' => $days,
            'weeks' => $weeks,
            'months' => $months,
            'hours' => $hours,
            'minutes' => $minutes
        ];
    }

    static function relativeTime($time): string
    {
        // Accept timestamps or date strings
        if (!is_int($time)) {
            $time = strtotime($time);
        }

        $diff = time() - $time;

        if ($diff < 1) {
            return "just now";
        }

        $units = [
            31536000 => 'year',
            2592000  => 'month',
            604800   => 'week',
            86400    => 'day',
            3600     => 'hour',
            60       => 'minute',
            1        => 'second'
        ];

        foreach ($units as $seconds => $name) {
            if ($diff >= $seconds) {
                $value = floor($diff / $seconds);
                return $value . ' ' . $name . ($value > 1 ? 's' : '') . ' ago';
            }
        }
    }
}