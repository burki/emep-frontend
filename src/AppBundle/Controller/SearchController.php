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
        $listPage = $listPagination->get($this->pageSize, ($page - 1) * $this->pageSize);

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

        $stmt = $query->execute();

        $renderParams = $this->processMapEntries($stmt, $entity);

        return $this->render('Search/map.html.twig', $renderParams + [
            'pageTitle' => $this->get('translator')->trans('Advanced Search'),
            'disableClusteringAtZoom' => 'Person' == $entity ? 7 : 5,
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
                                              $mode = false,
                                              $entity = null)
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
        $parameters = self::array_filter_recursive($parameters, null, true); // remove empty values

        if (array_key_exists('filter', $parameters)) {
            // some values must be arrays and not scalar
            $forceArray = [
                'location' => [ 'geoname' ],
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
