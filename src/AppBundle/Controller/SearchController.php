<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Intl\Intl;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\User\UserInterface;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Ifedko\DoctrineDbalPagination\ListBuilder;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

use Pagerfanta\Pagerfanta;

/**
 *
 */
class SearchController
extends CrudController
{
    const PAGE_SIZE = 50;

    static $entities = [
        'ItemExhibition',
        'Exhibition',
        'Venue',
        'Person',
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
        $listBuilder = $this->instantiateListBuilder($request, $urlGenerator, true);

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

    /* TODO: move to shared helper */
    private function buildDisplayDate ($row) {
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
        $listBuilder = $this->instantiateListBuilder($request, $urlGenerator, true);
        if (!in_array($entity = $listBuilder->getEntity(), [ 'Exhibition', 'Venue', 'Person'])) {
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

                if ('Venue' == $entity) {
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
            'pageTitle' => 'General Search',
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

    protected function instantiateListBuilder(Request $request,
                                              UrlGeneratorInterface $urlGenerator,
                                              $extended = false)
    {
        $connection = $this->getDoctrine()->getEntityManager()->getConnection();

        $entity = $request->get('entity');
        if (!in_array($entity, self::$entities)) {
            $entity = self::$entities[0];
        }

        $venueTypes = $this->buildVenueTypes();
        $exhibitionTypes = [ 'group', 'solo', 'auction' ];
        $organizerTypes = $this->buildOrganizerTypes();

        $this->form = $this->createForm(\AppBundle\Form\Type\SearchFilterType::class, [
            'choices' => [
                'nationality' => array_flip($this->buildPersonNationalities()),
                'location_geoname' => array_flip($this->buildVenueGeonames()),
                'location_type' => array_combine($venueTypes, $venueTypes),
                'exhibition_type' => array_combine($exhibitionTypes, $exhibitionTypes),
                'organizer_type' => array_combine($organizerTypes, $organizerTypes),
                'itemexhibition_type' => array_flip($this->buildItemExhibitionTypes()),
            ],
        ]);

        $parameters = $request->query->all();
        if (array_key_exists('filter', $parameters)) {
            $this->form->submit($parameters['filter']);
        }
        $filters = $this->form->getData();

        switch ($entity) {
            case 'Exhibition':
                return new ExhibitionListBuilder($connection, $request, $urlGenerator, $filters, $extended);
                break;

            case 'Venue':
                return new VenueListBuilder($connection, $request, $urlGenerator, $filters, $extended);
                break;

            case 'Person':
                return new PersonListBuilder($connection, $request, $urlGenerator, $filters, $extended);
                break;

            case 'ItemExhibition':
            default:
                return new ItemExhibitionListBuilder($connection, $request, $urlGenerator, $filters, $extended);
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
            'pageTitle' => $this->get('translator')->trans('General Search'),
            'pager' => $pager,

            'listBuilder' => $listBuilder,
            'form' => $this->form->createView(),
            'searches' => $this->lookupSearches($user),
        ]);
    }
}

abstract class SearchListBuilder
extends ListBuilder
{
    const STATUS_DELETED = -1;
    const STATUS_EDIT = 0;
    const STATUS_PUBLISHED = 1;

    const STATUS_PENDING = -10;
    const STATUS_COMPLETED = -3;
    const STATUS_PROOFREAD = -2;
    const STATUS_PENDINGIMG = -5;

    static $STATUS_LABELS = [
        self::STATUS_PENDING => 'pending',
        self::STATUS_EDIT => 'in progress',
        self::STATUS_COMPLETED => 'completed',
        self::STATUS_PROOFREAD => 'proof read',
        self::STATUS_PENDINGIMG => 'pictures pending',
        self::STATUS_PUBLISHED => 'published',
    ];

    /**
     * Remove any elements where the callback returns true
     *
     * @param  array    $array    the array to walk
     * @param  callable $callback callback takes ($value, $key, $userdata)
     * @param  mixed    $userdata additional data passed to the callback.
     * @return array
     */
    static function array_walk_recursive_delete(array &$array, callable $callback, $userdata = null)
    {
        foreach ($array as $key => &$value) {
            if (is_array($value)) {
                $value = self::array_walk_recursive_delete($value, $callback, $userdata);
            }
            if ($callback($value, $key, $userdata)) {
                unset($array[$key]);
            }
        }

        return $array;
    }

    var $request = null;
    var $urlGenerator = null;
    var $orders = [];
    var $queryFilters = [];

    public function __construct(\Doctrine\DBAL\Connection $connection,
                                Request $request,
                                UrlGeneratorInterface $urlGenerator,
                                $queryFilters = null)
    {
        parent::__construct($connection);

        $this->request = $request;
        $this->urlGenerator = $urlGenerator;

        if (is_null($queryFilters)) {
            $queryFilters = $this->request->get('filter');
        }

        $this->setQueryFilters($queryFilters);
    }

	protected function baseQuery()
	{
    	$queryBuilder = $this->getQueryBuilder();

        $this
            ->setSelect($queryBuilder)
            ->setFrom($queryBuilder)
            ->setJoin($queryBuilder)
            ->setFilter($queryBuilder)
            ->setOrder($queryBuilder);

        return $queryBuilder;
	}

    protected function determineSortOrder()
    {
        $sortKeys = array_keys($this->orders);

        $sort = $this->request->get('sort');

        if (!in_array($sort, $sortKeys)) {
            $sort = $sortKeys[0];
        }

        $sortOrders = array_keys($this->orders[$sort]);
        $order = $this->request->get('order');
        if (!in_array($order, $sortOrders)) {
            $order = $sortOrders[0];
        }

        return [ $sort, $order ];
    }

    public function getSortInfo($key)
    {
        $info = [];

        if (!array_key_exists($key, $this->orders)) {
            return $info;
        }

        $route = $this->request->get('_route');
        $params = $this->request->query->all();

        unset($params['page']);

        $sortKeys = array_keys($this->orders);
        if ($key == $sortKeys[0]) {
            unset($params['sort']);
        }
        else {
            $params['sort'] = $key;
        }

        unset($params['order']);

        list($sort, $order) = $this->determineSortOrder();
        if ($sort == $key) {
            $info['active'] = $order;
            // determine next order
            $orders = array_keys($this->orders[$key]);
            $pos = array_search($order, $orders);
            if ($pos != count($orders) - 1) {
                $params['order'] = $orders[$pos + 1];
            }
            $info['action'] = $this->urlGenerator->generate($route, $params);
        }
        else {
            $info['action'] = $this->urlGenerator->generate($route, $params);
        }

        return $info;
    }

    protected function setOrder($queryBuilder)
    {
        list($sort, $order) = $this->determineSortOrder();

        foreach ($this->orders[$sort][$order] as $orderBy) {
            $dir = 'ASC';
            if (preg_match('/(.+)\s+(asc|desc)\s*$/i', $orderBy, $matches)) {
                $orderBy = $matches[1];
                $dir = $matches[2];
            }

            $queryBuilder->addOrderBy($orderBy, $dir);
        }

        return $this;
    }

    public function getEntity()
    {
        return $this->entity;
    }

    public function setQueryFilters($queryFilters)
    {
        if (!empty($queryFilters) && is_array($queryFilters)) {
            // $form->getData() gets 'choices' of subforms we don't care about
            foreach (array_keys($queryFilters) as $key) {
                if ('choices' == $key) {
                    unset($queryFilters[$key]);
                }
                else if (is_array($queryFilters[$key]) && array_key_exists('choices', $queryFilters[$key])) {
                    unset($queryFilters[$key]['choices']);
                }
            }

            // remove empty options
            self::array_walk_recursive_delete($queryFilters, function ($val) {
                if (is_null($val)) {
                    return true;
                }

                if (is_array($val)) {
                    return empty($val);
                }
                else if (is_string($val)) {
                    return '' === trim($val);
                }

                return false;
            });
        }

        $this->queryFilters = $queryFilters;

        return $this;
    }

    public function getQueryFilters()
    {
        return $this->queryFilters;
    }

    public function buildLikeCondition($search, $fields, $basename = 'search')
    {
        $parts = preg_split('/\s+/', $search);

        $andParts = [];
        if (count($parts) == 0) {
            return $andParts;
        }

        $bind = [];
        for ($i = 0; $i < count($parts); $i++) {
            $term = trim($parts[$i]);
            if ('' === $term) {
                continue;
            }

            $key = $basename . $i;
            $bind[$key] = '%' . $term . '%';

            $orParts = [];
            for ($j = 0; $j < count($fields); $j++) {
                $orParts[] = $fields[$j] . " LIKE :" . $key;
            }
            $andParts[] = '(' . implode(' OR ', $orParts) . ')';
        }

        if (empty($andParts)) {
            return $andParts;
        }

        return [
            'andWhere' => $andParts,
            'parameters' => $bind,
        ];
    }

    protected function addSearchFilters($queryBuilder, $fields)
    {
        if (!empty($this->queryFilters['search'])) {
            $condition = $this->buildLikeCondition($this->queryFilters['search'], $fields);

            if (!empty($condition)) {
                foreach ($condition['parameters'] as $name => $value) {
                    $queryBuilder->setParameter($name, $value);
                }
                foreach ($condition['andWhere'] as $andWhere) {
                    $queryBuilder->andWhere($andWhere);
                }
            }
        }
    }

    protected function addQueryFilters($queryBuilder)
    {
        if (array_key_exists('person', $this->queryFilters)) {
            $personFilters = & $this->queryFilters['person'];
            foreach ([ 'gender' => 'P.sex', 'nationality' => 'P.country' ] as $key => $field) {
                if (!empty($personFilters[$key])) {
                    $queryBuilder->andWhere(sprintf('%s = %s',
                                                    $field, ':' . $key))
                        ->setParameter($key, $personFilters[$key]);
                }
            }

            foreach ([ 'birthdate' => 'P.birthdate', 'deathdate' => 'P.deathdate' ] as $key => $field) {
                if (!empty($personFilters[$key]) && is_array($personFilters[$key])) {
                    foreach ([ 'from', 'until'] as $part) {
                        if (array_key_exists($part, $personFilters[$key])) {
                            $paramName = $key . '_' . $part;
                            $queryBuilder->andWhere(sprintf('YEAR(%s) %s %s',
                                                            $field,
                                                            'from' == $part ? '>=' : '<',
                                                            ':' . $paramName))
                                ->setParameter($paramName,
                                               intval($personFilters[$key][$part])
                                               + ('until' == $part ? 1 : 0));
                        }
                    }
                }
            }
        }

        if (array_key_exists('location', $this->queryFilters)) {
            $locationFilters = & $this->queryFilters['location'];
            foreach ([ 'type' => 'L.type' ] as $key => $field) {
                if (!empty($locationFilters[$key])) {
                    $queryBuilder->andWhere(sprintf('%s = %s',
                                                    $field, ':' . $key))
                        ->setParameter($key, $locationFilters[$key]);
                }
            }

            // geoname can be cc:XY or tgn:12345
            if (!empty($locationFilters[$key = 'geoname'])) {
                $typeValue = explode(':', $locationFilters[$key], 2);
                if ('cc' == $typeValue[0]) {
                    $field = 'PL.country_code';
                }
                else {
                    $field = 'L.place_tgn';
                }
                $queryBuilder->andWhere(sprintf('%s = %s',
                                                $field, ':' . $key))
                    ->setParameter($key, $typeValue[1]);
            }
        }

        if (array_key_exists('exhibition', $this->queryFilters)) {
            $exhibitionFilters = & $this->queryFilters['exhibition'];
            foreach ([ 'type' => 'E.type', 'organizer_type' => 'E.organizer_type'  ] as $key => $field) {
                if (!empty($exhibitionFilters[$key])) {
                    $queryBuilder->andWhere(sprintf('%s = %s',
                                                    $field, ':' . $key))
                        ->setParameter($key, $exhibitionFilters[$key]);
                }
            }

            foreach ([ 'date' => 'E.startdate' ] as $key => $field) {
                if (!empty($exhibitionFilters[$key]) && is_array($exhibitionFilters[$key])) {
                    foreach ([ 'from', 'until' ] as $part) {
                        if (array_key_exists($part, $exhibitionFilters[$key])) {
                            $paramName = $key . '_' . $part;
                            $queryBuilder->andWhere(sprintf('YEAR(%s) %s %s',
                                                            $field,
                                                            'from' == $part ? '>=' : '<',
                                                            ':' . $paramName))
                                ->setParameter($paramName,
                                               intval($exhibitionFilters[$key][$part])
                                               + ('until' == $part ? 1 : 0));
                        }
                    }
                }
            }
        }

        if (array_key_exists('catentry', $this->queryFilters)) {
            $itemExhibitionFilters = & $this->queryFilters['catentry'];
            foreach ([ 'type' => 'IE.type', 'forsale' => 'IE.forsale' ] as $key => $field) {
                if (!empty($itemExhibitionFilters[$key])) {
                    $queryBuilder->andWhere(sprintf('%s = %s',
                                                    $field, ':' . $key))
                        ->setParameter($key, $itemExhibitionFilters[$key]);
                }
            }

            if (!empty($itemExhibitionFilters['price_available'])) {
                $queryBuilder->andWhere('IE.price IS NOT NULL');
            }
        }
    }

    protected function buildStatusLabel($status)
    {
        return array_key_exists($status, self::$STATUS_LABELS)
            ? self::$STATUS_LABELS[$status] : $status;
    }

    protected function buildLinkedValue($val, $route, $routeParams, $format)
    {
        if ('html' != $format) {
            return false;
        }

        return sprintf('<a href="%s">%s</a>',
                       $this->urlGenerator->generate($route, $routeParams),
                       $this->formatRowValue($val, [], $format));
    }

    public function buildHeaderRow()
    {
        $ret = [];

        foreach ($this->rowDescr as $key => $descr) {
            $ret[$key] = array_key_exists('label', $descr)
                ? $descr['label'] : '';
        }

        return $ret;
    }

    protected function formatRowValue($val, $descr, $format)
    {
        if (is_null($val)) {
            return '';
        }

        if ('plain' == $format) {
            return $val;
        }

        return htmlspecialchars($val, ENT_COMPAT, 'utf-8');
    }

    protected function formatPrice($val, $currency)
    {
        static $currencies = null;

        if (empty($val) || empty($currency)) {
            return $val;
        }

        if (is_null($currencies)) {
            $currencies = [];

            $fpath = realpath(__DIR__ . '/../Resources/currencies.txt');
            if (false !== $fpath) {
                $lines = file($fpath);
                $col_name = 2;

                foreach ($lines as $line) {
                    $line = chop($line);
                    $parts = preg_split('/\t/', $line);
                    if (!empty($parts[0])) {
                        $currencies[$parts[0]] = $parts[1];
                    }
                }
            }
        }

        $symbol = array_key_exists($currency, $currencies)
            ? $currencies[$currency] : $currency;

        return $symbol . "\xC2\xA0" . $val;
    }

    public function buildRow($row, $format = 'plain')
    {
        $ret = [];
        foreach ($this->rowDescr as $key => $descr) {
            $val = null;

            if (array_key_exists('buildValue', $descr)) {
                $val = $descr['buildValue']($row, array_key_exists($key, $row) ? $row[$key] : $val,
                                            $this, $key, $format);
                if (false === $val && array_key_exists($key, $row)) {
                    // fall back to default
                    $val = $this->formatRowValue($row[$key], $descr, $format);
                }
            }
            else if (array_key_exists($key, $row)) {
                $val = $this->formatRowValue($row[$key], $descr, $format);
            }

            $ret[] = $val;
        }

        return $ret;
    }
}

class ItemExhibitionListBuilder
extends SearchListBuilder
{
    protected $entity = 'ItemExhibition';

    var $rowDescr = [
        'person' => [
            'label' => 'Person',
        ],
        'catalogueId' => [
            'label' => 'Cat.No.',
        ],
        'title' => [
            'label' => 'Title',
        ],
        'type' => [
            'label' => 'Type',
        ],
        'exhibition' => [
            'label' => 'Exhibition',
        ],
        'startdate' => [
            'label' => 'Date',
        ],
        'place' => [
            'label' => 'City',
        ],
        'location' => [
            'label' => 'Venue',
        ],
    ];

    var $orders = [
        'catalogueId' => [
            'asc' => [
                'E.startdate',
                'E.id',
                'IE.catalogue_section',
                'CAST(IE.catalogueId AS unsigned)',
                'IE.catalogueId',
                'person',
                'title',
                'IE.id',
            ],
            'desc' => [
                'E.startdate DESC',
                'E.id DESC',
                'IE.catalogue_section DESC',
                'CAST(IE.catalogueId AS unsigned) DESC',
                'IE.catalogueId DESC',
                'person DESC',
                'title DESC',
                'IE.id DESC',
            ],
        ],
        'person' => [
            'asc' => [
                'person',
                'E.startdate',
                'E.id',
                'IE.catalogue_section',
                'CAST(IE.catalogueId AS unsigned)',
                'IE.catalogueId',
                'title',
                'IE.id',
            ],
            'desc' => [
                'person DESC',
                'E.startdate DESC',
                'E.id DESC',
                'IE.catalogue_section DESC',
                'CAST(IE.catalogueId AS unsigned) DESC',
                'IE.catalogueId DESC',
                'title DESC',
                'IE.id DESC',
            ],
        ],
        'title' => [
            'asc' => [
                "IE.title = ''",
                'IE.title',
                'E.startdate',
                'E.id',
                'IE.catalogue_section',
                'CAST(IE.catalogueId AS unsigned)',
                'IE.catalogueId',
                'person',
                'IE.id',
            ],
            'desc' => [
                "IE.title = '' DESC",
                'IE.title DESC',
                'E.startdate DESC',
                'E.id DESC',
                'IE.catalogue_section DESC',
                'CAST(IE.catalogueId AS unsigned) DESC',
                'IE.catalogueId DESC',
                'person DESC',
                'IE.id DESC',
            ],
        ],
        'type' => [
            'asc' => [
                "(TypeTerm.name <> '0_unknown' AND TypeTerm.name IS NOT NULL) DESC",
                'TypeTerm.name',
                'E.startdate',
                'E.id',
                'IE.catalogue_section',
                'CAST(IE.catalogueId AS unsigned)',
                'IE.catalogueId',
                'title',
                'IE.id',
            ],
            'desc' => [
                "(TypeTerm.name <> '0_unknown' AND TypeTerm.name IS NOT NULL)",
                'TypeTerm.name DESC',
                'E.startdate DESC',
                'E.id DESC',
                'IE.catalogue_section DESC',
                'CAST(IE.catalogueId AS unsigned) DESC',
                'IE.catalogueId DESC',
                'title DESC',
                'IE.id DESC',
            ],
        ],
        'place' => [
            'asc' => [
                'L.place',
                'E.startdate',
                'E.id',
                'IE.catalogue_section',
                'CAST(IE.catalogueId AS unsigned)',
                'IE.catalogueId',
                'person',
                'title',
                'IE.id',
            ],
            'desc' => [
                'L.place DESC',
                'E.startdate DESC',
                'E.id DESC',
                'IE.catalogue_section DESC',
                'CAST(IE.catalogueId AS unsigned) DESC',
                'IE.catalogueId DESC',
                'person DESC',
                'title DESC',
                'IE.id DESC',
            ],
        ],
        'location' => [
            'asc' => [
                'L.name',
                'L.place',
                'L.id',
                'E.startdate',
                'E.id',
                'IE.catalogue_section',
                'CAST(IE.catalogueId AS unsigned)',
                'IE.catalogueId',
                'person',
                'title',
                'IE.id',
            ],
            'desc' => [
                'L.name DESC',
                'L.place DESC',
                'L.id DESC',
                'E.startdate DESC',
                'E.id DESC',
                'IE.catalogue_section DESC',
                'CAST(IE.catalogueId AS unsigned) DESC',
                'IE.catalogueId DESC',
                'person DESC',
                'title DESC',
                'IE.id DESC',
            ],
        ],
    ];

    public function __construct(\Doctrine\DBAL\Connection $connection,
                                Request $request,
                                UrlGeneratorInterface $urlGenerator,
                                $queryFilters = null,
                                $extended = false)
    {
        parent::__construct($connection, $request, $urlGenerator, $queryFilters);

        if ($extended) {
            $this->rowDescr = [
                'id' => [
                    'label' => 'ID',
                ],
                'person' => [
                    'label' => 'Person',
                ],
                'lifespan' => [
                    'label' => 'Life Span',
                ],
                'gender' => [
                    'label' => 'Gender',
                ],
                'nationality' => [
                    'label' => '(Preferred) Nationality',
                ],
                'catalogueId' => [
                    'label' => 'Cat.No.',
                ],
                'title' => [
                    'label' => 'Title',
                ],
                'type' => [
                    'label' => 'Type',
                ],
                'displaydate' => [
                    'label' => 'Creation Date',
                ],
                'owner' => [
                    'label' => 'Owner',
                ],
                'forsale' => [
                    'label' => 'For Sale',
                ],
                'price' => [
                    'label' => 'Price',
                    'buildValue' => function (&$row, $val, $listBuilder, $key, $format) {
                        if (!empty($val)) {
                            return $this->formatRowValue($this->formatPrice($val, $row['currency']),
                                                         [], $format);
                        }
                    },
                ],
                'exhibition' => [
                    'label' => 'Exhibition',
                ],
                'startdate' => [
                    'label' => 'Date',
                ],
                'place' => [
                    'label' => 'City',
                ],
                'location' => [
                    'label' => 'Venue',
                ],
                'organizers' => [
                    'label' => 'Organizing Body',
                ],
                'organizer_type' => [
                    'label' => 'Type of Organizing Body',
                ],
            ];
        }

        $this->rowDescr['startdate']['buildValue'] = function (&$row, $val, $listBuilder, $key, $format) {
            if (!empty($row['displaydate'])) {
                return $this->formatRowValue($row['displaydate'], [], $format);
            }

            return $this->formatRowValue(\AppBundle\Utils\Formatter::daterangeIncomplete($row['startdate'], $row['enddate']), [], $format);
        };

        $this->rowDescr['exhibition']['buildValue'] = function (&$row, $val, $listBuilder, $key, $format) {
            return $listBuilder->buildLinkedValue($val, 'exhibition', [ 'id' => $row['exhibition_id'] ], $format);
        };

        $this->rowDescr['location']['buildValue'] = function (&$row, $val, $listBuilder, $key, $format) {
            return $listBuilder->buildLinkedValue($val, 'location', [ 'id' => $row['location_id'] ], $format);
        };

        $this->rowDescr['person']['buildValue'] = function (&$row, $val, $listBuilder, $key, $format) {
            return $listBuilder->buildLinkedValue($val, 'person', [ 'id' => $row['person_id'] ], $format);
        };

        $this->rowDescr['place']['buildValue'] = function (&$row, $val, $listBuilder, $key, $format) {
            $key_tgn = $key . '_tgn';

            if (empty($row[$key_tgn])) {
                return false;
            }

            return $listBuilder->buildLinkedValue($val, 'place-by-tgn', [ 'tgn' => $row[$key_tgn] ], $format);
        };

        $this->rowDescr['type']['buildValue'] = function (&$row, $val, $listBuilder, $key, $format) {
            if ('0_unknown' == $val) {
                return '';
            }

            return $this->formatRowValue($val, [], $format);
        };
    }

    protected function setSelect($queryBuilder)
    {
        $queryBuilder->select([
            'SQL_CALC_FOUND_ROWS IE.id',
            'IE.id AS id',
            "CONCAT(P.lastname, ', ', IFNULL(P.firstname, '')) AS person",
            'P.id AS person_id',
            "CONCAT(IFNULL(YEAR(birthdate), ''), IF(deathdate IS NOT NULL, CONCAT('-', YEAR(deathdate)), '')) AS lifespan",
            'P.sex AS gender',
            'P.country AS nationality',
            'IE.catalogueId AS catalogueId',
            'IE.title AS title',
            'TypeTerm.name AS type',
            'IE.displaydate AS displaydate',
            'IE.owner AS owner',
            'IE.forsale AS forsale',
            'IE.price AS price',
            'E.currency AS currency',
            "E.title AS exhibition",
            "E.id AS exhibition_id",
            'DATE(E.startdate) AS startdate',
            'DATE(E.enddate) AS enddate',
            'E.displaydate AS displaydate',
            "E.type AS exhibition_type",
            'L.name AS location',
            'L.id AS location_id',
            'L.place AS place',
            'L.place_tgn AS place_tgn',
            'E.organizer_type AS organizer_type',
            "GROUP_CONCAT(O.name ORDER BY EL.ord SEPARATOR '; ') AS organizers",

            "1 AS status",
        ]);

        return $this;
    }

    protected function setFrom($queryBuilder)
    {
        $queryBuilder->from('ItemExhibition', 'IE');

        return $this;
    }

    protected function setJoin($queryBuilder)
    {
        $queryBuilder->groupBy('IE.id');

        $queryBuilder->leftJoin('IE',
                                'Person', 'P',
                                'P.id=IE.id_person AND P.status <> -1');
        $queryBuilder->join('IE',
                                'Exhibition', 'E',
                                'E.id=IE.id_exhibition AND E.status <> -1');
        $queryBuilder->leftJoin('IE',
                                'Term', 'TypeTerm',
                                'IE.type=TypeTerm.id');
        $queryBuilder->leftJoin('E',
                                'Location', 'L',
                                'E.id_location=L.id AND L.status <> -1');
        $queryBuilder->leftJoin('L',
                                'Geoname', 'PL',
                                'L.place_tgn=PL.tgn');
        $queryBuilder->leftJoin('E',
                                'ExhibitionLocation', 'EL',
                                'E.id=EL.id_exhibition AND EL.role = 0');
        $queryBuilder->leftJoin('EL',
                                'Location', 'O',
                                'O.id=EL.id_location');

        return $this;
    }

    protected function setFilter($queryBuilder)
    {
        // we show other media but not anonymous links to work
        $queryBuilder->andWhere('IE.title IS NOT NULL OR IE.id_item IS NULL');

        $this->addSearchFilters($queryBuilder, [
            'IE.title',
            'P.lastname',
            'P.firstname',
            'E.title',
            'E.title_short',
            'E.title_translit',
            'E.title_alternate',
            'E.organizing_committee',
            'L.name',
            'L.place',
        ]);

        $this->addQueryFilters($queryBuilder);

        return $this;
    }
}

class ExhibitionListBuilder
extends SearchListBuilder
{
    protected $entity = 'Exhibition';

    var $rowDescr = [
        'startdate' => [
            'label' => 'Date',
        ],
        'exhibition' => [
            'label' => 'Title',
        ],
        'place' => [
            'label' => 'City',
        ],
        'location' => [
            'label' => 'Venue',
        ],
        'type' => [
            'label' => 'Type',
        ],
        'count_itemexhibition' => [
            'label' => 'Number of Cat. Entries',
        ],
        'count_person' => [
            'label' => 'Number of Artists',
        ],
    ];

    var $orders = [
        'startdate' => [
            'asc' => [
                'E.startdate',
                'E.title',
                'E.id',
            ],
            'desc' => [
                'E.startdate DESC',
                'E.title DESC',
                'E.id DESC',
            ],
        ],
        'exhibition' => [
            'asc' => [
                'E.title',
                'E.startdate',
                'E.id',
            ],
            'desc' => [
                'E.title DESC',
                'E.startdate DESC',
                'E.id DESC',
            ],
        ],
        'location' => [
            'asc' => [
                'L.name',
                'L.place',
                'L.id',
                'E.startdate',
                'E.title',
                'E.id',
            ],
            'desc' => [
                'L.name DESC',
                'L.place DESC',
                'L.id DESC',
                'E.startdate',
                'E.title',
                'E.id',
            ],
        ],
        'place' => [
            'asc' => [
                'L.place',
                'L.name',
                'L.id',
                'E.startdate',
                'E.title',
            ],
            'desc' => [
                'L.place DESC',
                'L.name DESC',
                'L.id DESC',
                'E.startdate DESC',
                'E.title DESC',
            ],
        ],
        'type' => [
            'asc' => [
                'E.type IS NOT NULL DESC',
                'E.type',
                'E.startdate',
                'E.title',
                'E.id',
            ],
            'desc' => [
                'E.type IS NOT NULL',
                'E.type DESC',
                'E.startdate DESC',
                'E.title DESC',
                'E.id DESC',
            ],
        ],
        'count_person' => [
            'desc' => [
                'count_person DESC',
                'E.startdate',
                'E.title',
                'E.id',
            ],
            'asc' => [
                'count_person',
                'E.startdate',
                'E.title',
                'E.id',
            ],
        ],
        'count_itemexhibition' => [
            'desc' => [
                'count_itemexhibition DESC',
                'E.startdate',
                'E.title',
                'E.id',
            ],
            'asc' => [
                'count_itemexhibition',
                'E.startdate',
                'E.title',
                'E.id',
            ],
        ],
    ];

    public function __construct(\Doctrine\DBAL\Connection $connection,
                                Request $request,
                                UrlGeneratorInterface $urlGenerator,
                                $queryFilters = null,
                                $extended = false)
    {
        parent::__construct($connection, $request, $urlGenerator, $queryFilters);

        if ($extended) {
            $this->rowDescr = [
                'exhibition_id' => [
                    'label' => 'ID',
                ],
                'startdate' => [
                    'label' => 'Start Date',
                ],
                'enddate' => [
                    'label' => 'End Date',
                ],
                'displaydate' => [
                    'label' => 'Display Date',
                ],
                'exhibition' => [
                    'label' => 'Title',
                ],
                'place' => [
                    'label' => 'City',
                ],
                'location' => [
                    'label' => 'Venue',
                ],
                'type' => [
                    'label' => 'Type',
                ],
                'count_itemexhibition' => [
                    'label' => 'Number of Cat. Entries',
                ],
                'count_person' => [
                    'label' => 'Number of Artists',
                ],
                'status' => [
                    'label' => 'Status',
                    'buildValue' => function (&$row, $val, $listBuilder, $key, $format) {
                        return $this->formatRowValue($listBuilder->buildStatusLabel($val), [], $format);
                    },
                ],
            ];
        }
        else {
            $this->rowDescr['startdate']['buildValue'] = function (&$row, $val, $listBuilder, $key, $format) {
                if (!empty($row['displaydate'])) {
                    return $this->formatRowValue($row['displaydate'], [], $format);
                }

                return $this->formatRowValue(\AppBundle\Utils\Formatter::daterangeIncomplete($row['startdate'], $row['enddate']), [], $format);
            };
        }

        $this->rowDescr['exhibition']['buildValue'] = function (&$row, $val, $listBuilder, $key, $format) {
            return $listBuilder->buildLinkedValue($val, 'exhibition', [ 'id' => $row['id'] ], $format);
        };

        $this->rowDescr['location']['buildValue'] = function (&$row, $val, $listBuilder, $key, $format) {
            return $listBuilder->buildLinkedValue($val, 'location', [ 'id' => $row['location_id'] ], $format);
        };

        $this->rowDescr['place']['buildValue'] = function (&$row, $val, $listBuilder, $key, $format) {
            $key_tgn = $key . '_tgn';

            if (empty($row[$key_tgn])) {
                return false;
            }

            return $listBuilder->buildLinkedValue($val, 'place-by-tgn', [ 'tgn' => $row[$key_tgn] ], $format);
        };
    }

    protected function setSelect($queryBuilder)
    {
        $queryBuilder->select([
            'SQL_CALC_FOUND_ROWS E.id',
            'E.id AS exhibition_id',
            'E.title AS exhibition',
            'E.type AS type',
            'DATE(E.startdate) AS startdate',
            'DATE(E.enddate) AS enddate',
            'E.displaydate AS displaydate',
            'L.place AS place',
            'L.place_tgn AS place_tgn',
            'L.name AS location',
            'L.id AS location_id',
            'L.place_geo',
            'PL.latitude', 'PL.longitude',
            'E.status AS status',
            'COUNT(DISTINCT IE.id) AS count_itemexhibition',
            'COUNT(DISTINCT IE.id_person) AS count_person',
        ]);

        return $this;
    }

    protected function setFrom($queryBuilder)
    {
        $queryBuilder->from('Exhibition', 'E');

        return $this;
    }

    protected function setJoin($queryBuilder)
    {
        $queryBuilder->groupBy('E.id');

        $queryBuilder->leftJoin('E',
                                'ItemExhibition', 'IE',
                                'E.id=IE.id_exhibition AND (IE.title IS NOT NULL OR IE.id_item IS NULL)');
        $queryBuilder->leftJoin('E',
                                'Location', 'L',
                                'E.id_location=L.id AND L.status <> -1');
        $queryBuilder->leftJoin('L',
                                'Geoname', 'PL',
                                'L.place_tgn=PL.tgn');
        $queryBuilder->leftJoin('E',
                                'ExhibitionLocation', 'EL',
                                'E.id=EL.id AND EL.role = 0');
        $queryBuilder->leftJoin('EL',
                                'Location', 'O',
                                'O.id=EL.id_location');

        if (array_key_exists('person', $this->queryFilters)) {
            // so we can filter on P.*
            $queryBuilder->join('IE',
                                    'Person', 'P',
                                    'P.id=IE.id_person AND P.status <> -1');
        }

        return $this;
    }

    protected function setFilter($queryBuilder)
    {
        $queryBuilder->andWhere('E.status <> -1');

        $this->addSearchFilters($queryBuilder, [
            'E.title',
            'E.title_short',
            'E.title_translit',
            'E.title_alternate',
            'E.organizing_committee',
            'L.name',
            'L.place',
        ]);

        $this->addQueryFilters($queryBuilder);

        return $this;
    }
}

class VenueListBuilder
extends SearchListBuilder
{
    protected $entity = 'Location';

    var $rowDescr = [
        'location' => [
            'label' => 'Name',
        ],
        'place' => [
            'label' => 'City',
        ],
        'type' => [
            'label' => 'Type',
        ],
        'count_exhibition' => [
            'label' => 'Number of Exhibitions',
        ],
        'count_itemexhibition' => [
            'label' => 'Number of Cat. Entries',
        ],
    ];

    var $orders = [
        'place' => [
            'asc' => [
                'L.place',
                'L.name',
                'L.id',
            ],
            'desc' => [
                'L.place DESC',
                'L.name DESC',
                'L.id DESC',
            ],
        ],
        'location' => [
            'asc' => [
                'L.name',
                'L.place',
                'L.id',
            ],
            'desc' => [
                'L.name DESC',
                'L.place DESC',
                'L.id DESC',
            ],
        ],
        'type' => [
            'asc' => [
                'L.type IS NOT NULL DESC',
                'L.type',
                'L.place',
                'L.name',
                'L.id',
            ],
            'desc' => [
                'L.type IS NOT NULL',
                'L.type DESC',
                'L.place DESC',
                'L.name DESC',
                'L.id DESC',
            ],
        ],
        'count_exhibition' => [
            'desc' => [
                'count_exhibition DESC',
                'L.place',
                'L.name',
                'L.id',
            ],
            'asc' => [
                'count_exhibition',
                'L.place',
                'L.name',
                'L.id',
            ],
        ],
        'count_itemexhibition' => [
            'desc' => [
                'count_itemexhibition DESC',
                'L.place',
                'L.name',
                'L.id',
            ],
            'asc' => [
                'count_itemexhibition',
                'L.place',
                'L.name',
                'L.id',
            ],
        ],
    ];

    public function __construct(\Doctrine\DBAL\Connection $connection,
                                Request $request,
                                UrlGeneratorInterface $urlGenerator,
                                $queryFilters = null,
                                $extended = false)
    {
        parent::__construct($connection, $request, $urlGenerator, $queryFilters);

        if ($extended) {
            $this->rowDescr = [
                'location_id' => [
                    'label' => 'ID',
                ],
                'location' => [
                    'label' => 'Name',
                ],
                'place' => [
                    'label' => 'Place',
                ],
                'country_code' => [
                    'label' => 'Country Code',
                ],
                'type' => [
                    'label' => 'Type',
                ],
                'foundingdate' => [
                    'label' => 'Founding Date',
                ],
                'dissolutiondate' => [
                    'label' => 'Dissolution Date',
                ],
                'ulan' => [
                    'label' => 'ULAN',
                ],
                'gnd' => [
                    'label' => 'GND',
                ],
                'count_exhibition' => [
                    'label' => 'Number of Exhibitions',
                ],
                'count_itemexhibition' => [
                    'label' => 'Number of Cat. Entries',
                ],
                'status' => [
                    'label' => 'Status',
                    'buildValue' => function (&$row, $val, $listBuilder, $key, $format) {
                        return $this->formatRowValue($listBuilder->buildStatusLabel($val), [], $format);
                    },
                ],
            ];
        }

        $this->rowDescr['location']['buildValue'] = function (&$row, $val, $listBuilder, $key, $format) {
            return $listBuilder->buildLinkedValue($val, 'location', [ 'id' => $row['id'] ], $format);
        };

        $this->rowDescr['place']['buildValue'] = function (&$row, $val, $listBuilder, $key, $format) {
            if (empty($row['place_tgn'])) {
                return false;
            }

            return $listBuilder->buildLinkedValue($val, 'place-by-tgn', [ 'tgn' => $row['place_tgn'] ], $format);
        };
    }

    /**
     * @override
     */
    public function getEntity()
    {
        return 'Venue';
    }

    protected function setSelect($queryBuilder)
    {
        $queryBuilder->select([
            'SQL_CALC_FOUND_ROWS L.id',
            'L.id AS location_id',
            'L.name AS location',
            'L.place AS place',
            'L.place_tgn AS place_tgn',
            'PL.country_code AS country_code',
            'DATE(L.foundingdate) AS foundingdate',
            'DATE(L.dissolutiondate) AS dissolutiondate',
            'L.gnd AS gnd', 'L.ulan AS ulan',
            'L.type AS type',
            'L.status AS status',
            'L.place_geo',
            'PL.latitude', 'PL.longitude',
            'COUNT(DISTINCT IE.id) AS count_itemexhibition',
            '(SELECT COUNT(*) FROM Exhibition EC WHERE EC.id_location=L.id AND EC.status <> -1) AS count_exhibition',
        ]);

        return $this;
    }

    protected function setFrom($queryBuilder)
    {
        $queryBuilder->from('Location', 'L');

        return $this;
    }

    protected function setJoin($queryBuilder)
    {
        $queryBuilder->groupBy('L.id');

        $queryBuilder->leftJoin('L',
                                'Geoname', 'PL',
                                'PL.tgn=L.place_tgn');

        $queryBuilder->leftJoin('L',
                                'Exhibition', 'E',
                                'E.id_location=L.id AND E.status <> -1');

        $queryBuilder->leftJoin('E',
                                'ItemExhibition', 'IE',
                                'E.id=IE.id_exhibition AND (IE.title IS NOT NULL OR IE.id_item IS NULL)');

        if (array_key_exists('person', $this->queryFilters)) {
            // so we can filter on P.*
            $queryBuilder->join('IE',
                                    'Person', 'P',
                                    'P.id=IE.id_person AND P.status <> -1');
        }

        return $this;
    }

    protected function setFilter($queryBuilder)
    {
        // don't show organizer-only
        $queryBuilder->andWhere('L.status <> -1 AND 0 = (L.flags & 256)');

        $this->addSearchFilters($queryBuilder, [
            'L.name',
            'L.name_translit',
            'L.name_alternate',
            'L.gnd',
            'L.ulan',
            'L.place',
        ]);

        $this->addQueryFilters($queryBuilder);

        return $this;
    }
}

class PersonListBuilder
extends SearchListBuilder
{
    protected $entity = 'Person';
    protected $joinLatLong = false; // for maps

    var $rowDescr = [
        'person' => [
            'label' => 'Name',
        ],
        'birthdate' => [
            'label' => 'Year of Birth',
        ],
        'birthplace' => [
            'label' => 'Place of Birth',
        ],
        'deathdate' => [
            'label' => 'Year of Death',
        ],
        'deathplace' => [
            'label' => 'Place of Death',
        ],
        'gender' => [
            'label' => 'Gender',
        ],
        'nationality' => [
            'label' => '(Preferred) Nationality',
        ],
        'count_exhibition' => [
            'label' => 'Number of Exhibitions',
        ],
        'count_itemexhibition' => [
            'label' => 'Number of Cat. Entries',
        ],
    ];

    var $orders = [
        'person' => [
            'asc' => [
                'P.lastname',
                'P.firstname',
                'P.id',
            ],
            'desc' => [
                'P.lastname DESC',
                'P.firstname DESC',
                'P.id DESC',
            ],
        ],
        'birthdate' => [
            'desc' => [
                'P.birthdate DESC',
                'P.lastname DESC',
                'P.firstname DESC',
                'P.id DESC',
            ],
            'asc' => [
                'P.birthdate',
                'P.lastname',
                'P.firstname',
                'P.id',
            ],
        ],
        'deathdate' => [
            'desc' => [
                'P.deathdate DESC',
                'P.lastname DESC',
                'P.firstname DESC',
                'P.id DESC',
            ],
            'asc' => [
                'P.deathdate',
                'P.lastname',
                'P.firstname',
                'P.id',
            ],
        ],
        'birthplace' => [
            'asc' => [
                'P.birthplace IS NULL',
                'P.birthplace',
                'P.lastname',
                'P.firstname',
                'P.id',
            ],
            'desc' => [
                'P.birthplace IS NOT NULL',
                'P.birthplace DESC',
                'P.lastname DESC',
                'P.firstname DESC',
                'P.id DESC',
            ],
        ],
        'deathplace' => [
            'asc' => [
                'P.deathplace IS NULL',
                'P.deathplace',
                'P.lastname',
                'P.firstname',
                'P.id',
            ],
            'desc' => [
                'P.deathplace IS NOT NULL',
                'P.deathplace DESC',
                'P.lastname DESC',
                'P.firstname DESC',
                'P.id DESC',
            ],
        ],
        'nationality' => [
            'asc' => [
                'P.country IS NULL',
                'P.country',
                'P.lastname',
                'P.firstname',
                'P.id',
            ],
            'desc' => [
                'P.country IS NOT NULL',
                'P.country DESC',
                'P.lastname DESC',
                'P.firstname DESC',
                'P.id DESC',
            ],
        ],
        'gender' => [
            'asc' => [
                'P.sex IS NULL',
                'P.sex DESC',
                'P.lastname',
                'P.firstname',
                'P.id',
            ],
            'desc' => [
                'P.sex IS NOT NULL',
                'P.sex',
                'P.lastname DESC',
                'P.firstname DESC',
                'P.id DESC',
            ],
        ],
        'count_exhibition' => [
            'desc' => [
                'count_exhibition DESC',
                'P.lastname',
                'P.firstname',
                'P.id',
            ],
            'asc' => [
                'count_exhibition',
                'P.lastname',
                'P.firstname',
                'P.id',
            ],
        ],
        'count_itemexhibition' => [
            'desc' => [
                'count_itemexhibition DESC',
                'P.lastname',
                'P.firstname',
                'P.id',
            ],
            'asc' => [
                'count_itemexhibition',
                'P.lastname',
                'P.firstname',
                'P.id',
            ],
        ],
    ];

    public function __construct(\Doctrine\DBAL\Connection $connection,
                                Request $request,
                                UrlGeneratorInterface $urlGenerator,
                                $queryFilters = null,
                                $extended = false)
    {
        parent::__construct($connection, $request, $urlGenerator, $queryFilters);

        $this->joinLatLong = 'search-map' == $request->get('_route');

        if ($extended) {
            $this->rowDescr = [
                'person_id' => [
                    'label' => 'ID',
                ],
                'person' => [
                    'label' => 'Name',
                ],
                'birthdate' => [
                    'label' => 'Date of Birth',
                ],
                'birthplace' => [
                    'label' => 'Place of Birth',
                ],
                'deathdate' => [
                    'label' => 'Date of Death',
                ],
                'deathplace' => [
                    'label' => 'Place of Death',
                ],
                'nationality' => [
                    'label' => '(Primary) Nationality',
                ],
                'ulan' => [
                    'label' => 'ULAN',
                ],
                'gnd' => [
                    'label' => 'GND',
                ],
                'wikidata' => [
                    'label' => 'Wikidata',
                ],
                'count_exhibition' => [
                    'label' => 'Number of Exhibitions',
                ],
                'count_itemexhibition' => [
                    'label' => 'Number of Cat. Entries',
                ],
                'status' => [
                    'label' => 'Status',
                    'buildValue' => function (&$row, $val, $listBuilder, $key, $format) {
                        return $this->formatRowValue($listBuilder->buildStatusLabel($val), [], $format);
                    },
                ],
            ];
        }
        else {
            $this->rowDescr['birthdate']['buildValue']
                = $this->rowDescr['deathdate']['buildValue']
                = function (&$row, $val, $listBuilder, $key, $format) {
                    // year only
                    return preg_match('/^(\d+)\-/', $val, $matches)
                        && $matches[1] > 0 ? $matches[1] : '';
                    // return \AppBundle\Utils\Formatter::dateIncomplete($val);
                };
        }

        $this->rowDescr['person']['buildValue'] = function (&$row, $val, $listBuilder, $key, $format) {
            return $listBuilder->buildLinkedValue($val, 'person', [ 'id' => $row['id'] ], $format);
        };

        $this->rowDescr['birthplace']['buildValue']
            = $this->rowDescr['deathplace']['buildValue']
            = function (&$row, $val, $listBuilder, $key, $format) {
                $key_tgn = $key . '_tgn';

                if (empty($row[$key_tgn])) {
                    return false;
                }

                return $listBuilder->buildLinkedValue($val, 'place-by-tgn', [ 'tgn' => $row[$key_tgn] ], $format);
            };
    }

    protected function setSelect($queryBuilder)
    {
        if ($this->joinLatLong) {
            $queryBuilder->select([
                'SQL_CALC_FOUND_ROWS P.id',
                "CONCAT(P.lastname, ', ', IFNULL(P.firstname, '')) AS person",
                'P.id AS person_id',
                "DATE(P.birthdate) AS birthdate",
                "P.birthplace AS birthplace",
                "P.birthplace_tgn AS birthplace_tgn",
                "DATE(P.deathdate) AS deathdate",
                "P.deathplace AS deathplace",
                "P.deathplace_tgn AS deathplace_tgn",
                "P.ulan AS ulan",
                "P.pnd AS gnd",
                "P.wikidata AS wikidata",
                'P.sex AS gender',
                'P.country AS nationality',
                'P.status AS status',
                'PB.latitude AS birthplace_latitude',
                'PB.longitude AS birthplace_longitude',
                'PD.latitude AS deathplace_latitude',
                'PD.longitude AS deathplace_longitude',
            ]);

            return $this;
        }

        $queryBuilder->select([
            'SQL_CALC_FOUND_ROWS P.id',
            "CONCAT(P.lastname, ', ', IFNULL(P.firstname, '')) AS person",
            'P.id AS person_id',
            "DATE(P.birthdate) AS birthdate",
            "P.birthplace AS birthplace",
            "P.birthplace_tgn AS birthplace_tgn",
            "DATE(P.deathdate) AS deathdate",
            "P.deathplace AS deathplace",
            "P.deathplace_tgn AS deathplace_tgn",
            "P.ulan AS ulan",
            "P.pnd AS gnd",
            "P.wikidata AS wikidata",
            'P.sex AS gender',
            'P.country AS nationality',
            'P.status AS status',
            'COUNT(DISTINCT IE.id_exhibition) AS count_exhibition',
            'COUNT(DISTINCT IE.id) AS count_itemexhibition',
        ]);

        return $this;
    }

    protected function setFrom($queryBuilder)
    {
        $queryBuilder->from('Person', 'P');

        return $this;
    }

    protected function setJoin($queryBuilder)
    {
        $queryBuilder->groupBy('P.id');

        $queryBuilder->leftJoin('P',
                                'ItemExhibition', 'IE',
                                'IE.id_person=P.id AND (IE.title IS NOT NULL OR IE.id_item IS NULL)');

        if (array_key_exists('exhibition', $this->queryFilters) || array_key_exists('location', $this->queryFilters)) {
            // so we can filter on E.*
            $queryBuilder->leftJoin('IE',
                                    'Exhibition', 'E',
                                    'E.id=IE.id_exhibition AND (IE.title IS NOT NULL OR IE.id_item IS NULL)');

            if (array_key_exists('location', $this->queryFilters)) {
                // so we can filter on E.*
                $queryBuilder->leftJoin('E',
                                        'Location', 'L',
                                        'E.id_location=L.id AND L.status <> -1');
                $queryBuilder->leftJoin('L',
                                        'Geoname', 'PL',
                                        'L.place_tgn=PL.tgn');
            }
        }

        if ($this->joinLatLong) {
            $queryBuilder->leftJoin('P',
                                    'Geoname', 'PB',
                                    'P.birthplace_tgn=PB.tgn');
            $queryBuilder->leftJoin('P',
                                    'Geoname', 'PD',
                                    'P.deathplace_tgn=PD.tgn');
        }

        return $this;
    }

    protected function setFilter($queryBuilder)
    {
        // don't show deleted
        $queryBuilder->andWhere('P.status <> -1');

        $this->addSearchFilters($queryBuilder, [
            'P.lastname',
            'P.firstname',
            'P.pnd',
            'P.ulan',
            'P.name_variant',
            'P.name_variant_ulan',
            'P.birthplace',
            'P.deathplace',
        ]);

        $this->addQueryFilters($queryBuilder);

        return $this;
    }
}

class SearchListPagination
{
    const DEFAULT_LIMIT = 20;
    const DEFAULT_OFFSET = 0;

    /**
     * @var \Ifedko\DoctrineDbalPagination\ListBuilder
     */
    private $listQueryBuilder;

    /**
     * @var callable|null
     */
    private $pageItemsMapCallback;

    /**
     * @param \Ifedko\DoctrineDbalPagination\ListBuilder $listQueryBuilder
     */
    public function __construct(ListBuilder $listQueryBuilder)
    {
        $this->listQueryBuilder = $listQueryBuilder;
    }

    /**
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function get($limit, $offset)
    {
        $limit = (intval($limit) > 0) ? intval($limit) : self::DEFAULT_LIMIT;
        $offset = (intval($offset) >= 0) ? $offset : self::DEFAULT_OFFSET;

        $query = $this->listQueryBuilder->query();
        /*
        echo($query
            ->setMaxResults($limit)->setFirstResult($offset)->getSQL());
        */
        $pageItems = $query
            ->setMaxResults($limit)->setFirstResult($offset)->execute()->fetchAll();


        $conn = $query->getConnection();
        $stmt = $conn->query("SELECT FOUND_ROWS() AS found_rows");
        $totalResult = $stmt->fetchAll();

        return [
            'limit' => $limit,
            'offset' => $offset,
            'total' => $totalResult[0]['found_rows'],

            'items' => is_null($this->pageItemsMapCallback) ?
                $pageItems : array_map($this->pageItemsMapCallback, $pageItems),

            'sorting' => $this->listQueryBuilder->sortingParameters()
        ];
    }

    public function definePageItemsMapCallback($callback)
    {
        $this->pageItemsMapCallback = $callback;
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
