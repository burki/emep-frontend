<?php

/**
 *
 * Shared methods to build up statistics
 *
 */

namespace AppBundle\Controller;

trait MapBuilderTrait
{
    /* TODO: move to shared helper */
    private function buildDisplayDate($row)
    {
        if (!empty($row['displaydate'])) {
            return $row['displaydate'];
        }

        return \AppBundle\Utils\Formatter::daterangeIncomplete($row['startdate'], $row['enddate']);
    }

    function processMapEntries($stmt, $entity)
    {
        $maxDisplay = 'Person' == $entity ? 15 : 10;

        if ('Person' == $entity) {
            $subTitle = 'Birth and Death Places';

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
                        $append .= '+';
                    }

                    return $ret . ('' !== $append ? ' ' . $append : '');
                };

                $count_entries = count($value['persons']);

                if ($count_entries <= $maxDisplay) {
                    $entry_list = implode('<br />', array_map($buildRow, $value['persons']));
                }
                else {
                    $entry_list = implode('<br />', array_map($buildRow, array_slice($value['persons'], 0, $maxDisplay - 1)))
                                . sprintf('<br />... (%d more)', $count_entries - $maxDisplay);
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
            // Exhibition / Venue
            $values = [];
            $values_country = [];
            $subTitle = 'Exhibition' == $entity ? 'Exhibitions' : 'Venues';

            while ($row = $stmt->fetch()) {
                if (empty($row['location_geo']) && $row['longitude'] == 0 && $row['latitude'] == 0) {
                    continue;
                }
                $key = $row['latitude'] . ':' . $row['longitude'];
                if (!empty($row['location_geo'])) {
                    list($latitude, $longitude) = preg_split('/\s*,\s*/', $row['location_geo'], 2);
                    $key = $latitude . ':' . $longitude;
                }
                else {
                    $latitude = $row['latitude'];
                    $longitude = $row['longitude'];
                }

                if (!array_key_exists($key, $values)) {
                    $values[$key]  = [
                        'latitude' => (double)$latitude,
                        'longitude' => (double)$longitude,
                        'place' => sprintf('<a href="%s">%s</a>',
                                           htmlspecialchars($this->generateUrl('place-by-tgn', [
                                                'tgn' => $row['place_tgn'],
                                           ])),
                                           htmlspecialchars($row['place'])),
                        'exhibitions' => [],
                    ];
                }

                if (in_array($entity, [ 'Venue', 'Organizer' ])) {
                    $values[$key]['exhibitions'][] =
                        sprintf('<a href="%s">%s</a>',
                                htmlspecialchars($this->generateUrl('location', [
                                    'id' => $row['location_id'],
                                ])),
                                htmlspecialchars($row['location'])
                        );
                }
                else if ('Exhibition' == $entity) {
                    $values[$key]['exhibitions'][] =
                        sprintf('<a href="%s">%s</a> at <a href="%s">%s</a> (%s)',
                                htmlspecialchars($this->generateUrl('exhibition', [
                                    'id' => $row['exhibition_id'],
                                ])),
                                htmlspecialchars($row['exhibition']),
                                htmlspecialchars($this->generateUrl('location', [
                                    'id' => $row['location_id'],
                                ])),
                                htmlspecialchars($row['location']),
                                $this->buildDisplayDate($row)
                        );
                }
            }

            $values_final = [];
            foreach ($values as $key => $value) {
                $count_entries = count($value['exhibitions']);
                if ($count_entries <= $maxDisplay) {
                    $entry_list = implode('<br />', $value['exhibitions']);
                }
                else {
                    $entry_list = implode('<br />', array_slice($value['exhibitions'], 0, $maxDisplay - 1))
                                . sprintf('<br />... (%d more)', $count_entries - $maxDisplay);
                }
                $values_final[] = [
                    $value['latitude'], $value['longitude'],
                    $value['place'],
                    $entry_list,
                    count($value['exhibitions']),
                ];
            }
        }

        return [
            'subTitle' => $subTitle,
            'data' => json_encode($values_final),
            'maxCount' => isset($max_count) ? $max_count : null,
        ];
    }
}
