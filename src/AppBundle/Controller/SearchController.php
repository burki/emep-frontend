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
                return new \AppBundle\Utils\ExhibitionListBuilder($connection, $request, $urlGenerator, $filters, $extended);
                break;

            case 'Venue':
                return new \AppBundle\Utils\VenueListBuilder($connection, $request, $urlGenerator, $filters, $extended);
                break;

            case 'Person':
                return new \AppBundle\Utils\PersonListBuilder($connection, $request, $urlGenerator, $filters, $extended);
                break;

            case 'ItemExhibition':
            default:
                return new \AppBundle\Utils\ItemExhibitionListBuilder($connection, $request, $urlGenerator, $filters, $extended);
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
