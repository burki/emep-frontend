<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

/**
 *
 */
class MapController extends Controller
{
    /**
     * @Route("/person/by-place", name="person-by-place")
     */
    public function birthDeathPlaces()
    {
        $em = $this->getDoctrine()->getEntityManager();
        $dbconn = $em->getConnection();
        $querystr = "SELECT Person.id AS person_id, Person.lastname, Person.firstname, birthdate, deathdate, COALESCE(Geoname.name_variant, Geoname.name) AS place, Geoname.tgn, latitude, longitude"
                  . " FROM Person"
                  . " INNER JOIN Geoname ON Person.birthplace_tgn=Geoname.tgn"
                  . " WHERE"
                  . " Person.status <> -1"
                  . " ORDER BY tgn, Person.lastname, Person.firstname"
                  ;
        $stmt = $dbconn->query($querystr);
        $values = [];
        while ($row = $stmt->fetch()) {
            if ($row['longitude'] == 0 && $row['latitude'] == 0) {
                continue;
            }
            $key = $row['latitude'] . ':' . $row['longitude'];

            if (!array_key_exists($key, $values)) {
                $values[$key]  = [
                    'latitude' => (double)$row['latitude'],
                    'longitude' => (double)$row['longitude'],
                    'place' => sprintf('<a href="%s">%s</a>',
                                       htmlspecialchars($this->generateUrl('place-by-tgn', [
                                            'tgn' => $row['tgn'],
                                       ])),
                                       htmlspecialchars($row['place'])),
                    'persons' => [],
                ];
            }
            $values[$key]['persons'][] = sprintf('<a href="%s">%s</a>',
                                                 htmlspecialchars($this->generateUrl('person', [
                                                    'id' => $row['person_id'],
                                                ])),
                                                $row['lastname'] . ', ' . $row['firstname']);
        }
        $values_final = [];
        foreach ($values as $key => $value) {
            $values_final[] = [
                $value['latitude'], $value['longitude'],
                $value['place'],
                implode('<br />', $value['persons']),
                count($value['persons']),
            ];
        }

        // display
        return $this->render('Map/person-by-place.html.twig', [
            'pageTitle' => 'Artists by Birth Place',
            'data' => json_encode($values_final),
            'bounds' => [
                [ 60, -120 ],
                [ -15, 120 ],
            ],
        ]);
    }

    /**
     * @Route("/exhibition/by-place", name="exhibition-by-place")
     * @Route("/location/by-place", name="location-by-place")
     */
    public function exhibitionByPlace(Request $request)
    {
        $em = $this->getDoctrine()->getEntityManager();
        $dbconn = $em->getConnection();

        $route = $request->get('_route');
        if ('location-by-place' == $route) {
            $maxDisplay = 15;
            $querystr = "SELECT Location.id AS location_id, Location.name AS location_name, COALESCE(Geoname.name_variant, Geoname.name) AS place, Geoname.tgn, Geoname.latitude, Geoname.longitude"
                      . " FROM Location"
                      . " INNER JOIN Geoname ON Geoname.tgn=Location.place_tgn"
                      . " WHERE"
                      . " Location.status <> -1"
                      . " ORDER BY tgn, location_name"
                      ;
        }
        else {
            $maxDisplay = 10;
            $querystr = "SELECT Exhibition.id AS exhibition_id, Exhibition.title, startdate, enddate, displaydate, Location.id AS location_id, Location.name AS location_name, COALESCE(Geoname.name_variant, Geoname.name) AS place, Geoname.tgn, Geoname.latitude, Geoname.longitude"
                      . " FROM Exhibition"
                      . " INNER JOIN Location ON Location.id=Exhibition.id_location"
                      . " INNER JOIN Geoname ON Geoname.tgn=Location.place_tgn"
                      . " WHERE"
                      . " Exhibition.status <> -1"
                      . " ORDER BY tgn, location_name, Exhibition.startdate, Exhibition.title"
                      ;
        }

        $stmt = $dbconn->query($querystr);
        $values = [];
        $values_country = [];
        $displayhelper = new ExhibitionDisplayHelper();
        while ($row = $stmt->fetch()) {
            if ($row['longitude'] == 0 && $row['latitude'] == 0) {
                continue;
            }
            $key = $row['latitude'] . ':' . $row['longitude'];

            if (!array_key_exists($key, $values)) {
                $values[$key]  = [
                    'latitude' => (double)$row['latitude'],
                    'longitude' => (double)$row['longitude'],
                    'place' => sprintf('<a href="%s">%s</a>',
                                       htmlspecialchars($this->generateUrl('place-by-tgn', [
                                            'tgn' => $row['tgn'],
                                       ])),
                                       htmlspecialchars($row['place'])),
                    'exhibitions' => [],
                ];
            }
            if ('location-by-place' == $route) {
                $values[$key]['exhibitions'][] =
                    sprintf('<a href="%s">%s</a>',
                            htmlspecialchars($this->generateUrl('location', [
                                'id' => $row['location_id'],
                            ])),
                            htmlspecialchars($row['location_name'])
                    );
            }
            else {
                $values[$key]['exhibitions'][] =
                    sprintf('<a href="%s">%s</a> at <a href="%s">%s</a> (%s)',
                            htmlspecialchars($this->generateUrl('exhibition', [
                                'id' => $row['exhibition_id'],
                            ])),
                            htmlspecialchars($row['title']),
                            htmlspecialchars($this->generateUrl('location', [
                                'id' => $row['location_id'],
                            ])),
                            htmlspecialchars($row['location_name']),
                            $displayhelper->buildDisplayDate($row)
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

        // display
        return $this->render('Map/person-by-place.html.twig', [
            'data' => json_encode($values_final),
            'bounds' => [
                [ 60, -120 ],
                [ -15, 120 ],
            ],
        ]);
    }
}

/* TODO: move to general util */
class ExhibitionDisplayHelper
{
  function buildDisplayDate ($row) {
    if (!empty($row['displaydate'])) {
      return $row['displaydate'];
    }
    return $this->formatDateRange($row['startdate'],
                                  $row['enddate']);
  }

  function formatDateIncomplete ($date, $joiner = '.') {
    $ret = [];
    $parts = date_parse($date);
    foreach ([ 'day', 'month', 'year' ] as $part) {
      if (0 != $parts[$part]) {
        $ret[] = sprintf('year' == $part ? '%04d' : '%02d',
                         $parts[$part]);
      }
    }
    return implode($joiner, $ret);
  }

  function formatDateRange ($date_from, $date_until) {
    // TODO: make configurable, multilingual
    $joiner = '-';
    $date_format = 'dd.MM.yyyy';

    if (is_object($date_from)) {
      $from = $date_from->toString($date_format);
    }
    else {
      $from = $this->formatDateIncomplete($date_from);
    }

    if (is_object($date_from)) {
      $until = $date_until->toString($date_format);
    }
    else {
      $until = $this->formatDateIncomplete($date_until);
    }

    if ($from == $until || empty($until)) {
      return $from;
    }
    else {
      return implode($joiner, [ $from, $until ]);
    }
  }

}
