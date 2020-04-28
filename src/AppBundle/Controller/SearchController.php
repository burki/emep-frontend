<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\User\UserInterface;

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
        'Place',
    ];

    /**
     * @Route("/search", name="search-index", options={"sitemap" = true})
     * @Route("/exhibition", name="exhibition-index", options={"sitemap" = true})
     * @Route("/person", name="person-index", options={"sitemap" = true})
     * @Route("/location", name="venue-index", options={"sitemap" = true})
     * @Route("/organizer", name="organizer-index", options={"sitemap" = true})
     * @Route("/place/exhibiting", name="place-index", options={"sitemap" = true})
     */
    public function searchAction(Request $request,
                                 UrlGeneratorInterface $urlGenerator,
                                 UserInterface $user = null)
    {
        $settings = $this->lookupSettingsFromRequest($request);

        $response = $this->handleUserAction($request, $user, $settings['base']);
        if (!is_null($response)) {
            return $response;
        }

        $listBuilder = $this->instantiateListBuilder($request, $urlGenerator, null, $settings['entity']);
        /*
        // debug
        $query = $listBuilder->query();
        dd($query);
        */

        $listPagination = new SearchListPagination($listBuilder);

        $page = $request->get('page', 1);
        $listPage = $listPagination->get($this->pageSize, ($page - 1) * $this->pageSize);

        $adapter = new SearchListAdapter($listPage);
        $pager = new Pagerfanta($adapter);
        $pager->setMaxPerPage($listPage['limit']);
        $pager->setCurrentPage(intval($listPage['offset'] / $listPage['limit']) + 1);

        $templatePath = 'Search/base.html.twig';
        if ('search' != $settings['base']) {
            $templatePath = 'Search/Entity/index.html.twig';
        }

        return $this->render($templatePath, [
            'pageTitle' => $this->get('translator')->trans($settings['pageTitle']),
            'type' => $settings['view'],
            'pager' => $pager,

            'listBuilder' => $listBuilder,
            'form' => $this->form->createView(),
            'searches' => $this->lookupSearches($user, $settings['base']),
        ]);
    }

    protected function buildSaveSearchParams(Request $request, UrlGeneratorInterface $urlGenerator)
    {
        $settings = $this->lookupSettingsFromRequest($request);
        $route = $settings['base'];

        $listBuilder = $this->instantiateListBuilder($request, $urlGenerator, null, $settings['entity']);
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
        $settings = $this->lookupSettingsFromRequest($request);

        $listBuilder = $this->instantiateListBuilder($request, $urlGenerator, 'extended', $settings['entity']);
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
     * @Route("/exhibition/stats", name="exhibition-index-stats")
     * @Route("/person/stats", name="person-index-stats")
     * @Route("/location/stats", name="venue-index-stats")
     * @Route("/organizer/stats", name="organizer-index-stats")
     * @Route("/itemexhibition/stats", name="itemexhibition-index-stats")
     * @Route("/place/exhibiting/stats", name="place-index-stats")
     */
    public function statsAction(Request $request,
                                UrlGeneratorInterface $urlGenerator,
                                UserInterface $user = null)
    {
        $settings = $this->lookupSettingsFromRequest($request);

        $response = $this->handleUserAction($request, $user, $settings['base']);
        if (!is_null($response)) {
            return $response;
        }

        $listBuilder = $this->instantiateListBuilder($request, $urlGenerator, false, $settings['entity']);

        $charts = [];

        switch ($listBuilder->getEntity()) {
            case 'Exhibition':
                $charts = $this->buildExhibitionCharts($request, $urlGenerator, $listBuilder);

                break;

            case 'Person':
                $charts = $this->buildPersonCharts($request, $urlGenerator, $listBuilder);

                break;

            case 'ItemExhibition':
                $listBuilder = $this->instantiateListBuilder($request, $urlGenerator, 'stats-type', $listBuilder->getEntity());

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
            case 'Organizer':
                $charts = $this->buildLocationCharts($request, $urlGenerator, $listBuilder);

                break;

            case 'Place':
                $charts = $this->buildPlaceCharts($request, $urlGenerator, $listBuilder);

                break;
        }

        if (empty($charts)) {
            $charts[] = 'No matching data found. Please adjust the filters';
        }

        $templatePath = 'Search/stats.html.twig';
        if ('search' != $settings['base']) {
            $templatePath = 'Search/Entity/stats.html.twig';
        }

        return $this->render($templatePath, [
            'pageTitle' => $this->get('translator')->trans($settings['pageTitle']),
            'type' => $settings['view'],
            'listBuilder' => $listBuilder,
            'form' => $this->form->createView(),
            'searches' => $this->lookupSearches($user, $settings['base']),

            'charts' => implode("\n", $charts)
        ]);
    }

    /**
     * @Route("/search/map", name="search-map")
     * @Route("/exhibition/map", name="exhibition-index-map")
     * @Route("/person/map", name="person-index-map")
     * @Route("/location/map", name="venue-index-map")
     * @Route("/organizer/map", name="organizer-index-map")
     * @Route("/place/exhibiting/map", name="place-index-map")
     */
    public function mapAction(Request $request,
                              UrlGeneratorInterface $urlGenerator,
                              UserInterface $user = null)
    {
        $settings = $this->lookupSettingsFromRequest($request);

        $response = $this->handleUserAction($request, $user, $settings['base']);
        if (!is_null($response)) {
            return $response;
        }

        $listBuilder = $this->instantiateListBuilder($request, $urlGenerator, 'extended', $settings['entity']);
        if (!in_array($entity = $listBuilder->getEntity(), [ 'Exhibition', 'Person', 'Venue', 'Organizer', 'Place' ])) {
            $routeParams = [
                'entity' => $listBuilder->getEntity(),
                'filter' => $listBuilder->getQueryFilters(),
            ];

            return $this->redirectToRoute('search-index', $routeParams);
        }

        $query = $listBuilder->query();
        // echo($query->getSQL());

        $stmt = $query->execute();

        $renderParams = $this->processMapEntries($stmt, $entity);

        $templatePath = 'Search/map.html.twig';
        if ('search' != $settings['base']) {
            $templatePath = 'Search/Entity/map.html.twig';
        }

        return $this->render($templatePath, $renderParams + [
            'pageTitle' => $this->get('translator')->trans($settings['pageTitle']),
            'type' => $settings['view'],
            'disableClusteringAtZoom' => 'Person' == $entity ? 7 : 5,
            'showHeatMap' => 'Person' == $entity,
            'markerStyle' => 'Person' == $entity ? 'pie' : 'circle',
            'listBuilder' => $listBuilder,
            'form' => $this->form->createView(),
            'searches' => $this->lookupSearches($user, $settings['base']),
        ]);
    }

    /**
     * @Route("/search/select/person", name="search-select-person")
     * @Route("/search/select/location", name="search-select-location")
     * @Route("/search/select/organizer", name="search-select-organizer")
     * @Route("/search/select/holder", name="search-select-holder")
     * @Route("/search/select/exhibition", name="search-select-exhibition")
     * @Route("/search/select/birthplace", name="search-select-birthplace")
     * @Route("/search/select/deathplace", name="search-select-deathplace")
     *
     * Builds autocompleters for entity-selection
     */
    public function searchSelectAction(Request $request, UrlGeneratorInterface $urlGenerator)
    {
        $search = $request->get('q');

        $data = [];

        if (isset($search) && mb_strlen($search, 'UTF-8') >= 2) {
            $connection = $this->getDoctrine()->getManager()->getConnection();

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
                        ->andWhere(\AppBundle\Utils\SearchListBuilder::exhibitionVisibleCondition('E'))
                        ->select("E.id AS id, E.title AS text, E.startdate, E.enddate, E.displaydate")
                        ;
                    break;

                case 'search-select-birthplace':
                case 'search-select-deathplace':
                    $listBuilder = new \AppBundle\Utils\PlaceListBuilder($connection, $request, $urlGenerator, []);
                    $fields = [ 'PL.name_alternate', 'PL.name' ];
                    $queryBuilder = $listBuilder->getQueryBuilder();

                    $queryBuilder
                        ->from('Geoname', 'Pl')
                        ->innerJoin('Pl',
                                    'Person', 'P',
                                    'Pl.tgn = P.'
                                    . str_replace('search-select-', '', $request->get('_route'))
                                    . '_tgn AND P.status <> -1')
                        ->select("PL.tgn AS id, COALESCE(PL.name_alternate, PL.name) AS text")
                        ->distinct()
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

            $maxResult = intval($request->get('page_limit'));
            if ($maxResult <= 0) {
                $maxResult = 10;
            }

            $queryBuilder
                ->setFirstResult(0) // TODO: set from $request
                ->setMaxResults($maxResult);

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
        if (is_null($entity) || !in_array($entity, self::$entities)) {
            if ('search-export' == $request->get('_route') && 'Holder' == $entity) {
                // Holder has only export functionality
            }
            else {
                $entity = self::$entities[0];
            }
        }

        $this->form = $this->createSearchForm($request, $urlGenerator);

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
        $connection = $this->getDoctrine()->getManager()->getConnection();

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

            case 'Place':
                return new \AppBundle\Utils\PlaceListBuilder($connection, $request, $urlGenerator, $filters, $mode);
                break;

            case 'Exhibition':
            default:
                return new \AppBundle\Utils\ExhibitionListBuilder($connection, $request, $urlGenerator, $filters, $mode);
                break;
        }
    }
}
