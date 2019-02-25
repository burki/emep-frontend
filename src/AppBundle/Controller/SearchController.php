<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

use Symfony\Component\Intl\Intl;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\User\UserInterface;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Ifedko\DoctrineDbalPagination\ListBuilder;

use Pagerfanta\Pagerfanta;

use AppBundle\Utils\SearchListPagination;
use AppBundle\Utils\SearchListAdapter;

/**
 *
 */
class SearchController
extends CrudController
{
    use MapBuilderTrait;
    use StatisticsBuilderTrait;

    static $entities = [
        'Exhibition',
        'Person',
        'ItemExhibition',
        'Venue',
        'Organizer',
    ];

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
     * Get all countries
     */
    protected function buildHolderGeonames()
    {
        $geonames = [];

        $qb = $this->getDoctrine()
                ->getManager()
                ->createQueryBuilder();

        $qb->select([
                'H.countryCode',
                'H.countryCode AS country',
            ])
            ->distinct()
            ->from('AppBundle:Holder', 'H')
            ->where('H.status <> -1')
            ->orderBy('country')
            ;

        $lastCountryCode = '';

        foreach ($qb->getQuery()->getResult() as $result) {
            if ($lastCountryCode != $result['countryCode']) {
                $key = 'cc:' . $result['countryCode'];
                $geonames[$key] = $result['country'];
            }
        }

        return $geonames;
    }

    /**
     * @Route("/search", name="search")
     * @Route("/exhibition/search", name="exhibition")
     * @Route("/location/search", name="location")
     * @Route("/holder/search", name="holder")
     * @Route("/person/search", name="person")
     * @Route("/place/search", name="place")
     * @Route("/itemExhibition/search", name="itemexhibition")
     * @Route("/venue/search", name="venue")
     * @Route("/organizer/search", name="organizer")
     */
    public function searchAction(Request $request,
                                 UrlGeneratorInterface $urlGenerator,
                                 UserInterface $user = null)
    {

        $routeName = $request->get('_route');


        $response = $this->handleUserAction($request, $user);
        if (!is_null($response)) {
            return $response;
        }


        $listBuilder = $this->instantiateListBuilder($request, $urlGenerator,null, ucfirst($routeName) );

        $listPagination = new SearchListPagination($listBuilder);

        $page = $request->get('page', 1);
        $listPage = $listPagination->get($this->pageSize, ($page - 1) * $this->pageSize);


        return $this->renderResult($listPage, $listBuilder, $user, $routeName);
    }


    protected function buildSaveSearchParams(Request $request, UrlGeneratorInterface $urlGenerator)
    {
        $route = 'search'; // maybe build from $request with a certain string replace pattern;

        $listBuilder = $this->instantiateListBuilder($request, $urlGenerator);
        $filters = $listBuilder->getQueryFilters(true);
        if (empty($filters)) {
            return [ $route, [] ];
        }

        $routeParams = [
            'entity' => $listBuilder->getEntity(),
            'filter' => $filters,
        ];

        return [ $route, $routeParams ];
    }

    /**
     * @Route("/search/save", name="search-save")
     */
    public function saveSearchAction(Request $request,
                                     UrlGeneratorInterface $urlGenerator,
                                     UserInterface $user)
    {
        return $this->handleSaveSearchAction($request, $urlGenerator, $user);
    }

    /**
     * @Route("/search/export", name="search-export")
     */
    public function exportAction(Request $request, UrlGeneratorInterface $urlGenerator)
    {
        $listBuilder = $this->instantiateListBuilder($request, $urlGenerator, 'extended');
        $filters = $listBuilder->getQueryFilters();

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
     * @Route("/exhibition/index/stats", name="exhibition-stats")
     * @Route("/venue/index/stats", name="venue-stats")
     * @Route("/person/index/stats", name="person-stats")
     * @Route("/itemexhibition/index/stats", name="itemExhibition-stats")
     * @Route("/organizer/index/stats", name="organizer-stats")
     */
    public function statsAction(Request $request,
                                UrlGeneratorInterface $urlGenerator,
                                UserInterface $user = null)
    {

        $listBuilder = $this->instantiateListBuilder($request, $urlGenerator);
        $charts = [];


        switch ($listBuilder->getEntity()) {
            case 'Exhibition':
                $charts = $this->buildExhibitionCharts($request, $urlGenerator, $listBuilder);

                break;

            case 'Person':
                $charts = $this->buildPersonCharts($request, $urlGenerator, $listBuilder);

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
                $charts = $this->buildLocationCharts($request, $urlGenerator, $listBuilder);

                break;
        }

        if (empty($charts)) {
            $charts[] = 'No matching data found. Please adjust the filters';
        }


        $routeName = $request->get('_route');
        $tempaltePath = 'Search/stats.html.twig';
        $entityName = 'search';
        $type = 'stats';
        $pageTitle = 'Advanced Search';

        // other path for basic search


        switch ($routeName) {
            case 'exhibition-stats':
                $tempaltePath = 'Search/Entity/stats.html.twig';
                $entityName = "exhibition";
                $pageTitle = 'Exhibitions';
                break;
            case 'venue-stats':
                $tempaltePath = 'Search/Entity/stats.html.twig';
                $entityName = "venue";
                $pageTitle = 'Venues';
                break;
            case 'person-stats':
                $tempaltePath = 'Search/Entity/stats.html.twig';
                $entityName = "person";
                $pageTitle = 'Artists';
                break;
            case 'person-stats':
                $tempaltePath = 'Search/Entity/stats.html.twig';
                $entityName = "itemExhibition";
                $pageTitle = 'Catalogue Entries';
                break;
            case 'organizer-stats':
                $tempaltePath = 'Search/Entity/stats.html.twig';
                $entityName = "organizer";
                $pageTitle = 'Organizing Bodies';
                break;
        }



        return $this->render($tempaltePath, [
            'pageTitle' => $this->get('translator')->trans($pageTitle),
            'listBuilder' => $listBuilder,
            'form' => $this->form->createView(),
            'searches' => $this->lookupSearches($user, $entityName),

            'charts' => implode("\n", $charts),
            'entityName' => $entityName,
            'type' => $type
        ]);
    }

    /**
     * @Route("/search/map", name="search-map")
     * @Route("/exhibition/map/index", name="exhibition-map")
     * @Route("/venue/map/index", name="venue-map")
     * @Route("/person/map/index", name="person-map")
     * @Route("/itemexhibition/map/index", name="itemExhibition-map")
     * @Route("/organizer/map/index", name="organizer-map")
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

        $stmt = $query->execute();




        $renderParams = $this->processMapEntries($stmt, $entity);


        $routeName = $request->get('_route');
        $templatePath = 'Search/map.html.twig';
        $entityName = 'search';
        $type = 'map';
        $pageTitle = 'Advanced Search';

        // other path for basic search

        switch ($routeName) {
            case 'exhibition-map':
                $templatePath = 'Search/Entity/map.html.twig';
                $entityName = "exhibition";
                $pageTitle = 'Exhibitions';
                break;
            case 'venue-map':
                $templatePath = 'Search/Entity/map.html.twig';
                $entityName = "venue";
                $pageTitle = 'Venues';
                break;
            case 'person-map':
                $templatePath = 'Search/Entity/map.html.twig';
                $entityName = "person";
                $pageTitle = 'Artists';
                break;
            case 'itemExhibition-map':
                $templatePath = 'Search/Entity/map.html.twig';
                $entityName = "itemExhibition";
                $pageTitle = 'Catalogue Entries';
                break;
            case 'organizer-map':
                $templatePath = 'Search/Entity/map.html.twig';
                $entityName = "organizer";
                $pageTitle = 'Organizing Bodies';
                break;
        }


        return $this->render($templatePath, $renderParams + [
            'pageTitle' => $this->get('translator')->trans($pageTitle),
            'disableClusteringAtZoom' => 'Person' == $entity ? 7 : 5,
            'showHeatMap' => 'Person' == $entity,
            'markerStyle' => 'Person' == $entity ? 'pie' : 'circle',
            'bounds' => [
                [ 60, -120 ],
                [ -15, 120 ],
            ],

            'listBuilder' => $listBuilder,
            'form' => $this->form->createView(),
            'searches' => $this->lookupSearches($user, $entityName),
            'entityName' => $entityName,
            'type' => $type
        ]);
    }

    /**
     * @Route("/search/select/person", name="search-select-person")
     * @Route("/search/select/location", name="search-select-location")
     * @Route("/search/select/organizer", name="search-select-organizer")
     * @Route("/search/select/holder", name="search-select-holder")
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

                case 'search-select-holder':
                    $listBuilder = new \AppBundle\Utils\HolderListBuilder($connection, $request, $urlGenerator, []);
                    $fields = [ 'H.name' ];
                    $queryBuilder = $listBuilder->getQueryBuilder();

                    $queryBuilder
                        ->from('Holder', 'H')
                        ->andWhere('H.status <> -1')
                        ->select("H.id AS id, H.name AS text")
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
                                              $mode = false,
                                              $entity = null)
    {
        $connection = $this->getDoctrine()->getEntityManager()->getConnection();


        if (!$entity) { $entity = $request->get('entity'); };
        if (!in_array($entity, self::$entities)) {
            if ('search-export' == $request->get('_route') && 'Holder' == $entity) {
                // Holder has only export functionality
            }
            else {
                $entity = self::$entities[0];
            }
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
                'holder_geoname' => array_flip($this->buildHolderGeonames()),
                'exhibition_type' => array_combine($exhibitionTypes, $exhibitionTypes),
                'exhibition_organizer_type' => array_combine($exhibitionOrganizerTypes, $exhibitionOrganizerTypes),
                'itemexhibition_type' => array_flip($this->buildItemExhibitionTypes()),
            ],
        ]);

        $parameters = $request->query->all();
        $parameters = self::array_filter_recursive($parameters, null, true); // remove empty values

        if (array_key_exists('filter', $parameters)) {
            // some values must be arrays and not scalar as in Basic Search
            $forceArray = [
                'location' => [ 'geoname' ],
                'organizer' => [ 'geoname' ],
                'holder' => [ 'geoname' ],
                'exhibition' => [ 'organizer_type' ],
            ];

            foreach ($forceArray as $group => $fields) {
                if (!array_key_exists($group, $parameters['filter'])) {
                    continue;
                }

                foreach ($fields as $field) {
                    if (array_key_exists($field, $parameters['filter'][$group]) && !is_array($parameters['filter'][$group][$field])) {
                        $parameters['filter'][$group][$field] = [ $parameters['filter'][$group][$field] ];
                    }
                }
            }

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

            case 'Holder':
                return new \AppBundle\Utils\HolderListBuilder($connection, $request, $urlGenerator, $filters, $mode);
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


    protected function renderResult($listPage, $listBuilder, UserInterface $user = null, $template = null)
    {
        $adapter = new SearchListAdapter($listPage);
        $pager = new Pagerfanta($adapter);
        $pager->setMaxPerPage($listPage['limit']);
        $pager->setCurrentPage(intval($listPage['offset'] / $listPage['limit']) + 1);

        $templatePath = 'Search/base.html.twig';
        $entityName = 'search';
        $pageTitle = 'Advanced Search';



        switch ($template) {
            case 'exhibition':
                $templatePath = 'Search/Entity/index.html.twig';
                $entityName = 'exhibition';
                $pageTitle = 'Exhibtions';
                break;
            case 'location':
                $templatePath = 'Search/Entity/index.html.twig';
                $entityName = 'venue';
                $pageTitle = 'Venues';
                break;
            case 'venue':
                $templatePath = 'Search/Entity/index.html.twig';
                $entityName = 'venue';
                $pageTitle = 'Venues';
                break;
            case 'organizer':
                $templatePath = 'Search/Entity/index.html.twig';
                $entityName = 'organizer';
                $pageTitle = 'Organizing Bodies';
                break;
            case 'person':
                $templatePath = 'Search/Entity/index.html.twig';
                $entityName = 'person';
                $pageTitle = 'Artists';
                break;
            case 'itemExhibition':
                $templatePath = 'Search/Entity/index.html.twig';
                $entityName = "itemExhibition";
                $pageTitle = 'Catalogue Entries';
                break;
        }

        if($template === 'exhibition' ){
         $templatePath = 'Search/Entity/index.html.twig';
         $entityName = 'exhibition';
         $pageTitle = 'Exhibtions';
        }

        return $this->render($templatePath, [
            'pageTitle' => $this->get('translator')->trans($pageTitle),
            'pager' => $pager,

            'listBuilder' => $listBuilder,
            'form' => $this->form->createView(),
            'searches' => $this->lookupSearches($user, 'search'),
            'entityName' => $entityName
        ]);
    }
}
