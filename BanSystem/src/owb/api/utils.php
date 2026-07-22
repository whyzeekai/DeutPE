<?php

namespace owb\api;

use owb\main;

class utils
{

    public function construct(main $main)
    {
        $this->main = $main;
    }

    public static function parseTime($time, $j = 0) {
        if ($time < 0) {
            return 0;
        }
        if ($time >= 157680000) {
            return "Навсегда";
        }
        $times = array();
        $periods = array(60, 3600, 86400, 31536000);

        for ($i = 3; $i >= 0; $i--) {
            $period = floor($time / $periods[$i]);
            $times[$i + 1] = $period;
            $time -= $period * $periods[$i];
        }
        $times[0] = $time;
        $timeStr = '';
        $tab = ' ';
        for ($i = count($times) - 1; $i >= 0; $i--) {
            if ($i === 0) $tab = '';
            if ($times[$i] > 0) $timeStr .= $times[$i] . ' ' . self::forRuLang($times[$i], $i) . $tab;
        }
        if ($j > 0) {
            $exp = explode(' ', $timeStr);
            $newTimeStr = '';
            for ($i = 0; $i < $j; $i++) {
                $newTimeStr .= $exp[$i] . " ";
            }
            return $newTimeStr;
        }

        return $timeStr;
    }

    public static function forRuLang($num, $index)
    {
        $byNum = $num % 10;
        if ($index === 0) {
            if ($byNum >= 5 or $byNum === 0) return 'секунд';
            if ($byNum >= 2 and $byNum <= 4) return 'секунды';
            return 'секунду';
        }
        if ($index === 1) {
            if ($byNum >= 5 or $byNum === 0) return 'минут';
            if ($byNum >= 2 and $byNum <= 4) return 'минуты';
            return 'минуту';
        }
        if ($index === 2) {
            if ($byNum >= 5 or $byNum === 0) return 'часов';
            if ($byNum >= 2 and $byNum <= 4) return 'часа';
            return 'час';
        }
        if ($index === 3) {
            if ($byNum >= 5 || $byNum === 0) return 'дней';
            if ($byNum >= 2 && $byNum <= 4) return 'дня';
            return 'день';
        }
        if ($index === 4) {
            if ($byNum >= 5 || $byNum === 0) return 'лет';
            if ($byNum >= 2 && $byNum <= 4) return 'года';
            return 'год';
        }
        return false;
    }

    public static function isValidTime($time): bool
    {
        if ($time === "*" || $time === 0) {
            return true;
        }
        $timeFormats = array('s', 'm', 'h', 'd', 'с', 'м', 'ч', 'д');
        $timeFormat = mb_substr($time, -1);
        $timeNums = mb_substr($time, 0, -1);
        if (strlen($time) < 2) {
            return false;
        }
        if (!in_array($timeFormat, $timeFormats, true)) {
            return false;
        }
        if (!ctype_digit($timeNums)) {
            return false;
        }
        return true;
    }

    public static function convertingFormedTimeToSec($time)
    {
        if (self::isValidTime($time)) {
            if ($time === "*" || $time === 0) {
                return 197680000;
            }
            $timeFormat = mb_substr($time, -1);
            $timeNums = (int)(mb_substr($time, 0, -1));
            if ($timeFormat === 's' || $timeFormat === 'с') {
                return $timeNums;
            }
            if ($timeFormat === 'm' || $timeFormat === 'м') {
                return $timeNums * 60;
            }
            if ($timeFormat === 'h' || $timeFormat === 'ч') {
                return $timeNums * 3600;
            }
            if ($timeFormat === 'd' || $timeFormat === 'д') {
                return $timeNums * 86400;
            }
        }
        return false;
    }

}