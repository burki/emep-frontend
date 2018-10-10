<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

/**
 *
 */
class MapController
extends CrudController
{
    /**
     * @Route("/person/by-place", name="person-by-place")
     */

    public function birthDeathPlacesIndex($countriesQuery, $genderQuery, $stringQuery)
    {
        $maxDisplay = 15;

        // $route = $request->get('_route');

        $em = $this->getDoctrine()->getEntityManager();
        $dbconn = $em->getConnection();

        $queryTemplate = "SELECT '%s' AS type, Person.id AS person_id, lastname, firstname, birthdate, deathdate, COALESCE(Geoname.name_variant, Geoname.name) AS place, Geoname.tgn AS tgn, latitude, longitude"
            . " FROM Person"
            . " INNER JOIN Geoname ON Person.%splace_tgn=Geoname.tgn"
            . " WHERE Person.status <> -1";


        $andWhere = '';


        $andWhere = " AND ". StatisticsController::getArrayQueryString('Person', 'country', $countriesQuery, 'Person.status <> -1');
        $andWhere .= " AND ". StatisticsController::getArrayQueryString('Person', 'sex', $genderQuery, 'Person.status <> -1');
        $stringQueryPart = " " . StatisticsController::getStringQueryForArtists($stringQuery, 'fullname');

        echo " " . $andWhere;


        // echo $testQuery;
        $queryTemplate .= $andWhere;



        $unionParts = [];

        foreach ([ 'birth', 'death' ] as $key) {
            $unionParts[] = sprintf($queryTemplate,
                $key, $key);
        }

        $unionParts[0] .= $stringQueryPart;
        $unionParts[1] .= $stringQueryPart;

        $querystr = join(' UNION ', $unionParts)
            . " ORDER BY lastname, firstname, person_id"
        ;



        // $querystr .= " WHERE " . $testQuery;

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
                    'person_ids' => [ 'birth' => [], 'death' => [] ],
                ];
            }

            if (!in_array($row['person_id'], $values[$key]['person_ids']['birth'])
                && !in_array($row['person_id'], $values[$key]['person_ids']['death']))
            {
                $values[$key]['persons'][] = sprintf('<a href="%s">%s</a>',
                    htmlspecialchars($this->generateUrl('person', [
                        'id' => $row['person_id'],
                    ])),
                    $row['lastname'] . ', ' . $row['firstname']);
            }

            $values[$key]['person_ids'][$row['type']][] = $row['person_id'];
        }

        $values_final = [];
        $max_count = 0;
        foreach ($values as $key => $value) {
            $count_entries = count($value['persons']);
            if ($count_entries <= $maxDisplay) {
                $entry_list = implode('<br />', $value['persons']);
            }
            else {
                $entry_list = implode('<br />', array_slice($value['persons'], 0, $maxDisplay - 1))
                    . sprintf('<br />... (%d more)', $count_entries - $maxDisplay);
            }

            $values_final[] = [
                $value['latitude'], $value['longitude'],
                $value['place'],
                $entry_list,
                $count = count($value['person_ids']['birth']) + count($value['person_ids']['death']),
            ];

            if ($count > $max_count) {
                $max_count = $count;
            }
        }

        // display
        return $this->render('Map/place-map-index.html.twig', [
            //'pageTitle' => 'Birth and Death Places',
            'data' => json_encode($values_final),
            'disableClusteringAtZoom' => 7,
            // 'maxCount' => $max_count,
            //'showHeatMap' => true,
            'markerStyle' => 'circle',
            'bounds' => [
                [ 60, -120 ],
                [ -15, 120 ],
            ],
        ]);

    }

    public function birthDeathPlaces(Request $request)
    {
        $maxDisplay = 15;

        $route = $request->get('_route');

        $em = $this->getDoctrine()->getEntityManager();
        $dbconn = $em->getConnection();

        $queryTemplate = "SELECT '%s' AS type, Person.id AS person_id, lastname, firstname, birthdate, deathdate, COALESCE(Geoname.name_variant, Geoname.name) AS place, Geoname.tgn AS tgn, latitude, longitude"
                  . " FROM Person"
                  . " INNER JOIN Geoname ON Person.%splace_tgn=Geoname.tgn"
                  . " WHERE Person.status <> -1";

        $unionParts = [];

        foreach ([ 'birth', 'death' ] as $key) {
            $unionParts[] = sprintf($queryTemplate,
                                    $key, $key);

        }
        $querystr = join(' UNION ', $unionParts)
                  . " ORDER BY lastname, firstname, person_id"
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
                    'person_ids' => [ 'birth' => [], 'death' => [] ],
                ];
            }

            if (!in_array($row['person_id'], $values[$key]['person_ids']['birth'])
                && !in_array($row['person_id'], $values[$key]['person_ids']['death']))
            {
                $values[$key]['persons'][] = sprintf('<a href="%s">%s</a>',
                                                     htmlspecialchars($this->generateUrl('person', [
                                                        'id' => $row['person_id'],
                                                    ])),
                                                    $row['lastname'] . ', ' . $row['firstname']);
            }

            $values[$key]['person_ids'][$row['type']][] = $row['person_id'];
        }

        $values_final = [];
        $max_count = 0;
        foreach ($values as $key => $value) {
            $count_entries = count($value['persons']);
            if ($count_entries <= $maxDisplay) {
                $entry_list = implode('<br />', $value['persons']);
            }
            else {
                $entry_list = implode('<br />', array_slice($value['persons'], 0, $maxDisplay - 1))
                            . sprintf('<br />... (%d more)', $count_entries - $maxDisplay);
            }

            $values_final[] = [
                $value['latitude'], $value['longitude'],
                $value['place'],
                $entry_list,
                $count = count($value['person_ids']['birth']) + count($value['person_ids']['death']),
            ];

            if ($count > $max_count) {
                $max_count = $count;
            }
        }

        // display
        return $this->render('Map/place-map-index.html.twig', [
            'pageTitle' => 'Birth and Death Places',
            'data' => json_encode($values_final),
            'disableClusteringAtZoom' => 7,
            'maxCount' => $max_count,
            'showHeatMap' => true,
            'markerStyle' => 'circle',
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
        $form = null;
        $filterData = [];
        $parameters = [];
        $parametersTypes = [];

        if (in_array($route, [ 'location-by-place', 'exhibition-by-place' ])) {
            $types = 'exhibition-by-place' == $route
                ? $this->buildOrganizerTypes()
                : $this->buildVenueTypes();
            $form = $this->get('form.factory')->create(\AppBundle\Filter\MapFilterType::class, [
                'type_choices' => array_combine($types, $types),
                'type_label' => 'exhibition-by-place' == $route
                    ? 'Type of Organizing Body' : 'Type of Venue',
            ]);

            if ($request->getMethod() == 'POST') {
                $form->handleRequest($request);
                if ($form->isSubmitted() && $form->isValid()) {
                    $filterData = $form->getData();
                }
            }
        }

        if ('location-by-place' == $route) {
            $disableClusteringAtZoom = 10;
            $maxDisplay = 15;

            $andWhere = '';
            if (array_key_exists('location-type', $filterData) && !empty($filterData['location-type'])) {
                $andWhere .= ' AND Location.type IN(?)';
                $parameters[] = $filterData['location-type'];
                $parametersTypes[] = \Doctrine\DBAL\Connection::PARAM_STR_ARRAY;
            }

            $querystr = "SELECT Location.id AS location_id, Location.name AS location_name, Location.place_geo AS location_geo, COALESCE(Geoname.name_variant, Geoname.name) AS place, Geoname.tgn, Geoname.latitude, Geoname.longitude"
                      . " FROM Location"
                      . " INNER JOIN Geoname ON Geoname.tgn=Location.place_tgn"
                      . " WHERE"
                      . " Location.status <> -1 AND 0 = (Location.flags & 256)"
                      . $andWhere
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

            $andWhere = '';
            if (array_key_exists('location-type', $filterData) && !empty($filterData['location-type'])) {
                $andWhere .= ' AND Exhibition.organizer_type IN(?)';
                $parameters[] = $filterData['location-type'];
                $parametersTypes[] = \Doctrine\DBAL\Connection::PARAM_STR_ARRAY;
            }

            $querystr
                     .= " WHERE"
                      . " Exhibition.status <> -1"
                      . $andWhere
                      . " ORDER BY tgn, Exhibition.startdate, location_name, Exhibition.title"
                      ;
        }


        $stmt = $dbconn->executeQuery($querystr, $parameters, $parametersTypes);
        $values = [];
        $values_country = [];
        $displayhelper = new ExhibitionDisplayHelper();
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
            'filter' => is_null($form) ? null : $form->createView(),
            'disableClusteringAtZoom' => $disableClusteringAtZoom,
            'bounds' => [
                [ 60, -120 ],
                [ -15, 120 ],
            ],
            'markerStyle' => 'exhibition-by-place' == $route ? 'circle' : 'default',
            'persons' => $persons,
        ]);
    }

    public function exhibitionByPlacePart($countriesQuery, $locationTypeQuery, $stringQuery)
    {


        $em = $this->getDoctrine()->getEntityManager();
        $dbconn = $em->getConnection();

        $persons = null;
        $maxDisplay = 10;
        $disableClusteringAtZoom = 5;
        $form = null;
        $filterData = [];
        $parameters = [];
        $parametersTypes = [];




        /*if (in_array($route, [ 'location-by-place', 'exhibition-by-place' ])) {
            $types = 'exhibition-by-place' == $route
                ? $this->buildOrganizerTypes()
                : $this->buildVenueTypes();
            $form = $this->get('form.factory')->create(\AppBundle\Filter\MapFilterType::class, [
                'type_choices' => array_combine($types, $types),
                'type_label' => 'exhibition-by-place' == $route
                    ? 'Type of Organizing Body' : 'Type of Venue',
            ]);


        }*/

        $querystr = "SELECT DISTINCT Exhibition.id AS exhibition_id, Exhibition.title, startdate, enddate, Exhibition.displaydate AS displaydate, Location.id AS location_id, Location.name AS location_name, COALESCE(Geoname.name_variant, Geoname.name) AS place, Geoname.tgn, Geoname.latitude, Geoname.longitude"
            . " FROM Exhibition"
            . " INNER JOIN Location ON Location.id=Exhibition.id_location"
            . " INNER JOIN Geoname ON Geoname.tgn=Location.place_tgn";

        /*if ('item-by-place' == $route) {
            $querystr .= ' INNER JOIN ItemExhibition ON ItemExhibition.id_exhibition=Exhibition.id'
                . ' INNER JOIN Item ON ItemExhibition.id_item=Item.id AND Item.status <> -1'
            ;

            // $person = $request->get('person');
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
        }*/

        $andWhere = '';
        if (array_key_exists('location-type', $filterData) && !empty($filterData['location-type'])) {
            $andWhere .= ' AND Exhibition.organizer_type IN(?)';
            $parameters[] = $filterData['location-type'];
            $parametersTypes[] = \Doctrine\DBAL\Connection::PARAM_STR_ARRAY;
        }

        if($countriesQuery !== '' and $countriesQuery !== null){
            $andWhere .= " AND ". StatisticsController::getCountryQueryString('Location', 'Exhibition', 'country', $countriesQuery);
        }

        $andWhere .= " AND ". StatisticsController::getArrayQueryString('Location', 'type', $locationTypeQuery, 'Exhibition.status <> -1');
        $andWhere .= StatisticsController::getStringQueryForExhibitions($stringQuery, 'long');



        /* if($countriesQuery !== '' and $countriesQuery !== null){
            $andWhere .= " AND ". StatisticsController::getArrayQueryString('Exhibition', 'organizer_type', $locationTypeQuery, 'Exhibition.status <> -1');
        }*/



        $querystr
            .= " WHERE"
            . " Exhibition.status <> -1"
            . $andWhere
            . " ORDER BY tgn, Exhibition.startdate, location_name, Exhibition.title"
        ;



        $stmt = $dbconn->executeQuery($querystr, $parameters, $parametersTypes);
        $values = [];
        $values_country = [];
        $displayhelper = new ExhibitionDisplayHelper();
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
                            'tgn' => $row['tgn'],
                        ])),
                        htmlspecialchars($row['place'])),
                    'exhibitions' => [],
                ];
            }


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
        return $this->render('Map/place-map-index.html.twig', [
            'data' => json_encode($values_final),
            'filter' => is_null($form) ? null : $form->createView(),
            'disableClusteringAtZoom' => $disableClusteringAtZoom,
            'bounds' => [
                [ 60, -120 ],
                [ -15, 120 ],
            ],
            'markerStyle' => 'exhibition-by-place' == 'default',
            'persons' => $persons,
        ]);
    }

    public function exhibitionByPlaceIndexPart($countriesQuery, $organizerTypeQuery, $stringQuery)
    {
        $em = $this->getDoctrine()->getEntityManager();
        $dbconn = $em->getConnection();

        // $route = $request->get('_route');
        $persons = null;
        $maxDisplay = 10;
        $disableClusteringAtZoom = 5;
        $form = null;
        $filterData = [];
        $parameters = [];
        $parametersTypes = [];

        /* if (in_array($route, [ 'location-by-place', 'exhibition-by-place' ])) {
            $types = 'exhibition-by-place' == $route
                ? $this->buildOrganizerTypes()
                : $this->buildVenueTypes();
            $form = $this->get('form.factory')->create(\AppBundle\Filter\MapFilterType::class, [
                'type_choices' => array_combine($types, $types),
                'type_label' => 'exhibition-by-place' == $route
                    ? 'Type of Organizing Body' : 'Type of Venue',
            ]);

            if ($request->getMethod() == 'POST') {
                $form->handleRequest($request);
                if ($form->isSubmitted() && $form->isValid()) {
                    $filterData = $form->getData();
                }
            }
        } */


        $querystr = "SELECT DISTINCT Exhibition.id AS exhibition_id, Exhibition.title, startdate, enddate, Exhibition.displaydate AS displaydate, Location.id AS location_id, Location.name AS location_name, COALESCE(Geoname.name_variant, Geoname.name) AS place, Geoname.tgn, Geoname.latitude, Geoname.longitude"
            . " FROM Exhibition"
            . " INNER JOIN Location ON Location.id=Exhibition.id_location"
            . " INNER JOIN Geoname ON Geoname.tgn=Location.place_tgn";

        $andWhere = '';


        $andWhere = " AND " .  StatisticsController::getCountryQueryString('Location', 'Exhibition', 'country', $countriesQuery);
        $andWhere .= " AND ". StatisticsController::getArrayQueryString('Exhibition', 'organizer_type', $organizerTypeQuery, 'Exhibition.status <> -1');
        $andWhere .= StatisticsController::getStringQueryForExhibitions($stringQuery, 'long');


        $querystr
            .= " WHERE"
            . " Exhibition.status <> -1"
            . $andWhere
            . " ORDER BY tgn, Exhibition.startdate, location_name, Exhibition.title"
        ;


        $stmt = $dbconn->executeQuery($querystr, $parameters, $parametersTypes);
        $values = [];
        $values_country = [];
        $displayhelper = new ExhibitionDisplayHelper();
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
                            'tgn' => $row['tgn'],
                        ])),
                        htmlspecialchars($row['place'])),
                    'exhibitions' => [],
                ];
            }


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
        return $this->render('Map/place-map-index.html.twig', [
            'data' => json_encode($values_final),
            'filter' => is_null($form) ? null : $form->createView(),
            'disableClusteringAtZoom' => $disableClusteringAtZoom,
            'bounds' => [
                [ 60, -120 ],
                [ -15, 120 ],
            ],
            'markerStyle' => 'exhibition-by-place' == 'default',
            'persons' => $persons,
        ]);
    }


    // refactor this big time
    public function exhibitionByPlaceIndex(Request $request, $thisOptional, $form)
    {
        $em = $thisOptional->getDoctrine()->getEntityManager();

        $route = $request->get('_route');
        $persons = null;
        $maxDisplay = 10;
        $disableClusteringAtZoom = 5;
        $filterData = [];
        $parameters = [];
        $parametersTypes = [];

        /*
        $dbconn = $em->getConnection();
        $querystr = "SELECT DISTINCT Exhibition.id AS exhibition_id, Exhibition.title, startdate, enddate, Exhibition.displaydate AS displaydate, Location.id AS location_id, Location.name AS location_name, COALESCE(Geoname.name_variant, Geoname.name) AS place, Geoname.tgn, Geoname.latitude, Geoname.longitude"
            . " FROM Exhibition"
            . " INNER JOIN Location ON Location.id=Exhibition.id_location"
            . " INNER JOIN Geoname ON Geoname.tgn=Location.place_tgn";

        $andWhere = '';

        $querystr
            .= " WHERE"
            . " Exhibition.status <> -1"
            . $andWhere
            . " ORDER BY tgn, Exhibition.startdate, location_name, Exhibition.title"
        ;
        $stmt = $dbconn->executeQuery($querystr, $parameters, $parametersTypes);
        */

        $qb = $thisOptional->getDoctrine()
            ->getManager()
            ->createQueryBuilder();

        $qb->select([
                'PARTIAL E.{id,title,startdate,enddate,displaydate}',
                'L.id AS location_id',
                'L.name AS location_name',
                'COALESCE(G.name) AS place',
                'G.tgn',
                'G.latitude',
                'G.longitude',
                //'startdate',
                //'enddate'
            ])
            ->from('AppBundle:Exhibition', 'E')
            ->innerJoin('E.location', 'L')
            ->innerJoin('L.place', 'G')
            ->leftJoin('L.place', 'P')
            ->where('E.status <> -1')
            // ->where('ORDER BY tgn, E.startdate, location_name, E.title')
            ->groupBy('E.id')
            //->orderBy('dateSort')
        ;

        if ($request->query->has($form->getName())) {
            // manually bind values from the request
            // $form->submit($request->query->get('exhibition_filter'));

            // build the query from the given form object
            $thisOptional->get('lexik_form_filter.query_builder_updater')->addFilterConditions($form, $qb);
        }

        $result = $qb->getQuery()->execute();

        $values = [];
        $values_country = [];

        $displayhelper = new ExhibitionDisplayHelper();

        foreach ($result as $row) {
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
                        htmlspecialchars($thisOptional->generateUrl('place-by-tgn', [
                            'tgn' => $row['tgn'],
                        ])),
                        htmlspecialchars($row['place'])),
                    'exhibitions' => [],
                ];
            }

            $exhibition = $row[0];
            $values[$key]['exhibitions'][] =
                sprintf('<a href="%s">%s</a> at <a href="%s">%s</a> (%s)',
                    htmlspecialchars($thisOptional->generateUrl('exhibition', [
                        'id' => $exhibition->getId(),
                    ])),
                    htmlspecialchars($exhibition->getTitle()),
                    htmlspecialchars($thisOptional->generateUrl('location', [
                        'id' => $row['location_id'],
                    ])),
                    htmlspecialchars($row['location_name']),
                    $displayhelper->buildDisplayDate([
                        'displaydate' => $exhibition->getDisplaydate(),
                        'startdate' => $exhibition->getStartdate(),
                        'enddate' => $exhibition->getEnddate(),
                    ])
                );
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
        return [
            'data' => json_encode($values_final),
            'filter' => is_null($form) ? null : $form->createView(),
            'disableClusteringAtZoom' => $disableClusteringAtZoom,
            'bounds' => [
                [ 75, 200 ],
                [ -75, -190 ],
            ],
            'markerStyle' => 'exhibition-by-place' == $route ? 'circle' : 'default',
            'persons' => $persons,
            'resultTest' => $result
        ];
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
