<?php

return [
    'year' => ':count год|:count года|:count лет',
    'y' => ':count г.',
    'month' => ':count месяц|:count месяца|:count месяцев',
    'm' => ':count мес.',
    'week' => ':count неделя|:count недели|:count недель',
    'w' => ':count нед.',
    'day' => ':count день|:count дня|:count дней',
    'd' => ':count д.',
    'hour' => ':count час|:count часа|:count часов',
    'h' => ':count ч.',
    'minute' => ':count минута|:count минуты|:count минут',
    'min' => ':count мин.',
    'second' => ':count секунда|:count секунды|:count секунд',
    's' => ':count сек.',
    'ago' => ':time назад',
    'from_now' => 'через :time',
    'after' => ':time после',
    'before' => ':time до',
    'diff_now' => 'только что',
    'diff_today' => 'сегодня',
    'diff_yesterday' => 'вчера',
    'diff_tomorrow' => 'завтра',
    'diff_before_yesterday' => 'позавчера',
    'diff_after_tomorrow' => 'послезавтра',
    'period_recurrences' => 'один раз|:count раза|:count раз',
    'period_interval' => 'каждый :interval',
    'period_start_date' => 'с :date',
    'period_end_date' => 'до :date',
    'months' => ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'],
    'months_short' => ['янв', 'фев', 'мар', 'апр', 'мая', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек'],
    'weekdays' => ['воскресенье', 'понедельник', 'вторник', 'среда', 'четверг', 'пятница', 'суббота'],
    'weekdays_short' => ['вс', 'пн', 'вт', 'ср', 'чт', 'пт', 'сб'],
    'list' => [', ', ' и '],
    'first_day_of_week' => 1,
    'day_of_first_week_of_year' => 4,
    'formats' => [
        'LT' => 'H:mm',
        'LTS' => 'H:mm:ss',
        'L' => 'DD.MM.YYYY',
        'LL' => 'D MMMM YYYY г.',
        'LLL' => 'D MMMM YYYY г., H:mm',
        'LLLL' => 'dddd, D MMMM YYYY г., H:mm',
    ],
    'calendar' => [
        'sameDay' => '[Сегодня, в] LT',
        'nextDay' => '[Завтра, в] LT',
        'nextWeek' => 'dddd, [в] LT',
        'lastDay' => '[Вчера, в] LT',
        'lastWeek' => function ($date) {
            if ($date->dayOfWeek === 2) {
                return '[Во] dddd, [в] LT';
            } else {
                return '[В] dddd, [в] LT';
            }
        },
        'sameElse' => 'L',
    ],
    'ordinal' => function ($number) {
        return $number . '-й';
    },
    'meridiem' => function ($hour, $minute, $isLower) {
        if ($hour < 4) {
            return 'ночи';
        } elseif ($hour < 12) {
            return 'утра';
        } elseif ($hour < 17) {
            return 'дня';
        } else {
            return 'вечера';
        }
    },
];
