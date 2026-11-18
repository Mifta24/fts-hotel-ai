<?php

namespace App\Services\Concierge;

use Carbon\CarbonImmutable;

/**
 * Reads stay details (dates, party size) out of what the guest typed or said,
 * so the concierge can search rooms without waiting for the local model to
 * decide to call the tool. Understands English, Indonesian and Japanese,
 * numeric dates ("11/25"), month names ("nov 25", "25 november") and
 * "11月25日". Deliberately forgiving about typos ("nove 25", "2adult").
 */
class StayParser
{
    private const MONTHS = [
        'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'mei' => 5, 'may' => 5, 'jun' => 6,
        'jul' => 7, 'agu' => 8, 'aug' => 8, 'sep' => 9, 'okt' => 10, 'oct' => 10, 'nov' => 11, 'des' => 12, 'dec' => 12,
    ];

    private const MONTH_WORD = '(jan|feb|mar|apr|mei|may|jun|jul|agu|aug|sep|okt|oct|nov|des|dec)[a-z]*\.?';

    private const NUMBER_WORDS = ['one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5, 'six' => 6, 'satu' => 1, 'dua' => 2, 'tiga' => 3, 'empat' => 4, 'lima' => 5, 'enam' => 6];

    /**
     * @param  array<int, string>  $texts  the guest's recent messages, oldest first
     * @return array{check_in: ?string, check_out: ?string, adults: ?int, children: ?int}
     */
    public static function fromMessages(array $texts, CarbonImmutable $today, string $locale = 'en'): array
    {
        $dates = [];
        $adults = null;
        $children = null;

        foreach ($texts as $text) {
            foreach (self::datesIn($text, $today, $locale) as $date) {
                $dates[] = $date;
            }

            $adults = self::adultsIn($text) ?? $adults;
            $children = self::childrenIn($text) ?? $children;
        }

        // The two latest distinct dates are the stay, whichever order they were said in.
        $distinct = [];
        foreach (array_reverse($dates) as $date) {
            if (! in_array($date, $distinct, true)) {
                $distinct[] = $date;
            }
            if (count($distinct) === 2) {
                break;
            }
        }
        sort($distinct);

        $checkIn = $distinct[0] ?? null;
        $checkOut = $distinct[1] ?? null;

        if ($checkIn !== null && $checkOut === null) {
            foreach (array_reverse($texts) as $text) {
                if (preg_match('/(\d+)\s*(?:nights?|malam)\b|(\d+)\s*泊/iu', $text, $m) === 1) {
                    $nights = (int) (($m[1] ?? '') !== '' ? $m[1] : $m[2]);
                    $checkOut = $nights > 0 && $nights <= 30 ? CarbonImmutable::parse($checkIn)->addDays($nights)->toDateString() : null;
                    break;
                }
            }
        }

        return ['check_in' => $checkIn, 'check_out' => $checkOut, 'adults' => $adults, 'children' => $children];
    }

    /**
     * Whether one message carries a date or a party size at all.
     */
    public static function mentionsStay(string $text, CarbonImmutable $today, string $locale = 'en'): bool
    {
        return self::datesIn($text, $today, $locale) !== [] || self::adultsIn($text) !== null;
    }

    /**
     * @return array<int, string> Y-m-d, in the order mentioned
     */
    private static function datesIn(string $text, CarbonImmutable $today, string $locale): array
    {
        $found = [];
        $text = mb_strtolower($text);

        // [position => [month, day, year|null]]
        if (preg_match_all('/\b(20\d{2})-(\d{1,2})-(\d{1,2})\b/', $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matches as $m) {
                $found[$m[0][1]] = [(int) $m[2][0], (int) $m[3][0], (int) $m[1][0]];
            }
            $text = preg_replace('/\b20\d{2}-\d{1,2}-\d{1,2}\b/', str_repeat(' ', 10), $text);
        }

        if (preg_match_all('/(\d{1,2})\s*月\s*(\d{1,2})\s*日?/u', $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matches as $m) {
                $found[$m[0][1]] = [(int) $m[1][0], (int) $m[2][0], null];
            }
        }

        $word = self::MONTH_WORD;

        // "nov 25", "november 25th"
        if (preg_match_all('/\b'.$word.'\s*(\d{1,2})(?:st|nd|rd|th)?\b(?!\s*(?::|am|pm))/u', $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matches as $m) {
                $found[$m[0][1]] = [self::MONTHS[$m[1][0]], (int) $m[2][0], null];
            }
        }

        // "25 nov", "25th of november"
        if (preg_match_all('/\b(\d{1,2})(?:st|nd|rd|th)?\s*(?:of\s+)?'.$word.'/u', $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matches as $m) {
                $found[$m[0][1]] = [self::MONTHS[$m[2][0]], (int) $m[1][0], null];
            }
        }

        // "11/25", "25/11", "25-11"
        if (preg_match_all('/(?<![\d\/.-])(\d{1,2})[\/.](\d{1,2})(?![\d\/.])/', $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matches as $m) {
                $first = (int) $m[1][0];
                $second = (int) $m[2][0];

                if ($first > 12) {
                    [$month, $day] = [$second, $first];
                } elseif ($second > 12) {
                    [$month, $day] = [$first, $second];
                } else {
                    [$month, $day] = $locale === 'id' ? [$second, $first] : [$first, $second];
                }

                $found[$m[0][1]] = [$month, $day, null];
            }
        }

        ksort($found);

        $dates = [];
        foreach ($found as [$month, $day, $year]) {
            $date = self::resolve($month, $day, $year, $today);
            if ($date !== null) {
                $dates[] = $date;
            }
        }

        return $dates;
    }

    private static function resolve(int $month, int $day, ?int $year, CarbonImmutable $today): ?string
    {
        if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
            return null;
        }

        $year ??= $today->year;

        if (! checkdate($month, $day, $year)) {
            return null;
        }

        $date = CarbonImmutable::create($year, $month, $day)->startOfDay();

        // No year said: the next time this date comes round.
        if ($year === $today->year && $date->isBefore($today->startOfDay()) && checkdate($month, $day, $year + 1)) {
            $date = $date->addYear();
        }

        return $date->toDateString();
    }

    private static function adultsIn(string $text): ?int
    {
        $text = mb_strtolower($text);
        $unit = '(?:adults?|dewasa|guests?|people|persons?|pax|orang|ppl)\b';

        if (preg_match('/(\d{1,2})\s*'.$unit.'/u', $text, $m) === 1) {
            return max(1, (int) $m[1]);
        }

        if (preg_match('/\b('.implode('|', array_keys(self::NUMBER_WORDS)).')\s+'.$unit.'/u', $text, $m) === 1) {
            return self::NUMBER_WORDS[$m[1]];
        }

        if (preg_match('/(?:大人|おとな)\s*(\d{1,2})|(\d{1,2})\s*(?:名|人|大人)/u', $text, $m) === 1) {
            return max(1, (int) (($m[1] ?? '') !== '' ? $m[1] : $m[2]));
        }

        if (preg_match('/\b(?:couple|berdua|for two|just us two)\b|二人|2人|ふたり/u', $text) === 1) {
            return 2;
        }

        return null;
    }

    private static function childrenIn(string $text): ?int
    {
        $text = mb_strtolower($text);

        if (preg_match('/(\d{1,2})\s*(?:child(?:ren)?|kids?|anak|子供|子ども)/u', $text, $m) === 1) {
            return (int) $m[1];
        }

        if (preg_match('/(?:子供|子ども)\s*(\d{1,2})/u', $text, $m) === 1) {
            return (int) $m[1];
        }

        if (preg_match('/\bno (?:children|kids)\b|tanpa anak/u', $text) === 1) {
            return 0;
        }

        return null;
    }
}
