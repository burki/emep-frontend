<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

use Symfony\Component\Intl\Intl;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\User\UserInterface;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Ifedko\DoctrineDbalPagination\ListBuilder;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

use Pagerfanta\Pagerfanta;

use AppBundle\Utils\SearchListPagination;

/**
 *
 */
class SearchController
extends CrudController
{
    use StatisticsBuilderTrait;

    const PAGE_SIZE = 50;

    static $entities = [
        'Exhibition',
        'Person',
        'ItemExhibition',
        'Venue',
        'Organizer',
    ];

    /**
     * TODO: move into a shared trait
     */
    protected function buildPersonNationalities()
    {
        $qb = $this->getDoctrine()
                ->getManager()
                ->createQueryBuilder();

        $qb->select([
                'P.nationality',
            ])
            ->distinct()
            ->from('AppBundle:Person', 'P')
            ->where('P.status <> -1 AND P.nationality IS NOT NULL')
            ;

        $countriesActive = [];

        foreach ($qb->getQuery()->getResult() as $result) {
            $countryCode = $result['nationality'];
            $countriesActive[$countryCode] = Intl::getRegionBundle()->getCountryName($countryCode);
        }

        asort($countriesActive);

        return $countriesActive;
    }

    /**
     *
     */
    protected function buildItemExhibitionTypes()
    {
        $conn = $this->getDoctrine()->getEntityManager()->getConnection();

        $queryBuilder = $conn->createQueryBuilder();
        $queryBuilder->select('DISTINCT IE.type')
            ->from('ItemExhibition', 'IE')
            ->where('IE.type IS NOT NULL')
            ;

        $termIds = $queryBuilder->execute()->fetchAll(\PDO::FETCH_COLUMN);

        $queryBuilder = $conn->createQueryBuilder();
        $queryBuilder->select('T.id, T.name')
            ->from('Term', 'T')
            ->where('T.id IN (:ids)')
            ->setParameter('ids', $termIds, \Doctrine\DBAL\Connection::PARAM_INT_ARRAY)
            ->orderBy('T.name')
            ;

        $types = [];

        foreach ($queryBuilder->execute()->fetchAll() as $row)  {
            if ('0_unknown' == $row['name']) {
                continue;
            }
            $types[$row['id']] = $row['name'];
        }

        return $types;
    }

    /**
     * Get all countries and places
     */
    protected function buildVenueGeonames()
    {
        $geonames = [];

        $qb = $this->getDoctrine()
                ->getManager()
                ->createQueryBuilder();

        $qb->select([
                'PL.countryCode',
                'C.name AS country',
                'PL.tgn',
                'COALESCE(PL.alternateName,PL.name) AS name'
            ])
            ->distinct()
            ->from('AppBundle:Location', 'L')
            ->leftJoin('L.place', 'PL')
            ->leftJoin('PL.country', 'C')
            ->where('L.status <> -1 AND 0 = BIT_AND(L.flags, 256) AND PL.countryCode IS NOT NULL')
            ->orderBy('country, name')
            ;

        $lastCountryCode = '';

        foreach ($qb->getQuery()->getResult() as $result) {
            if ($lastCountryCode != $result['countryCode']) {
                $key = 'cc:' . $result['countryCode'];
                $geonames[$key] = $result['country'];
            }

            $key = 'tgn:' . $result['tgn'];
            $geonames[$key] = "\xC2\xA0\xC2\xA0\xC2\xA0\xC2\xA0" . $result['name'];
        }

        return $geonames;
    }

    /**
     * Get all countries and places
     */
    protected function buildOrganizerGeonames(Request $request, UrlGeneratorInterface $urlGenerator)
    {
        $organizerListBuilder = new \AppBundle\Utils\OrganizerListBuilder($conn = $this->getDoctrine()->getEntityManager()->getConnection(), $request, $urlGenerator);
        $alias = $organizerListBuilder->getAlias();

        $queryBuilder = $conn->createQueryBuilder();

        $queryBuilder->select([
                'DISTINCT C.cc AS countryCode',
                'C.name AS country',
                'P' . $alias . '.tgn',
                'COALESCE(P' . $alias . '.name_alternate,P' . $alias . '.name) AS name',
            ])
            ->from('Location', $alias)
            ->innerJoin($alias,
                                'Geoname', 'P' . $alias,
                                'P' . $alias . '.tgn=' . $alias.'.place_tgn')
            ->innerJoin('P' . $alias,
                                'Country', 'C',
                                'P' . $alias . '.country_code=' . 'C.cc')
            ->innerJoin($alias,
                                'ExhibitionLocation', 'EL',
                                'EL.id_location=' . $alias . '.id AND EL.role = 0')
            ->innerJoin('EL',
                                'Exhibition', 'E',
                                'EL.id_exhibition=E.id AND E.status <> -1')
            ->orderBy('country, place')
            ;

        // die($queryBuilder->getSql());


        $geonames = [];


        $lastCountryCode = '';

        foreach ($queryBuilder->execute()->fetchAll() as $result) {
            if ($lastCountryCode != $result['countryCode']) {
                $key = 'cc:' . $result['countryCode'];
                $geonames[$key] = $result['country'];
            }

            $key = 'tgn:' . $result['tgn'];
            $geonames[$key] = "\xC2\xA0\xC2\xA0\xC2\xA0\xC2\xA0" . $result['name'];
        }

        return $geonames;
    }

    /**
     * @Route("/search", name="search")
     */
    public function searchAction(Request $request,
                                 UrlGeneratorInterface $urlGenerator,
                                 UserInterface $user = null)
    {
        if ('POST' == $request->getMethod() && !is_null($user)) {
            // check a useraction was requested
            $userActionId = $request->request->get('useraction');
            if (!empty($userActionId)) {
                $userAction = $this->getDoctrine()
                    ->getManager()
                    ->getRepository('AppBundle:UserAction')
                    ->findOneBy([
                        'id' => $userActionId,
                        'user' => $user,
                        'route' => 'search',
                    ]);

                if (!is_null($userAction)) {
                    return $this->redirectToRoute($userAction->getRoute(),
                                                  $userAction->getRouteParams());
                }
            }
        }

        $listBuilder = $this->instantiateListBuilder($request, $urlGenerator);

        $listPagination = new SearchListPagination($listBuilder);

        $page = $request->get('page', 1);
        $listPage = $listPagination->get(self::PAGE_SIZE, ($page - 1) * self::PAGE_SIZE);

        return $this->renderResult($listPage, $listBuilder, $user);
    }

    /**
     * @Route("/search/save", name="search-save")
     */
    public function saveSearchAction(Request $request,
                                     UrlGeneratorInterface $urlGenerator,
                                     UserInterface $user)
    {
        $listBuilder = $this->instantiateListBuilder($request, $urlGenerator);
        $filters = $listBuilder->getQueryFilters();

        $routeParams = [
            'entity' => $listBuilder->getEntity()
        ];

        if (empty($filters)) {
            // nothing to save
            return $this->redirectToRoute('search', $routeParams);
        }

        $routeParams['filter'] = $filters;

        $form = $this->createForm(\AppBundle\Form\Type\SaveSearchType::class);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $userAction = new \AppBundle\Entity\UserAction();

            $userAction->setUser($user);
            $userAction->setRoute($route = 'search');
            $userAction->setRouteParams($routeParams);

            $userAction->setName($data['name']);

            $em = $this->getDoctrine()
                ->getManager();

            $em->persist($userAction);
            $em->flush();

            return $this->redirectToRoute($route, $routeParams);
        }

        return $this->render('Search/save.html.twig', [
            'pageTitle' => $this->get('translator')->trans('Save your query'),
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/search/export", name="search-export")
     */
    public function exportAction(Request $request, UrlGeneratorInterface $urlGenerator)
    {
        $listBuilder = $this->instantiateListBuilder($request, $urlGenerator, 'extended');

        set_time_limit(5 * 60); // ItemExhibition is large

        $writer = \Box\Spout\Writer\WriterFactory::create(\Box\Spout\Common\Type::XLSX);
        $writer->openToBrowser($listBuilder->getEntity() . '.xlsx');

        $writer->addRow($listBuilder->buildHeaderRow());

        $query = $listBuilder->query();

        $stmt = $query->execute();
        while ($row = $stmt->fetch()) {
            $writer->addRow($listBuilder->buildRow($row));
        }

        $writer->close();

        exit;
    }

    /**
     * @Route("/search/stats", name="search-stats")
     */
    public function statsAction(Request $request,
                                UrlGeneratorInterface $urlGenerator,
                                UserInterface $user = null)
    {
        $listBuilder = $this->instantiateListBuilder($request, $urlGenerator);
        $charts = [];

        switch ($listBuilder->getEntity()) {
            case 'Exhibition':
                // exhibtion country-nationality matrix
                $listBuilder = $this->instantiateListBuilder($request, $urlGenerator, 'stats-nationality');
                $query = $listBuilder->query();
                // echo $query->getSQL();

                $stmt = $query->execute();
                $renderParams = $this->processExhibitionNationality($stmt);
                if (!empty($renderParams)) {
                    $template = $this->get('twig')->loadTemplate('Statistics/exhibition-nationality-index.html.twig');

                    $charts[] = $template->render($renderParams);
                }

                // by month
                $listBuilder = $this->instantiateListBuilder($request, $urlGenerator, 'stats-by-month');

                $query = $listBuilder->query();
                // echo $query->getSQL();

                $stmt = $query->execute();
                $renderParams = $this->processExhibitionByMonth($stmt);
                if (!empty($renderParams)) {
                    $template = $this->get('twig')->loadTemplate('Statistics/exhibition-by-month-index.html.twig');

                    $charts[] = $template->render($renderParams);
                }

                // exhibition age
                $listBuilder = $this->instantiateListBuilder($request, $urlGenerator, 'stats-age');

                $query = $listBuilder->query();
                $innerSql = $query->getSQL();

                $sql = <<<EOT
SELECT COUNT(*) AS how_many,
YEAR(EB.startdate) - YEAR(EB.birthdate) AS age,
IF (EB.deathdate IS NOT NULL AND YEAR(EB.deathdate) < YEAR(EB.startdate), 'deceased', 'living') AS state
FROM
({$innerSql}) AS EB
GROUP BY age, state
ORDER BY age, state, how_many
EOT;

                $params = $query->getParameters();

                $stmt = $query->getConnection()->executeQuery($sql, $params);
                $renderParams = $this->processExhibitionAge($stmt);
                if (!empty($renderParams)) {
                    $template = $this->get('twig')->loadTemplate('Statistics/person-exhibition-age-index.html.twig');

                    $charts[] = $template->render($renderParams);
                }

                // place
                $listBuilder = $this->instantiateListBuilder($request, $urlGenerator, 'stats-place');
                $query = $listBuilder->query();
                // echo $query->getSQL();

                $stmt = $query->execute();
                $renderParams = $this->processExhibitionPlace($stmt);
                if (!empty($renderParams)) {
                    $template = $this->get('twig')->loadTemplate('Statistics/exhibition-city-index.html.twig');

                    $charts[] = $template->render($renderParams);
                }

                // type of organizer
                $listBuilder = $this->instantiateListBuilder($request, $urlGenerator, 'stats-organizer-type');
                $query = $listBuilder->query();
                // echo $query->getSQL();

                $stmt = $query->execute();
                $renderParams = $this->processExhibitionOrganizerType($stmt);
                if (!empty($renderParams)) {
                    $template = $this->get('twig')->loadTemplate('Statistics/exhibition-organizer-index.html.twig');

                    $charts[] = $template->render($renderParams);
                }

                break;

            case 'Person':
                // nationality
                $listBuilder = $this->instantiateListBuilder($request, $urlGenerator, 'stats-nationality');
                $query = $listBuilder->query();
                // echo $query->getSQL();

                $stmt = $query->execute();
                $renderParams = $this->processPersonNationality($stmt);
                if (!empty($renderParams)) {
                    $template = $this->get('twig')->loadTemplate('Statistics/person-nationality-index.html.twig');

                    $charts[] = $template->render($renderParams);
                }

                // birth/death
                $listBuilderBirth = $this->instantiateListBuilder($request, $urlGenerator, 'stats-by-year-birth');
                // $query = $listBuilderBirth->query();
                // echo $query->getSQL()

                $listBuilderDeath = $this->instantiateListBuilder($request, $urlGenerator, 'stats-by-year-death');
                // $query = $listBuilderDeath->query();
                //  echo $query->getSQL());

                $renderParams = $this->processPersonBirthDeath([
                    'birth' => $listBuilderBirth->query(),
                    'death' => $listBuilderDeath->query(),
                ]);

                if (!empty($renderParams)) {
                    $template = $this->get('twig')->loadTemplate('Statistics/person-by-year-index.html.twig');

                    $charts[] = $template->render($renderParams);
                }

                // exhibition-distribution
                $listBuilder = $this->instantiateListBuilder($request, $urlGenerator, 'stats-exhibition-distribution');
                $query = $listBuilder->query();
                // echo $query->getSQL();

                $stmt = $query->execute();
                $renderParams = $this->processPersonDistribution([ 'exhibition' => $query ]);
                if (!empty($renderParams)) {
                    $template = $this->get('twig')->loadTemplate('Statistics/person-distribution-index.html.twig');

                    $charts[] = $template->render($renderParams);
                }

                // wikipedia
                $lang = in_array($request->get('lang'), [ 'en', 'de', 'fr' ])
                    ? $request->get('lang') : 'en';

                $listBuilder = $this->instantiateListBuilder($request, $urlGenerator, 'stats-popularity');
                $query = $listBuilder->query();
                // echo $query->getSQL();

                $stmt = $query->execute();
                $renderParams = $this->processPersonPopularity($stmt, $lang);
                if (!empty($renderParams)) {
                    $template = $this->get('twig')->loadTemplate('Statistics/person-wikipedia-index.html.twig');

                    $charts[] = $template->render($renderParams);
                }

                break;

            case 'ItemExhibition':
                $listBuilder = $this->instantiateListBuilder($request, $urlGenerator, 'stats-type');

                $query = $listBuilder->query();
                // echo $query->getSQL();

                $stmt = $query->execute();
                $renderParams = $this->processItemExhibitionType($stmt);
                if (!empty($renderParams)) {
                    $template = $this->get('twig')->loadTemplate('Statistics/exhibition-type-index.html.twig');

                    $charts[] = $template->render($renderParams);
                }

                break;

            case 'Venue':
                // type
                $listBuilder = $this->instantiateListBuilder($request, $urlGenerator, 'stats-type');
                $query = $listBuilder->query();
                // echo $query->getSQL();

                $stmt = $query->execute();
                $renderParams = $this->processLocationType($stmt);
                if (!empty($renderParams)) {
                    $template = $this->get('twig')->loadTemplate('Statistics/venue-type-index.html.twig');

                    $charts[] = $template->render($renderParams);
                }

                // country
                $listBuilder = $this->instantiateListBuilder($request, $urlGenerator, 'stats-country');
                $query = $listBuilder->query();
                // echo $query->getSQL();

                $stmt = $query->execute();
                $renderParams = $this->processLocationCountry($stmt);
                if (!empty($renderParams)) {
                    $template = $this->get('twig')->loadTemplate('Statistics/venue-country-index.html.twig');

                    $charts[] = $template->render($renderParams);
                }

                break;

            case 'Organizer':
                // type
                $listBuilder = $this->instantiateListBuilder($request, $urlGenerator, 'stats-type');
                $query = $listBuilder->query();
                // echo $query->getSQL();

                $stmt = $query->execute();
                $renderParams = $this->processLocationType($stmt);
                if (!empty($renderParams)) {
                    $template = $this->get('twig')->loadTemplate('Statistics/organizer-type-index.html.twig');

                    $charts[] = $template->render($renderParams);
                }

                // country
                $listBuilder = $this->instantiateListBuilder($request, $urlGenerator, 'stats-country');
                $query = $listBuilder->query();
                // echo $query->getSQL();

                $stmt = $query->execute();
                $renderParams = $this->processLocationCountry($stmt);
                if (!empty($renderParams)) {
                    $template = $this->get('twig')->loadTemplate('Statistics/organizer-country-index.html.twig');

                    $charts[] = $template->render($renderParams);
                }

                break;
        }

        if (empty($charts)) {
            $charts[] = 'No matching data found. Please adjust the filters';
        }

        return $this->render('Search/stats.html.twig', [
            'pageTitle' => $this->get('translator')->trans('Advanced Search'),
            'listBuilder' => $listBuilder,
            'form' => $this->form->createView(),
            'searches' => $this->lookupSearches($user),

            'charts' => implode("\n", $charts),
        ]);
    }

    /* TODO: move to shared helper */
    private function buildDisplayDate($row)
    {
        if (!empty($row['displaydate'])) {
            return $row['displaydate'];
        }

        return \AppBundle\Utils\Formatter::daterangeIncomplete($row['startdate'], $row['enddate']);
    }

    /**
     * @Route("/search/map", name="search-map")
     */
    public function mapAction(Request $request,
                              UrlGeneratorInterface $urlGenerator,
                              UserInterface $user = null)
    {
        $listBuilder = $this->instantiateListBuilder($request, $urlGenerator, 'extended');
        if (!in_array($entity = $listBuilder->getEntity(), [ 'Exhibition', 'Venue', 'Organizer', 'Person'])) {
            $routeParams = [
                'entity' => $listBuilder->getEntity(),
                'filter' => $listBuilder->getQueryFilters(),
            ];

            return $this->redirectToRoute('search', $routeParams);
        }

        $query = $listBuilder->query();
        // echo($query->getSQL());

        $maxDisplay = 'Person' == $entity ? 15 : 10;

        $stmt = $query->execute();

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

        return $this->render('Search/map.html.twig', [
            'pageTitle' => $this->get('translator')->trans('Advanced Search'),
            'subTitle' => $subTitle,
            'data' => json_encode($values_final),
            'disableClusteringAtZoom' => 'Person' == $entity ? 7 : 5,
            'maxCount' => isset($max_count) ? $max_count : null,
            'showHeatMap' => 'Person' == $entity,
            'markerStyle' => 'Person' == $entity ? 'pie' : 'circle',
            'bounds' => [
                [ 60, -120 ],
                [ -15, 120 ],
            ],

            'listBuilder' => $listBuilder,
            'form' => $this->form->createView(),
            'searches' => $this->lookupSearches($user),
        ]);
    }

    /**
     * @Route("/search/select/person", name="search-select-person")
     * @Route("/search/select/location", name="search-select-location")
     * @Route("/search/select/organizer", name="search-select-organizer")
     * @Route("/search/select/exhibition", name="search-select-exhibition")
     */
    public function searchSelectAction(Request $request, UrlGeneratorInterface $urlGenerator)
    {
        $search = $request->get('q');

        $data = [];

        if (isset($search) && mb_strlen($search, 'UTF-8') >= 2) {
            $connection = $this->getDoctrine()->getEntityManager()->getConnection();

            switch ($request->get('_route')) {
                case 'search-select-person':
                    $listBuilder = new \AppBundle\Utils\PersonListBuilder($connection, $request, $urlGenerator, []);
                    $fields = [ 'P.lastname', 'P.firstname', 'P.name_variant', 'P.name_variant_ulan' ];
                    $queryBuilder = $listBuilder->getQueryBuilder();

                    $queryBuilder
                        ->from('Person', 'P')
                        ->andWhere('P.status <> -1')
                        ->select("P.id AS id, COALESCE(CONCAT(P.lastname, ', ', P.firstname), P.lastname) AS text")
                        ;
                    break;

                case 'search-select-location':
                    $listBuilder = new \AppBundle\Utils\VenueListBuilder($connection, $request, $urlGenerator, []);
                    $fields = [ 'L.name', 'L.name_translit' ];
                    $queryBuilder = $listBuilder->getQueryBuilder();

                    $queryBuilder
                        ->from('Location', 'L')
                        ->andWhere('L.status <> -1 AND 0 = (L.flags & 256)')
                        ->select("L.id AS id, COALESCE(CONCAT(L.name, ' [', L.name_translit, ']'), L.name) AS text")
                        ;
                    break;

                case 'search-select-organizer':
                    $listBuilder = new \AppBundle\Utils\OrganizerListBuilder($connection, $request, $urlGenerator, []);
                    $fields = [ 'O.name', 'O.name_translit' ];
                    $queryBuilder = $listBuilder->getQueryBuilder();

                    $queryBuilder
                        ->from('Location', 'O')
                        ->andWhere('O.status <> -1')
                        ->select("DISTINCT O.id AS id, COALESCE(CONCAT(O.name, ' [', O.name_translit, ']'), O.name) AS text")
                        ;
                    $listBuilder->setExhibitionJoin($queryBuilder);
                    break;

                case 'search-select-exhibition':
                    $listBuilder = new \AppBundle\Utils\ExhibitionListBuilder($connection, $request, $urlGenerator, []);
                    $fields = [ 'E.title', 'E.title_short', 'E.title_translit', 'E.title_alternate' ];
                    $queryBuilder = $listBuilder->getQueryBuilder();

                    $queryBuilder
                        ->from('Exhibition', 'E')
                        ->andWhere('E.status <> -1')
                        ->select("E.id AS id, E.title AS text, E.startdate, E.enddate, E.displaydate")
                        ;
                    break;
            }


            $condition = $listBuilder->buildLikeCondition($search, $fields);

            if (!empty($condition)) {
                foreach ($condition['parameters'] as $name => $value) {
                    $queryBuilder->setParameter($name, $value);
                }
                foreach ($condition['andWhere'] as $andWhere) {
                    $queryBuilder->andWhere($andWhere);
                }
            }

            $queryBuilder
                ->orderBy('text');

            // TODO: set from $request
            $queryBuilder
                ->setFirstResult(0)
                ->setMaxResults(10);

            $data = $queryBuilder->execute()->fetchAll();

            if ('search-select-exhibition' == $request->get('_route')) {
                // append date
                foreach ($data as $i => $row) {
                    $append = '';
                    if (!empty($row['displaydate'])) {
                        $append = $row['displaydate'];
                    }
                    else {
                        $append = \AppBundle\Utils\Formatter::daterangeIncomplete($row['startdate'], $row['enddate']);
                    }



                    $data[$i] = [
                        'id' => $row['id'],
                        'text' => $row['text']
                            . (!empty($append) ? ' (' . $append . ')' : ''),
                    ];
                }
            }

        }


        return new JsonResponse($data);
    }

    protected function instantiateListBuilder(Request $request,
                                              UrlGeneratorInterface $urlGenerator,
                                              $mode = false)
    {
        $connection = $this->getDoctrine()->getEntityManager()->getConnection();

        $entity = $request->get('entity');
        if (!in_array($entity, self::$entities)) {
            $entity = self::$entities[0];
        }

        $venueTypes = $this->buildVenueTypes();
        $exhibitionTypes = [ 'group', 'solo', 'auction' ];
        $exhibitionOrganizerTypes = $this->buildOrganizerTypes();

        $this->form = $this->createForm(\AppBundle\Form\Type\SearchFilterType::class, [
            'choices' => [
                'nationality' => array_flip($this->buildPersonNationalities()),
                'location_geoname' => array_flip($this->buildVenueGeonames()),
                'location_type' => array_combine($venueTypes, $venueTypes),
                'organizer_geoname' => array_flip($this->buildOrganizerGeonames($request, $urlGenerator)),
                'organizer_type' => array_combine($venueTypes, $venueTypes),
                'exhibition_type' => array_combine($exhibitionTypes, $exhibitionTypes),
                'exhibition_organizer_type' => array_combine($exhibitionOrganizerTypes, $exhibitionOrganizerTypes),
                'itemexhibition_type' => array_flip($this->buildItemExhibitionTypes()),
            ],
        ]);

        $parameters = $request->query->all();
        if (array_key_exists('filter', $parameters)) {
            $this->form->submit($parameters['filter']);
        }

        $filters = $this->form->getData();

        switch ($entity) {
            case 'Venue':
                return new \AppBundle\Utils\VenueListBuilder($connection, $request, $urlGenerator, $filters, $mode);
                break;

            case 'Organizer':
                return new \AppBundle\Utils\OrganizerListBuilder($connection, $request, $urlGenerator, $filters, $mode);
                break;

            case 'Person':
                return new \AppBundle\Utils\PersonListBuilder($connection, $request, $urlGenerator, $filters, $mode);
                break;

            case 'ItemExhibition':
                return new \AppBundle\Utils\ItemExhibitionListBuilder($connection, $request, $urlGenerator, $filters, $mode);

            case 'Exhibition':
            default:
                return new \AppBundle\Utils\ExhibitionListBuilder($connection, $request, $urlGenerator, $filters, $mode);
                break;

        }
    }

    protected function lookupSearches($user)
    {
        if (is_null($user)) {
            return [];
        }

        $qb = $this->getDoctrine()
                ->getManager()
                ->createQueryBuilder();

        $qb->select('UA')
            ->from('AppBundle:UserAction', 'UA')
            ->where("UA.route = 'search'")
            ->andWhere("UA.user = :user")
            ->orderBy("UA.createdAt", "DESC")
            ->setParameter('user', $user)
            ;

        $searches = [];

        foreach ($qb->getQuery()->getResult() as $userAction) {
            $searches[$userAction->getId()] = $userAction->getName();
        }

        return $searches;
    }

    protected function renderResult($listPage, $listBuilder, UserInterface $user = null)
    {
        $adapter = new SearchListAdapter($listPage);
        $pager = new Pagerfanta($adapter);
        $pager->setMaxPerPage($listPage['limit']);
        $pager->setCurrentPage(intval($listPage['offset'] / $listPage['limit']) + 1);

        return $this->render('Search/base.html.twig', [
            'pageTitle' => $this->get('translator')->trans('Advanced Search'),
            'pager' => $pager,

            'listBuilder' => $listBuilder,
            'form' => $this->form->createView(),
            'searches' => $this->lookupSearches($user),
        ]);
    }
}

class SearchListAdapter
implements \Pagerfanta\Adapter\AdapterInterface
{
    var $listPaginationResult = null;

    function __construct($listPaginationResult)
    {
        $this->listPaginationResult = $listPaginationResult;
    }

    /**
     * {@inheritdoc}
     */
    public function getNbResults()
    {
        return $this->listPaginationResult['total'];
    }

    /**
     * {@inheritdoc}
     */
    public function getSlice($offset, $length)
    {
        return $this->listPaginationResult['items'];
    }
}
