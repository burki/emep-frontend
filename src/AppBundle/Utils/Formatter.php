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

    public static function dateIncomplete($datestr, $locale = 'en')
    {
        $dateParts = preg_split('/\-/', $datestr);

        $dateParts_formatted = [];
        for ($i = 0; $i < count($dateParts); $i++) {
            if (0 == $dateParts[$i]) {
                break;
            }
            $dateParts_formatted[] = $dateParts[$i];
        }
        if (empty($dateParts_formatted)) {
            return '';
        }

        $separator = '.';
        if ('en' == $locale && count($dateParts_formatted) > 1) {
            $dateObj  = \DateTime::createFromFormat('!m', $dateParts_formatted[1]);
            $monthName = $dateObj->format('F'); // March
            $ret = [ $monthName ];
            if (count($dateParts_formatted) > 2) {
                $ret[] = $dateParts_formatted[2] . ','; // day
            }
            $ret[] = $dateParts_formatted[0]; // year
            return join(' ', $ret);
        }

        $dateParts_formatted = array_reverse($dateParts_formatted);

        return implode($separator, $dateParts_formatted);
    }
}
