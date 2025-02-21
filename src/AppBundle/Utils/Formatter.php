<?php

namespace AppBundle\Utils;

/**
 *
 */
class Formatter
{
    /** No instances */
    private function __construct() {}

    public static function dateDecade($datestr, $locale = 'en')
    {
        $dateParts = preg_split('/\-/', $datestr);
        if (empty($dateParts) || !is_numeric($dateParts[0])) {
            return '';
        }

        switch ($locale) {
            case 'de':
                $append = 'er';
                break;

            default:
                $append = 's';
        }

        return ($dateParts[0] - $dateParts[0] % 10) . $append;
    }

    /*
     * Split YYYY-MM-DD and remove the ones that are equal to 0
     */
    protected static function buildDatePartsReduced($datestr)
    {
        $datePartsReduced = [];
        if (is_null($datestr)) {
            return $datePartsReduced;
        }

        $dateParts = preg_split('/\-/', $datestr);

        for ($i = 0; $i < count($dateParts); $i++) {
            if (0 == $dateParts[$i]) {
                break;
            }
            $datePartsReduced[] = $dateParts[$i];
        }

        return $datePartsReduced;
    }

    public static function dateIncomplete($datestr, $locale = 'en', $ommit = '')
    {
        $datePartsReduced = self::buildDatePartsReduced($datestr);

        if (empty($datePartsReduced)) {
            return '';
        }

        $separator = '.';
        if ('en' == $locale && count($datePartsReduced) > 1) {
            $ret = [];
            if ('month' !== $ommit) {
                $dateObj = \DateTime::createFromFormat('!m', $datePartsReduced[1]);
                $monthName = $dateObj->format('M'); // M: Mar F: March
                $ret[] = $monthName;
            }
            if (count($datePartsReduced) > 2) {
                $ret[] = intval($datePartsReduced[2])  // day
                    . ('year' !== $ommit ? ',' : '')
                ;
            }
            if ('year' !== $ommit) {
                $ret[] = $datePartsReduced[0]; // year
            }

            return join(' ', $ret);
        }

        $datePartsReduced = array_reverse($datePartsReduced);

        return implode($separator, $datePartsReduced);
    }


    public static function daterangeIncomplete($datestrFrom, $datestrUntil, $locale = 'en')
    {
        if (empty($datestrUntil) || $datestrFrom === $datestrUntil) {
            return self::dateIncomplete($datestrFrom, $locale);
        }

        // from is shortened if check if year or year and month are the same
        $ommit = '';
        $fromReduced = self::buildDatePartsReduced($datestrFrom);
        $untilReduced = self::buildDatePartsReduced($datestrUntil);
        if (count($fromReduced) > 1 && count($untilReduced) > 1
            && $fromReduced[0] == $untilReduced[0]) {
            // year is equal
            $ommit = 'year';
            if (count($fromReduced) > 2 && count($untilReduced) > 2
                && $fromReduced[1] == $untilReduced[1]) {
                // year and month are equal
                $ommit = 'month';
            }
        }

        $from = self::dateIncomplete($datestrFrom, $locale, !empty($ommit) ? 'year' : '');
        $until = self::dateIncomplete($datestrUntil, $locale, 'month' === $ommit ? $ommit : '');

        return $from
            // . "\u{2012}" // PHP >= 7
            . hex2bin('e28092')
            . $until;
    }
}
