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
        return $this->render('Map/place-map.html.twig', [
            'pageTitle' => 'Artists by Birth Place',
            'data' => json_encode($values_final),
            'disableClusteringAtZoom' => 7,
            'bounds' => [
                [ 60, -120 ],
                [ -15, 120 ],
            ],
        ]);
    }

    /**
     * @Route("/exhibition/by-place", name="exhibition-by-place")
     * @Route("/location/by-place", name="location-by-place")
     * @Route("/work/by-place", name="item-by-place")
     * @Route("/place/map", name="place-map")
     */
    public function exhibitionByPlace(Request $request)
    {
        $em = $this->getDoctrine()->getEntityManager();
        $dbconn = $em->getConnection();

        $route = $request->get('_route');
        $persons = null;
        $maxDisplay = 10;
        $disableClusteringAtZoom = 5;

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
        else if ('place-map' == $route) {
            $disableClusteringAtZoom = 7;

            $querystr = "SELECT COALESCE(Geoname.name_variant, Geoname.name) AS place, Geoname.tgn, Geoname.latitude, Geoname.longitude"
                      . " FROM Geoname"
                      . " WHERE"
                      . " Geoname.type IN ('inhabited places')"
                      ;

        }
        else {
            $querystr = "SELECT DISTINCT Exhibition.id AS exhibition_id, Exhibition.title, startdate, enddate, Exhibition.displaydate AS displaydate, Location.id AS location_id, Location.name AS location_name, COALESCE(Geoname.name_variant, Geoname.name) AS place, Geoname.tgn, Geoname.latitude, Geoname.longitude"
                      . " FROM Exhibition"
                      . " INNER JOIN Location ON Location.id=Exhibition.id_location"
                      . " INNER JOIN Geoname ON Geoname.tgn=Location.place_tgn";

            if ('item-by-place' == $route) {
                $querystr .= ' INNER JOIN ItemExhibition ON ItemExhibition.id_exhibition=Exhibition.id'
                           . ' INNER JOIN Item ON ItemExhibition.id_item=Item.id AND Item.status <> -1'
                           ;

                $person = $request->get('person');
                if (!empty($person) && intval($person) > 0) {
                    $querystr .= ' INNER JOIN ItemPerson ON Item.id=ItemPerson.id_item'
                               . sprintf(' AND ItemPerson.id_person=%d', intval($person));
                }

                $qbPerson = $this->getDoctrine()
                        ->getManager()
                        ->createQueryBuilder();

                $qbPerson->select('P')
                    ->distinct()
                    ->from('AppBundle:Person', 'P')
                    ->innerJoin('AppBundle:ItemPerson', 'IP', 'WITH', 'IP.person=P')
                    ->innerJoin('AppBundle:Item', 'I', 'WITH', 'IP.item=I')
                    ->innerJoin('I.exhibitions', 'E')
                    ->where('I.status <> -1')
                    ->orderBy('P.familyName');
                $persons = $qbPerson->getQuery()->getResult();
            }

            $querystr
                     .= " WHERE"
                      . " Exhibition.status <> -1"
                      . " ORDER BY tgn, Exhibition.startdate, location_name, Exhibition.title"
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
            else if ('place-map' != $route) {
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
                'place-map' == $route ? 1 : count($value['exhibitions']),
            ];
        }

        // display
        return $this->render('Map/place-map.html.twig', [
            'data' => json_encode($values_final),
            'disableClusteringAtZoom' => $disableClusteringAtZoom,
            'bounds' => [
                [ 60, -120 ],
                [ -15, 120 ],
            ],
            'persons' => $persons,
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

        return \AppBundle\Utils\Formatter::daterangeIncomplete($row['startdate'], $row['enddate']);
    }
}
