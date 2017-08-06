<?php

namespace AppBundle\Utils;

/**
 * Get biographical information from Wikidata SPARQL-Service
 */

class BiographicalWikidata
{
    const SPARQL_URL = 'https://query.wikidata.org/bigdata/namespace/wdq/sparql';

    static $KEY_MAP = [
        19 => 'placeOfBirth',
        20 => 'placeOfDeath',
        21 => 'sex',
        569 => 'dateOfBirth',
        570 => 'dateOfDeath',
        214 => 'viaf',
        227 => 'gnd',
        244 => 'lc_naf',
        245 => 'ulan',
    ];

    public static function fetchByUlan($ulan, $locale)
    {
        return self::fetchByProperty(245, $ulan, $locale);
    }

    public static function fetchByGnd($gnd, $locale)
    {
        return self::fetchByProperty(227, $gnd, $locale);
    }

    protected static function fetchByProperty($property, $value, $locale = 'en')
    {
        $bio = new BiographicalWikidata();
        if (array_key_exists($property, self::$KEY_MAP)) {
            $key = self::$KEY_MAP[$property];
            $bio->$key = $value;
        }

        $sparql = new \EasyRdf_Sparql_Client(self::SPARQL_URL);

        $query = <<<EOT
SELECT ?item ?itemLabel
    ?viaf ?gnd ?ulan
    ?sexLabel
    ?birthDate ?birthPlaceLabel
    ?deathDate ?deathPlaceLabel
    (group_concat(?citizenship;separator="|") as ?citizenships)
WHERE
{
    BIND("{$value}" as ?queryby)

    ?item wdt:P31 wd:Q5;
  	wdt:P{$property} ?queryby;
        wdt:P27/wdt:P297 ?citizenship.

    OPTIONAL {
      ?item wdt:P214 ?viaf.
    }
    OPTIONAL {
      ?item wdt:P227 ?gnd.
    }
    OPTIONAL {
      ?item wdt:P245 ?ulan.
    }
    OPTIONAL {
      ?item wdt:P21 ?sex
    }
    OPTIONAL {
      ?item wdt:P569 ?birthDate.
    }
    OPTIONAL {
      ?item wdt:P19 ?birthPlace.
    }
    OPTIONAL {
      ?item wdt:P570 ?deathDate.
    }
    OPTIONAL {
      ?item wdt:P20 ?deathPlace.
    }

    SERVICE wikibase:label { bd:serviceParam wikibase:language "{$locale}" }
} GROUP BY ?item ?itemLabel ?viaf ?gnd ?ulan
?sex ?sexLabel
?birthDate ?birthPlace ?birthPlaceLabel
?deathDate ?deathPlace ?deathPlaceLabel
EOT;

        $result = $sparql->query($query);
        if (count($result) > 0) {
            foreach ($result as $row) {
                foreach ([
                    /*
                    'name' => 'preferredName',
                    'description' => 'biographicalInformation',
                    */
                    'item' => 'identifier',
                    'viaf' => 'viaf',
                    'gnd' => 'gnd',
                    'ulan' => 'ulan',

                    'sexLabel' => 'gender',
                    /*
                    'citizenships' => 'nationality',
                    */
                    'birthDate' => 'dateOfBirth',
                    'birthPlaceLabel' => 'placeOfBirth',
                    'deathDate' => 'dateOfDeath',
                    'deathPlaceLabel' => 'placeOfDeath',
                ] as $src => $target)
                {
                    if (property_exists($row, $src)) {
                        $property = $row->$src;
                        $value = (string)$property;
                        if (in_array($target, [ 'nationality' ])) {
                            $value = self::mapNationality($value);
                        }
                        else if (in_array($target, [ 'gender' ])) {
                            if ('male' == $value) {
                                $value = 'M';
                            }
                            else if ('female' == $value) {
                                $value = 'F';
                            }
                            else {
                                die('TODO: handle ' . $value);
                                unset($value);
                            }
                        }
                        else if (preg_match('/Label$/', $src) && preg_match('/^Q\d+/', $value)) {
                            // unresolved labels
                            unset($value);
                        }
                        if (isset($value)) {
                            $bio->$target = $value;
                        }
                    }
                }
                /*
                if (!empty($bio->preferredName)) {
                    $parts = preg_split('/,\s/', $bio->preferredName, 2);
                    if (count($parts) == 2) {
                        $bio->surname = $parts[0];
                        $bio->forename = $parts[1];
                    }
                }
                */

                break; // only pickup first $result
            }
        }

        return $bio;
    }

    var $identifier = null;
    var $gnd = null;
    var $viaf = null;
    var $lc_naf = null;
    var $ulan = null;
    var $preferredName;
    var $gender;
    var $academicTitle;
    var $dateOfBirth;
    var $placeOfBirth;
    var $placeOfResidence;
    var $dateOfDeath;
    var $placeOfDeath;
}
