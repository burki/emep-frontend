<?php

/**
 *
 * Shared methods to build up map
 *
 */

namespace AppBundle\Controller;

trait MapBuilderTrait
{
    /* TODO: move to shared helper */
    private function buildDisplayDate($row)
    {
        if (is_object($row)) {
            $row = [
                'displaydate' => $row->getDisplayDate(),
                'startdate' => $row->getStartDate(),
                'enddate' => $row->getEndDate(),
            ];
        }

        if (!empty($row['displaydate'])) {
            return $row['displaydate'];
        }

        return \AppBundle\Utils\Formatter::daterangeIncomplete($row['startdate'], $row['enddate']);
    }

    function processMapEntries($stmt, $entity, $defaultBounds = [
            [ -15, 120 ],
            [ 60, -120 ],
        ])
    {
        $latMin = $latMax = null;
        $longMin = $longMax = null;

        $maxDisplay = 'Person' == $entity ? 15 : 10;

        if ('Person' == $entity) {
            $subTitle = 'Places of Birth and Death';

            $values = [];
            while ($row = $stmt->fetch()) {
                foreach ([ 'birth', 'death'] as $type) {
                    $latitude = $row[$type . 'place_latitude'];
                    $longitude = $row[$type . 'place_longitude'];

                    if (is_null($latitude) || is_null($longitude)
                        || ($latitude == 0 && $longitude == 0))
                    {
                        continue;
                    }

                    if (is_null($latMin)) {
                        $latMin = $latMax = $latitude;
                        $longMin = $longMax = $longitude;
                    }
                    else {
                        if ($latitude < $latMin) {
                            $latMin = $latitude;
                        }
                        else if ($latitude > $latMax) {
                            $latMax = $latitude;
                        }

                        if ($longitude < $longMin) {
                            $longMin = $longitude;
                        }
                        else if ($longitude > $longMax) {
                            $longMax = $longitude;
                        }
                    }

                    $key = $latitude . ':' . $longitude;

                    if (!array_key_exists($key, $values)) {
                        $values[$key]  = [
                            'latitude' => (double)$latitude,
                            'longitude' => (double)$longitude,
                            'place' => sprintf('<a href="%s">%s</a>',
                                               htmlspecialchars($this->generateUrl('place-by-tgn', [
                                                    'tgn' => $row[$type . 'place_tgn'],
                                               ])),
                                               htmlspecialchars($row[$type . 'place'])),
                            'persons' => [],
                            'person_ids' => [ 'birth' => [], 'death' => [] ],
                        ];
                    }

                    if (!in_array($row['person_id'], $values[$key]['person_ids']['birth'])
                        && !in_array($row['person_id'], $values[$key]['person_ids']['death']))
                    {
                        $values[$key]['persons'][] = [
                            'id' => $row['person_id'],
                            'label' => sprintf('<a href="%s">%s</a>',
                                               htmlspecialchars($this->generateUrl('person', [
                                                   'id' => $row['person_id'],
                                               ])),
                                               htmlspecialchars($row['person'], ENT_COMPAT, 'utf-8')),
                        ];
                    }

                    $values[$key]['person_ids'][$type][] = $row['person_id'];
                }
            }

            // display
            $values_final = [];
            $max_count = 0;

            foreach ($values as $key => $value) {
                $idsByType = & $values[$key]['person_ids'];

                $buildRow = function ($entry) use ($idsByType) {
                    $ret = $entry['label'];

                    $append = '';
                    if (in_array($entry['id'], $idsByType['birth'])) {
                        $append .= '*';
                    }
                    if (in_array($entry['id'], $idsByType['death'])) {
                        $append .= '†';
                    }

                    return $ret . ('' !== $append ? ' ' . $append : '');
                };

                $countEntries = count($value['persons']);

                if ($countEntries <= $maxDisplay) {
                    $entry_list = implode('<br />', array_map($buildRow, $value['persons']));
                }
                else {
                    $entry_list = implode('<br />', array_map($buildRow, array_slice($value['persons'], 0, $maxDisplay - 1)))
                                . sprintf('<br />... (%d more)', $countEntries - ($maxDisplay - 1));
                }

                $values_final[] = [
                    $value['latitude'], $value['longitude'],
                    $value['place'],
                    $entry_list,
                    $count_birth = count($value['person_ids']['birth']),
                    $count_death = count($value['person_ids']['death'])
                ];

                if (($count = $count_birth + $count_death) > $max_count) {
                    $max_count = $count;
                }
            }
        }
        else {
            // Exhibition / Venue / Place
            $values = [];
            $values_country = [];
            $subTitle = ''; // 'Exhibition' == $entity ? 'Exhibitions' : 'Venues';

            while ($row = $stmt->fetch()) {
                if (empty($row['location_geo']) && $row['longitude'] == 0 && $row['latitude'] == 0) {
                    continue;
                }

                if (!empty($row['location_geo'])) {
                    list($latitude, $longitude) = preg_split('/\s*,\s*/', $row['location_geo'], 2);
                }
                else {
                    $latitude = $row['latitude'];
                    $longitude = $row['longitude'];
                }

                $key = $latitude . ':' . $longitude;
                if (!array_key_exists($key, $values)) {
                    if (is_null($latMin)) {
                        $latMin = $latMax = $latitude;
                        $longMin = $longMax = $longitude;
                    }
                    else {
                        if ($latitude < $latMin) {
                            $latMin = $latitude;
                        }
                        else if ($latitude > $latMax) {
                            $latMax = $latitude;
                        }

                        if ($longitude < $longMin) {
                            $longMin = $longitude;
                        }
                        else if ($longitude > $longMax) {
                            $longMax = $longitude;
                        }
                    }

                    $values[$key]  = [
                        'latitude' => (double)$latitude,
                        'longitude' => (double)$longitude,
                        'place' => sprintf('<a href="%s">%s</a>',
                                           $place_url = htmlspecialchars($this->generateUrl('place-by-tgn', [
                                                'tgn' => $row['place_tgn'],
                                           ])),
                                           htmlspecialchars($row['place'])),
                        'entries' => [],
                        'url_more' => '',
                    ];

                    if (in_array($entity, [ 'Venue' ])) {
                        $values[$key]['url_more'] = $place_url . '#venues';
                    }
                    else if (in_array($entity, [ 'Exhibition' ])) {
                        $values[$key]['url_more'] = $place_url . '#exhibitions';
                    }
                    /* TODO: Org. Body Tab on Place */
                }

                if (in_array($entity, [ 'Venue', 'Organizer' ])) {
                    $values[$key]['entries'][] =
                        sprintf('<a href="%s">%s</a>',
                                htmlspecialchars($this->generateUrl('location', [
                                    'id' => $row['location_id'],
                                ])),
                                htmlspecialchars(\AppBundle\Utils\SearchListBuilder::buildLocationNameListing($row))
                        );
                }
                else if ('Exhibition' == $entity) {
                    $values[$key]['entries'][] =
                        sprintf('<a href="%s">%s</a> at <a href="%s">%s</a> (%s)',
                                htmlspecialchars($this->generateUrl('exhibition', [
                                    'id' => $row['exhibition_id'],
                                ])),
                                htmlspecialchars(\AppBundle\Utils\SearchListBuilder::buildExhibitionTitleListing($row)),
                                htmlspecialchars($this->generateUrl('location', [
                                    'id' => $row['location_id'],
                                ])),
                                htmlspecialchars(\AppBundle\Utils\SearchListBuilder::buildLocationNameListing($row)),
                                $this->buildDisplayDate($row)
                        );
                }
                else if ('Place' == $entity) {
                    $values[$key]['count_exhibition'] = $row['count_exhibition'];
                }
            }

            $values_final = [];
            foreach ($values as $key => $value) {
                $countEntries = array_key_exists('count_exhibition', $value)
                    ? (int)$value['count_exhibition'] : count($value['entries']);

                if (count($value['entries']) <= $maxDisplay) {
                    $entry_list = implode('<br />', $value['entries']);
                }
                else {
                    $more = sprintf('%d more', $countEntries - ($maxDisplay - 1));

                    if (!empty($value['url_more'])) {
                        $more = sprintf('<a href="%s">%s</a>',
                                        $value['url_more'], $more);
                    }

                    $entry_list = implode('<br />', array_slice($value['entries'], 0, $maxDisplay - 1))
                                . sprintf('<br />... (%s)', $more);
                }

                $values_final[] = [
                    $value['latitude'], $value['longitude'],
                    $value['place'],
                    $entry_list,
                    $countEntries,
                ];
            }
        }

        // set bounds or center
        if (is_null($latMin) || is_null($latMax) || is_null($longMin) || is_null($longMax)) {
            $bounds = $defaultBounds;
        }
        else {
            if ($latMin != $latMax || $longMin != $longMax) {
                $bounds = [
                    [ $latMin, $longMin  ],
                    [ $latMax, $longMax,  ],
                ];
            }
            else {
                // center
                $bounds = [ $latMin, $longMin  ];
            }
        }

        return [
            'subTitle' => $subTitle,
            'data' => json_encode($values_final),
            'maxCount' => isset($max_count) ? $max_count : null,
            'bounds' => $bounds,
        ];
    }
}
