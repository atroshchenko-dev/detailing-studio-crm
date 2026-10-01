<?php

declare(strict_types=1);

namespace App;

/**
 * Inline-календар для Telegram
 *
 * Генерує inline keyboard у вигляді календаря з навігацією по місяцях.
 * Поточний день виділяється квадратними дужками [N].
 *
 * @package App
 */
class TelegramCalendar
{
    /**
     * Create Calendar Keyboard
     * 
     * @param int $year
     * @param int $month
     * @param string $prefix Callback prefix (e.g. 'calendar_')
     * @return array Inline Keyboard
     */
    public static function getKeyboard($year, $month, $prefix = 'calendar_')
    {
        $keyboard = [];

        // 1. Header: Month Year
        $monthName = self::getMonthName($month);
        $keyboard[] = [
            [
                'text' => "$monthName $year",
                'callback_data' => $prefix . "ignore"
            ]
        ];

        // 2. Days of week header
        $days = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Нд'];
        $row = [];
        foreach ($days as $day) {
            $row[] = ['text' => $day, 'callback_data' => $prefix . "ignore"];
        }
        $keyboard[] = $row;

        // 3. Days
        $firstDayOfMonth = mktime(0, 0, 0, $month, 1, $year);
        $daysInMonth = date('t', $firstDayOfMonth);
        $dayOfWeek = date('N', $firstDayOfMonth); // 1 (Mon) - 7 (Sun)

        $currentDay = 1;
        $row = [];

        // Empty slots before 1st day
        for ($i = 1; $i < $dayOfWeek; $i++) {
            $row[] = ['text' => ' ', 'callback_data' => $prefix . "ignore"];
        }

        while ($currentDay <= $daysInMonth) {
            $date = sprintf('%04d-%02d-%02d', $year, $month, $currentDay);

            $text = (string)$currentDay;
            if ($date === date('Y-m-d')) {
                $text = "[$text]"; // Highlight today
            }

            $row[] = ['text' => $text, 'callback_data' => $prefix . "day_" . $date];

            if (count($row) === 7) {
                $keyboard[] = $row;
                $row = [];
            }
            $currentDay++;
        }

        // Fill last row
        if (!empty($row)) {
            while (count($row) < 7) {
                $row[] = ['text' => ' ', 'callback_data' => $prefix . "ignore"];
            }
            $keyboard[] = $row;
        }

        // 4. Navigation controls
        $prevMonth = $month - 1;
        $prevYear = $year;
        if ($prevMonth < 1) {
            $prevMonth = 12;
            $prevYear--;
        }

        $nextMonth = $month + 1;
        $nextYear = $year;
        if ($nextMonth > 12) {
            $nextMonth = 1;
            $nextYear++;
        }

        $keyboard[] = [
            ['text' => '<<', 'callback_data' => $prefix . "nav_{$prevYear}_{$prevMonth}"],
            ['text' => '>>', 'callback_data' => $prefix . "nav_{$nextYear}_{$nextMonth}"]
        ];

        // 5. Special buttons (Skip / Cancel) - Optional, can be added by caller if needed
        // For now, let's keep it simple.

        return ['inline_keyboard' => $keyboard];
    }

    private static function getMonthName($month)
    {
        $months = [
            1 => 'Січень',
            2 => 'Лютий',
            3 => 'Березень',
            4 => 'Квітень',
            5 => 'Травень',
            6 => 'Червень',
            7 => 'Липень',
            8 => 'Серпень',
            9 => 'Вересень',
            10 => 'Жовтень',
            11 => 'Листопад',
            12 => 'Грудень'
        ];
        return $months[$month] ?? '';
    }
}
