<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use AppBundle\Utils\CsvResponse;

/**
 *
 */
class ExhibitionController
extends CrudController
{
    use MapBuilderTrait;
    use StatisticsBuilderTrait;
    use SharingBuilderTrait;

    protected function buildCountries()
    {
        $qb = $this->getDoctrine()
                ->getManager()
                ->createQueryBuilder();

        $qb->select([
                'PL.countryCode',
            ])
            ->distinct()
            ->from('AppBundle:Exhibition', 'E')
            ->innerJoin('E.location', 'L')
            ->innerJoin('L.place', 'PL')
            ->where(\AppBundle\Utils\SearchListBuilder::exhibitionVisibleCondition('E'))
            ->andWhere('PL.countryCode IS NOT NULL')
            ;

        return $this->buildActiveCountries($qb);
    }

    protected function buildSaveSearchParams(Request $request, UrlGeneratorInterface $urlGenerator)
    {
        $settings = $this->lookupSettingsFromRequest($request);
        $route = $settings['base'];

        $this->form = $this->createSearchForm($request, $urlGenerator);

        $listBuilder = $this->instantiateListBuilder($request, $urlGenerator, false, 'Exhibition');
        $filters = $listBuilder->getQueryFilters(true);
        if (empty($filters)) {
            return [ $route, [] ];
        }

        $routeParams = [
            'filter' => $filters,
        ];

        return [ $route, $routeParams ];
    }

    /**
     * @Route("/exhibition/save", name="exhibition-save")
     */
    public function saveSearchAction(Request $request,
                                     UrlGeneratorInterface $urlGenerator,
                                     UserInterface $user)
    {
        return $this->handleSaveSearchAction($request, $urlGenerator, $user);
    }

    /**
     * @Route("/exhibition/shared.embed/{persons}", name="exhibition-shared-partial")
     * @Route("/exhibition/shared/{persons}", name="exhibition-shared")
     */
    public function sharedAction(Request $request, $persons = null)
    {
        if (!is_null($persons)) {
            $persons = explode(',', $persons);
        }

        if (is_null($persons) || count($persons) < 2) {
            throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException("Invalid argument");
        }

        $names = [];
        $personRepo = $this->getDoctrine()
                ->getRepository('AppBundle:Person');
        for ($i = 0; $i < 2; $i++) {
            $criteria = new \Doctrine\Common\Collections\Criteria();

            $criteria->where($criteria->expr()->eq('id', $persons[$i]));

            $criteria->andWhere($criteria->expr()->neq('status', -1));

            $matching = $personRepo->matching($criteria);
            if (0 == count($matching)) {
                throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException("Invalid argument");
            }

            $names[] = $matching[0]->getFullname(true);
        }

        $qb = $this->getDoctrine()
                ->getManager()
                ->createQueryBuilder();

        $qb->select([
                'E',
                "E.startdate HIDDEN dateSort",
                "CONCAT(COALESCE(P.alternateName, P.name), E.startdate) HIDDEN placeSort"
            ])
            ->from('AppBundle:Exhibition', 'E')
            ->leftJoin('E.location', 'L')
            ->leftJoin('L.place', 'P')
            ->innerJoin('AppBundle:ItemExhibition', 'IE1',
                       \Doctrine\ORM\Query\Expr\Join::WITH,
                       'IE1.exhibition = E')
            ->innerJoin('AppBundle:Person', 'P1',
                       \Doctrine\ORM\Query\Expr\Join::WITH,
                       'IE1.person = P1 AND P1.id=:person1')
            ->innerJoin('AppBundle:ItemExhibition', 'IE2',
                       \Doctrine\ORM\Query\Expr\Join::WITH,
                       'IE2.exhibition = E')
            ->innerJoin('AppBundle:Person', 'P2',
                       \Doctrine\ORM\Query\Expr\Join::WITH,
                       'IE2.person = P2 AND P2.id=:person2')
            ->setParameters([ 'person1' => $persons[0], 'person2' => $persons[1] ])
            ->where(\AppBundle\Utils\SearchListBuilder::exhibitionVisibleCondition('E'))
            ->groupBy('E.id')
            ->orderBy('dateSort')
            ;

        $pagination = $this->buildPagination($request, $qb->getQuery(), [
            'defaultSortFieldName' => 'dateSort', 'defaultSortDirection' => 'asc',
            'pageSize' => 1000,
        ]);

        return $this->render('Exhibition/shared.html.twig', [
            'pageTitle' => $this->get('translator')->trans('Common Exhibitions of')
                . ' ' . implode(' and ', $names),
            'pagination' => $pagination,
        ]);
    }

    /* TODO: try to merge with inverse method in PersonController */
    protected function findSimilar($entity)
    {
        $em = $this->getDoctrine()->getManager();
        $dbconn = $em->getConnection();

        // build all the ids
        $personIds = [];
        foreach ($entity->getArtists() as $person) {
            if ($person->getStatus() <> -1) {
                $personIds[] = $person->getId();
            }
        }

        $numArtists = count($personIds);
        if (0 == $numArtists) {
            return [];
        }

        $querystr = "SELECT DISTINCT id_person, id_exhibition"
                  . " FROM ItemExhibition"
                  . " INNER JOIN Exhibition ON ItemExhibition.id_exhibition = Exhibition.id"
                  . " AND " . \AppBundle\Utils\SearchListBuilder::exhibitionVisibleCondition('Exhibition')
                  . " WHERE id_person IN (" . join(', ', $personIds) . ')'
                  . " AND id_exhibition <> " . $entity->getId()
                  . " ORDER BY id_exhibition";

        $personsByExhibition = [];
        $stmt = $dbconn->query($querystr);
        while ($row = $stmt->fetch()) {
            if (!array_key_exists($row['id_exhibition'], $personsByExhibition)) {
                $personsByExhibition[$row['id_exhibition']] = [];
            }
            $personsByExhibition[$row['id_exhibition']][] = $row['id_person'];
        }

        $jaccardIndex = [];
        $exhibitionIds = array_keys($personsByExhibition);
        if (count($exhibitionIds) > 0) {
            $querystr = "SELECT Exhibition.title AS title, id_exhibition, COUNT(DISTINCT id_person) AS num_artists"
                      . " FROM ItemExhibition"
                      . " LEFT OUTER JOIN Exhibition ON ItemExhibition.id_exhibition=Exhibition.id"
                      . " AND " . \AppBundle\Utils\SearchListBuilder::exhibitionVisibleCondition('Exhibition')
                      . " WHERE id_exhibition IN (" . join(', ', $exhibitionIds) . ')'
                      . " GROUP BY id_exhibition";
            $stmt = $dbconn->query($querystr);
            while ($row = $stmt->fetch()) {
                $numShared = count($personsByExhibition[$row['id_exhibition']]);
                $jaccardIndex[$row['id_exhibition']] = [
                    'title' => $row['title'],
                    'count' => $numShared,
                    'coefficient' =>
                        1.0
                            * $numShared // shared
                            /
                            ($row['num_artists'] + $numArtists - $numShared),
                ];
            }

            uasort($jaccardIndex,
                function ($a, $b) {
                    if ($a['coefficient'] == $b['coefficient']) {
                        return 0;
                    }
                    // highest first
                    return $a['coefficient'] < $b['coefficient'] ? 1 : -1;
                }
            );
        }

        return $jaccardIndex;
    }

    protected function hydrateSimilar($result)
    {
        $exhibitionsByIds = [];

        $exhibitionIds = array_keys($result);
        if (!empty($exhibitionIds)) {
            $exhibitions = $this->hydrateExhibitions($exhibitionIds);
            $exhibitionsByIds = array_combine(
                array_map(function ($exhibition) { return $exhibition->getId(); }, $exhibitions),
                $exhibitions
            );
        }

        return $exhibitionsByIds;
    }

    protected function findCatalogueEntries($exhibition, $sort = '')
    {
        // get the catalogue entries by exhibition
        $qb = $this->getDoctrine()
                ->getManager()
                ->createQueryBuilder();

        $qb->select([
                'IE',
            ])
            ->from('AppBundle:ItemExhibition', 'IE')
            ->leftJoin('IE.person', 'P')
            ->where("IE.exhibition = :exhibition")
            ->andWhere('IE.title IS NOT NULL OR IE.item IS NULL')
            ;

        $results = $qb->getQuery()
            ->setParameter('exhibition', $exhibition)
            ->getResult();

        // sort, either catalogueId or person first
        $sortPersonFirst = 'person' == $sort || $exhibition->isSortedByPerson();

        usort($results, function ($a, $b) use ($sortPersonFirst) {
            $personA = $a->getPerson();
            $personB = $b->getPerson();

            $sortPartsA = [ $a->catalogueSection . ' ' . $a->catalogueId, $personA != null ? $a->getPerson()->getFullname() : '' ];
            $sortPartsB = [ $b->catalogueSection . ' ' . $b->catalogueId, $personB != null ? $b->getPerson()->getFullname() : '' ];
            if ($sortPersonFirst) {
                $sortPartsA = array_reverse($sortPartsA);
                $sortPartsB = array_reverse($sortPartsB);
            }

            return strnatcmp(implode('', $sortPartsA), implode('', $sortPartsB));
        });

        return $results;
    }

    /**
     * @Route("/exhibition/{id}/catalogue/csv", requirements={"id" = "\d+"}, name="exhibition-catalogue-csv")
     */
    public function detailActionCatalogueCsv(Request $request, $id = null)
    {
        $routeName = $request->get('_route'); $routeParams = [];

        $repo = $this->getDoctrine()
            ->getRepository('AppBundle:Exhibition');

        if (!empty($id)) {
            $routeParams = [ 'id' => $id ];
            $exhibition = $repo->findOneById($id);
        }

        if (!isset($exhibition) || !$exhibition->checkStatus(-1)) {
            return $this->redirectToRoute('exhibition-index');
        }

        $result = $this->findCatalogueEntries($exhibition, $request->get('sort'));

        $csvResult = [];

        foreach ($result as $catEntry) {
            $person = $catEntry->getPerson();

            $csvResult[] = [
                $catEntry->catalogueId,
                $catEntry->title,
                !is_null($person) ? $person->getFullname(true) : '',
                $catEntry->getDisplaylocation(),
                $catEntry->getTypeName(),
                $catEntry->getTypeParts(false),
                $catEntry->getDisplaydate(),
                $catEntry->getOwnerFull(),
                $catEntry->isForsale() ? 'Y' : '',
                $catEntry->getPrice()
            ];
        }

        return new CsvResponse($csvResult, 200,
                               explode(', ', 'Cat. No., Title, Person, Room, Type, Additional, Creation Date, Owner, For Sale, Price'));
    }

    /**
     * @Route("/exhibition/{id}/similars/csv", requirements={"id" = "\d+"}, name="exhibition-similars-csv")
     */
    public function detailActionSimilarsCsv(Request $request, $id = null)
    {
        $routeName = $request->get('_route'); $routeParams = [];

        $repo = $this->getDoctrine()
            ->getRepository('AppBundle:Exhibition');

        if (!empty($id)) {
            $routeParams = [ 'id' => $id ];
            $exhibition = $repo->findOneById($id);
        }

        if (!isset($exhibition) || !$exhibition->checkStatus(1)) {
            return $this->redirectToRoute('exhibition-index');
        }

        $result = $this->findSimilar($exhibition);
        $exhibitionsByIds = $this->hydrateSimilar($result);

        $csvResult = [];
        foreach ($result as $id => $value) {
            if ($value['count'] > 0) {
                $exhibition = $exhibitionsByIds[$id];

                $csvResult[] = [
                    $exhibition->getStartdate(), $exhibition->getEnddate(), $exhibition->getDisplaydate(),
                    $exhibition->getTitle(),
                    $exhibition->getLocation()->getPlaceLabel(),
                    $exhibition->getLocation()->getName(),
                    $value['count']
                ];
            }
        }

        return new CsvResponse($csvResult, 200, explode(', ', 'Start Date, End Date, Display Date, Title, City, Venue, # of common Artists'));
    }

    /**
     * @Route("/exhibition/{id}/artists/csv", requirements={"id" = "\d+"}, name="exhibition-artists-csv")
     */
    public function detailActionArtistsCsv(Request $request, $id = null)
    {
        $repo = $this->getDoctrine()
            ->getRepository('AppBundle:Exhibition');

        if (!empty($id)) {
            $routeParams = [ 'id' => $id ];
            $exhibition = $repo->findOneById($id);
        }

        if (!isset($exhibition) || !$exhibition->checkStatus(-1)) {
            return $this->redirectToRoute('exhibition-index');
        }

        $catalogueEntries = $this->findCatalogueEntries($exhibition, $request->get('sort'));

        list($artists, $catalogueEntriesByPersonCount) = $this->buildArtistsCount($catalogueEntries, true);

        $csvResult = array_map(function ($person) use ($catalogueEntriesByPersonCount) {
                $count = array_key_exists($person->getId(), $catalogueEntriesByPersonCount)
                    ? $catalogueEntriesByPersonCount[$person->getId()] : 0;
                return [ $person->getFullname(false), $person->getNationality(), $person->getBirthDate(), $person->getDeathDate(), $count ];
            }, $artists);

        return new CsvResponse($csvResult, 200, explode(', ', 'Name, Nationality, Date of Birth, Date of Death, # of Cat. Entries'));
    }

    protected function lookupExhibitionGroup($em, $exhibition)
    {
        $conn = $em->getConnection();

        $queryBuilder = $conn->createQueryBuilder();

        $queryBuilder
            ->select('id_exhibition')
            ->from('ExhibitionGroup', 'EG')
            ->where('id_exhibition=:id_exhibition OR id_exhibition2=:id_exhibition')
            ->setParameter('id_exhibition', $exhibition->getId())
            ->orderBy('id_exhibition', 'asc');

        $ids = $queryBuilder->execute()->fetchAll(\PDO::FETCH_COLUMN);

        if (empty($ids)) {
            return [];
        }

        $queryBuilder
            ->select('id_exhibition2')
            ->from('ExhibitionGroup', 'EG')
            ->where('id_exhibition=:id_exhibition')
            ->setParameter('id_exhibition', $ids[0])
            ;

        $ids = array_merge($ids, $queryBuilder->execute()->fetchAll(\PDO::FETCH_COLUMN));

        // remove oneself
        $ids = array_diff($ids, [ $exhibition->getId() ]);

        if (empty($ids)) {
            return [];
        }

        return $this->hydrateExhibitions($ids, false, true);
    }

    private function buildArtistsCount($catalogueEntries, $sortByName = false)
    {
        $artists = []; $catalogueEntriesByPersonCount = [];
        foreach ($catalogueEntries as $entry) {
            $person = $entry->getPerson();
            if (!is_null($person)) {
                $personId = $person->getId();
                if (!array_key_exists($personId, $catalogueEntriesByPersonCount)) {
                    $catalogueEntriesByPersonCount[$personId] = 0;
                    $artists[] = $person;
                }
                ++$catalogueEntriesByPersonCount[$personId];
            }
        }

        if ($sortByName) {
            usort($artists, function ($personA, $personB) { return strcmp($personA->getFullname(), $personB->getFullname()); });
        }

        return [ $artists, $catalogueEntriesByPersonCount];
    }


    /**
     * @Route("/exhibition/{id}.jsonld", name="exhibition-jsonld")
     * @Route("/exhibition/{id}", requirements={"id" = "\d+"}, name="exhibition")
     */
    public function detailAction(Request $request, UrlGeneratorInterface $urlGenerator, $id, $itemexhibitionId = null)
    {
        $routeName = $request->get('_route'); $routeParams = [];

        $repo = $this->getDoctrine()
            ->getRepository('AppBundle:Exhibition');

        if (!empty($id)) {
            $routeParams = [ 'id' => $id ];
            $exhibition = $repo->findOneById($id);
        }

        if (!isset($exhibition) || !$exhibition->checkStatus(-1)) {
            return $this->redirectToRoute('exhibition-index');
        }

        $citeProc = $this->instantiateCiteProc($request->getLocale());
        if ($exhibition->hasInfo()) {
            // expand the publications
            $exhibition->buildInfoFull($this->getDoctrine()->getManager(), $citeProc);
        }

        $exhibition->setDateModified(\AppBundle\Utils\ExhibitionListBuilder::fetchDateModified($this->getDoctrine()->getConnection(), $exhibition->getId()));

        $catalogueEntries = $this->findCatalogueEntries($exhibition, $request->get('sort'));

        list($artists, $catalogueEntriesByPersonCount) = $this->buildArtistsCount($catalogueEntries);

        $artistsCountries = [];
        $genderSplit = [ 'M' => 0, 'F' => 0 ]; // first male, second female
        foreach ($artists as $artist) {
            $gender = $artist->getGender();
            if (!empty($gender)) {
                ++$genderSplit[$gender];
            }

            if (!in_array($artist->getNationality(), $artistsCountries)) {
                $artistsCountries[] = $artist->getNationality();
            }
        }

        $catalogues = $exhibition->findBibitem($em = $this->getDoctrine()->getManager(), 1);
        // expand the notes
        foreach ($catalogues as $catalogue) {
            if ($catalogue->hasInfo()) {
                $catalogue->buildInfoFull($em, $citeProc);
            }
        }
        if (count($catalogues)) {
            $exhibition->setSubjectOf($catalogues);
        }

        if (in_array($request->get('_route'), [ 'exhibition-jsonld' ])) {
            return new JsonLdResponse($exhibition->jsonLdSerialize($request->getLocale()));
        }

        // for traveling, find related
        $relatedExhibitions = $exhibition->isTraveling()
            ? $this->lookupExhibitionGroup($em, $exhibition)
            : [];

        list($charts, $placesActivityAvailable) = $this->buildDetailCharts($exhibition, $artists);

        $similar = $this->findSimilar($exhibition);
        return $this->render('Exhibition/detail.html.twig', [
            'pageTitle' => $exhibition->title, // TODO: dates in brackets
            'exhibition' => $exhibition,
            'mapMarkers' => $this->buildMapMarkers($exhibition, $artists),
            'catalogue' => $catalogues,
            'citeProc' => $citeProc,
            'artists' => $artists,
            'catalogueEntries' => $catalogueEntries,
            'catalogueEntriesByPersonCount' => $catalogueEntriesByPersonCount,
            'showWorks' => false, // !empty($_SESSION['user']),
            'related' => $relatedExhibitions,
            'similar' => $similar,
            'similarHydrated' => $this->hydrateSimilar($similar),
            'currentPageId' => $id,
            'catalogueStatus' => \AppBundle\Utils\SearchListBuilder::$STATUS_LABELS,
            'genderSplit' => $genderSplit,
            'charts' => $charts,
            'placesActivityAvailable' => $placesActivityAvailable,
            'numNationalities' => count($artistsCountries),
            'pageMeta' => [
                'canonical' => $urlGenerator->generate(
                    'exhibition',
                    [ 'id' => $exhibition->id ],
                    UrlGeneratorInterface::ABSOLUTE_URL
                ),
                'jsonLd' => $exhibition->jsonLdSerialize($request->getLocale()),
                /*
                'og' => $this->buildOg($exhibition, $routeName, $routeParams),
                'twitter' => $this->buildTwitter($exhibition, $routeName, $routeParams),
                */
            ],
        ]);
    }

    protected function buildMapMarkers($exhibition, $persons)
    {
        $markers = [];

        $places = [];

        // exhibition
        $location = $exhibition->getLocation();
        if (!is_null($location)) {
            $geo = $location->getGeo(true);
            if (!is_null($geo)) {
                $info = [
                    'geo' => $geo,
                    'exhibition' => $exhibition,
                ];

                $place = $location->getPlace();
                if (is_null($place)) {
                    $info += [ 'name' => $location->getPlaceLabel() ];
                }
                else {
                    $info += [ 'name' => $place->getNameLocalized(), 'tgn' => $place->getTgn() ];
                }

                $places[] = [
                    'info' => $info,
                    'label' => 'Exhibition',
                ];
            }
        }

        // places of activity for this exhibition
        $placesByTgn = [];
        foreach ($persons as $person) {
            $addresses = $person->getAddressesSeparated($exhibition->getId(), false, true);
            // we currently have geo, so get all tgn
            $tgns = array_filter(array_unique(array_column($addresses, 'place_tgn')));
            foreach ($tgns as $tgn) {
                if (!array_key_exists($tgn, $placesByTgn)) {
                    $placesByTgn[$tgn] = [ 'persons' => [] ];
                }

                $placesByTgn[$tgn]['persons'][] = [
                    'person' => $person,
                    'addresses' => $addresses,
                ];
            }
        }

        $tgns = array_keys($placesByTgn);
        if (!empty($tgns)) {
            $maxDisplay = 15;
            foreach ($this->hydratePlaces($tgns, true) as $place) {
                if (!empty($place->getGeo())) {
                    $placeInfo = [
                        'label' => 'Place of Activity',
                        'info' => [
                            'tgn' => $tgn = $place->getTgn(),
                            'name' => $place->getNameLocalized(),
                            'geo' => $place->getGeo(),
                        ],
                    ];

                    // add the persons
                    $entriesByFullname = [];

                    foreach ($placesByTgn[$tgn]['persons'] as $entry) {
                        $person = & $entry['person'];
                        list($route, $routeParams) = $person->getRouteInfo();
                        $entriesByFullname[$person->getFullname()] = sprintf('<a href="%s">%s</a>',
                                                                             $this->generateUrl($route, $routeParams),
                                                                             htmlspecialchars($person->getFullname(true), ENT_QUOTES));
                    }

                    ksort($entriesByFullname);

                    $entries = array_values($entriesByFullname);
                    $countEntries = count($entries);
                    if ($countEntries <= $maxDisplay) {
                        $entry_list = implode('<br />', $entries);
                    }
                    else {
                        $entry_list = implode('<br />', array_slice($entries, 0, $maxDisplay - 1))
                                    . sprintf('<br />... (%d more)', $countEntries - ($maxDisplay - 1));
                    }

                    $placeInfo['html'] = $entry_list;

                    $places[] = $placeInfo;
                }
            }
        }

        foreach ($places as $place) {
            $value = $group = null;
            switch ($place['label']) {
                case 'Place of Activity':
                    $group = 'birthDeath';
                    $value = [
                        'icon' => 'yellowIcon',
                        'html' => sprintf('<b>%s</b>: <a href="%s">%s</a><br />%s',
                                          $place['label'],
                                          htmlspecialchars($this->generateUrl('place-by-tgn', [
                                               'tgn' => $place['info']['tgn'],
                                          ])),
                                          htmlspecialchars($place['info']['name'], ENT_QUOTES),
                                          $place['html'])
                    ];
                    break;

                case 'Exhibition':
                    $group = 'exhibition';
                    $exhibition = $place['info']['exhibition'];
                    $value = [
                        'icon' => 'blueIcon',
                        'html' =>  sprintf('<a href="%s">%s</a> at <a href="%s">%s</a> (%s)',
                                htmlspecialchars($this->generateUrl('exhibition', [
                                    'id' => $exhibition->getId(),
                                ])),
                                htmlspecialchars($exhibition->getTitleListing(), ENT_QUOTES),
                                htmlspecialchars($this->generateUrl('location', [
                                    'id' => $exhibition->getLocation()->getId(),
                                ])),
                                htmlspecialchars($exhibition->getLocation()->getNameListing(), ENT_QUOTES),
                                $this->buildDisplayDate($exhibition)
                        ),
                    ];
                    break;
            }

            if (is_null($value)) {
                continue;
            }

            if (!array_key_exists($geo = $place['info']['geo'], $markers)) {
                $markers[$geo] = [
                    'place' => $place['info'],
                    'groupedEntries' => [],
                ];
            }

            if (!array_key_exists($group, $markers[$geo]['groupedEntries'])) {
                $markers[$geo]['groupedEntries'][$group] = [];
            }

            $markers[$geo]['groupedEntries'][$group][] = $value;
        }

        return $markers;
    }

    /**
     * @Route("/exhibition/{id}/{itemexhibitionId}", requirements={"id" = "\d+", "itemexhibitionId" = "\d+"}, name="itemexhibition")
     */
    public function detailItemExhibitionAction(Request $request, UrlGeneratorInterface $urlGenerator, $id, $itemexhibitionId)
    {
        return $this->detailAction($request, $urlGenerator, $id, $itemexhibitionId);
    }

    private function buildDetailCharts($exhibition, &$persons)
    {
        $charts = [];

        // genderStatsStatisticsFormat
        $template = $this->get('twig')->loadTemplate('Statistics/exhibition-gender-stats.html.twig');
        $charts[] = $template->renderBlock('chart', [
            'container' => 'container-artist-by-gender',
            'data' => $this->assoc2NameYArray($this->artistsByGenderExhibitionStatistics($exhibition->getId())),
            'exhibitionId' => $exhibition->getId(),
        ]);

        // display the artists by birth-year
        $stats = $this->exhibitionAgeDistribution($em = $this->getDoctrine()->getManager(), $exhibition->getId());
        $ageCount = & $stats['age_count'];

        $categories = $total = [];
        for ($age = $stats['min_age']; $age <= $stats['max_age'] && $age < 120; $age++) {
            $categories[] = $age; // 0 == $age % 5 ? $year : '';

            foreach ([ 'living', 'deceased' ] as $cat) {
                $total['age_' . $cat][$age] = [
                    'name' => $age,
                    'y' => isset($ageCount[$age]) && isset($ageCount[$age][$cat])
                        ? intval($ageCount[$age][$cat]) : 0,
                ];
            }
        }

        $template = $this->get('twig')->loadTemplate('Statistics/person-exhibition-age.html.twig');
        $charts[] = $template->renderBlock('chart', [
            'container' => 'container-age',
            'categories' => json_encode($categories),
            'age_at_exhibition_living' => json_encode(array_values($total['age_living'])),
            'age_at_exhibition_deceased' => json_encode(array_values($total['age_deceased'])),
            'exhibition_id' => $exhibition->getId(),
        ]);

        // artists' nationality
        $template = $this->get('twig')->loadTemplate('Statistics/exhibition-nationality-stats.html.twig');
        $charts[] = $template->renderBlock('chart', [
            'container' => 'container-nationalities-pie',
            'data' => $this->artistsNationalityByExhibitionStatistics($exhibition->getId()),
            'exhibitionId' => $exhibition->getId(),
        ]);

        // places of activity for this exhibition
        $countByTgn = [];
        $countUnknown = 0;
        $placesOfActivityAvailable = false;

        foreach ($persons as $person) {
            $found = false;

            $addresses = $person->getAddressesSeparated($exhibition->getId(), false, true);
            // we currently have geo, so get all tgn
            $tgns = array_filter(array_unique(array_column($addresses, 'place_tgn')));
            foreach ($tgns as $tgn) {
                $found = true;
                if (!array_key_exists($tgn, $countByTgn)) {
                    $countByTgn[$tgn] = 0;
                }

                ++$countByTgn[$tgn];
            }
            if (!$found) {
                ++$countUnknown;
            }
        }

        if (!empty($countByTgn)) {
            $placesOfActivityAvailable = true;

            $placesOfActivity = [];

            if ($countUnknown > 0) {
                $placesOfActivity['[unknown]'] = $countUnknown;
            }

            foreach ($this->hydratePlaces(array_keys($countByTgn), true) as $place) {
                $placesOfActivity[$place->getNameLocalized()] = $countByTgn[$place->getTgn()];
            }

            arsort($placesOfActivity);

            $template = $this->get('twig')->loadTemplate('Statistics/exhibition-places-activity-stats.html.twig');
            $charts[] = $chart = $template->renderBlock('chart', [
                'container' => 'container-places-activity-pie',
                'data' => $this->assoc2NameYArray($placesOfActivity),
                'exhibitionId' => $exhibition->getId(),
            ]);
        }

        // exhibiting cities of artists
        $template = $this->get('twig')->loadTemplate('Statistics/exhibition-city-stats.html.twig');
        $charts[] = $template->renderBlock('chart', [
            'container' => 'container-exhibiting-pie',
            'data' => $this->assoc2NameYArray($this->artistExhibitingInCityStats($this->getArtistsExhibitingAtExhibitionByExhibitionId($exhibition->getId()))),
            'exhibitionId' => $exhibition->getId(),
        ]);

        // types of work
        $stats = $this->itemExhibitionTypeDistribution($em, $exhibition->getId());
        $data = [];
        foreach ($stats['types'] as $type => $count) {
            $percentage = 100.0 * $count / $stats['total'];
            $dataEntry = [
                'name' => $type,
                'y' => (int)$count,
            ];
            if ($percentage < 5) {
                $dataEntry['dataLabels'] = [ 'enabled' => false ];
            }
            $data[] = $dataEntry;
        }

        $template = $this->get('twig')->loadTemplate('Statistics/itemexhibition-type.html.twig');
        $charts[] = $template->renderBlock('chart', [
            'container' => 'container-type',
            'total' => $stats['total'],
            'data' => json_encode($data),
            'exhibitionId' => $exhibition->getId()
        ]);

        // cat. entries by nationality of participating artists
        $stats = $this->itemExhibitionNationalityDistribution($em, $exhibition->getId());
        $data = [];
        $key = 'ItemExhibition'; // alternative is 'Artists'
        foreach ($stats['nationalities'] as $nationality => $counts) {
            $count = $counts['count' . $key];
            $percentage = 100.0 * $count / $stats['total' . $key];
            $dataEntry = [
                'name' => $counts['label'],
                'y' => (int)$count,
                'artists' => $counts['countArtists'],
                'itemExhibition' => $counts['countItemExhibition'],
            ];
            if ($percentage < 5) {
                $dataEntry['dataLabels'] = [ 'enabled' => false ];
            }
            $data[] = $dataEntry;
        }

        $template = $this->get('twig')->loadTemplate('Statistics/itemexhibition-nationality.html.twig');
        $charts[] = $template->renderBlock('chart', [
            'container' => 'container-nationality',
            'totalArtists' => $stats['totalArtists'],
            'totalItemExhibition' => $stats['totalItemExhibition'],
            'data' => json_encode($data),
        ]);

        return [
            join("\n", $charts),
            $placesOfActivityAvailable,
        ];
    }

    private function exhibitionAgePersonIds($em, $age, $exhibitionId = null)
    {
        $dbconn = $em->getConnection();

        $where = !is_null($exhibitionId) && intval($exhibitionId) > 0
            ? sprintf('WHERE Exhibition.id=%d', intval($exhibitionId))
            : '';

        $querystr = <<<EOT
SELECT id,
IF (EB.deathdate IS NOT NULL AND YEAR(EB.deathdate) < YEAR(EB.startdate), 'deceased', 'living') AS state
FROM
(SELECT DISTINCT Person.id AS id, Exhibition.startdate AS startdate, Exhibition.id AS id_exhibition, Person.id AS id_person, Person.birthdate AS birthdate, Person.deathdate AS deathdate
FROM Exhibition
INNER JOIN ItemExhibition ON ItemExhibition.id_exhibition=Exhibition.id
INNER JOIN Person ON ItemExhibition.id_person=Person.id AND Person.birthdate IS NOT NULL
$where
GROUP BY Exhibition.id, Person.id) AS EB
WHERE YEAR(EB.startdate) - YEAR(EB.birthdate) = :age
EOT;

        $stmt = $stmt = $em->getConnection()->prepare($querystr);
        $stmt->bindValue(':age', $age, \PDO::PARAM_INT);
        $stmt->execute();
        $ids = [];
        while ($row = $stmt->fetch()) {
            if (!array_key_exists($row['state'], $ids)) {
                $ids[$row['state']] = [];
            }

            $ids[$row['state']][] = $row['id'];
        }

        return $ids;
    }

    /**
     * @Route("/exhibition/{id}/stats/info", requirements={"id" = "\d*"}, name="exhibition-stats-info")
     */
    public function statsInfoAction(Request $request, $id)
    {
        $chart = $request->get('chart');
        switch ($chart) {
            case 'container-age':
                $personIds = $this->exhibitionAgePersonIds($em = $this->getDoctrine()->getManager(), $request->get('point'), $id);
                foreach ($personIds as $type => $ids) {
                    $personIds[$type] = $this->hydratePersons($ids);
                }

                return $this->render('Exhibition/stats-info-age.html.twig', [
                    'age' => $request->get('point'),
                    'personsByType' => $personIds,
                ]);
                break;

            /*
            case 'container-countries':
                $personIds = $this->exhibitionNationalityPersonIds($em = $this->getDoctrine()->getManager(), $request->get('point'), $id);

                foreach ($personIds as $type => $ids) {
                    $personIds[$type] = $this->hydratePersons($ids);
                }

                return $this->render('Shared/modal.html.twig', [
                    'heading' => 'Artists exhibiting in ' . $request->get('point'),
                    'elements' => $personIds['artists'],
                    'type' => 'person'
                ]);
                break;

            */

            default:
                die('Currently not handling chart: ' . $chart);
        }
    }

    private function itemExhibitionTypeDistribution($em, $exhibitionId)
    {
        $dbconn = $em->getConnection();


        $where = sprintf(' WHERE ItemExhibition.id_exhibition=%d',
                         intval($exhibitionId));

        $querystr = "SELECT TypeTerm.id, TypeTerm.name, TypeTerm.aat, COUNT(*) AS how_many"
                  . " FROM ItemExhibition"
                  . " LEFT OUTER JOIN Term TypeTerm ON ItemExhibition.type=TypeTerm.id"
                  . $where
                  . " GROUP BY TypeTerm.id, TypeTerm.name"
                  . " ORDER BY TypeTerm.name";

        $stmt = $dbconn->query($querystr);
        $total = 0;
        $stats = [];
        while ($row = $stmt->fetch()) {
            $label = preg_match('/unknown/', $row['name'])
                ? 'unknown'
                : $row['name'];
            $stats[$label] = $row['how_many'];
            $total += $row['how_many'];
        }

        return [
            'total' => $total,
            'types' => $stats,
        ];
    }

    private function exhibitionAgeDistribution($em, $exhibitionId)
    {
        $dbconn = $em->getConnection();

        $andConditions = [ sprintf('Exhibition.id=%d', intval($exhibitionId)) ];

        $where = ' WHERE ' . implode(' AND ', $andConditions);

        $querystr = <<<EOT
SELECT COUNT(*) AS how_many,
YEAR(EB.startdate) - YEAR(EB.birthdate) AS age,
IF (EB.deathdate IS NOT NULL AND YEAR(EB.deathdate) < YEAR(EB.startdate), 'deceased', 'living') AS state
FROM
(SELECT DISTINCT Exhibition.startdate AS startdate, Exhibition.id AS id_exhibition, Person.id AS id_person, Person.birthdate AS birthdate, Person.deathdate AS deathdate
FROM Exhibition
INNER JOIN ItemExhibition ON ItemExhibition.id_exhibition=Exhibition.id
INNER JOIN Person ON ItemExhibition.id_person=Person.id AND Person.status <> -1 AND Person.birthdate IS NOT NULL
$where
GROUP BY Exhibition.id, Person.id) AS EB
GROUP BY age, state
ORDER BY age, state, how_many
EOT;

        $min_age = $max_age = 0;

        $stmt = $dbconn->query($querystr);
        $ageCount = [];
        while ($row = $stmt->fetch()) {
            if (0 == $min_age) {
                $min_age = (int)$row['age'];
            }
            $max_age = $age = (int)$row['age'];
            if (!array_key_exists($age, $ageCount)) {
                $ageCount[$age] = [];
            }
            $ageCount[$age][$row['state']] = $row['how_many'];
        }

        return [
            'min_age' => $min_age,
            'max_age' => $max_age,
            'age_count' => $ageCount,
        ];
    }

    private function itemExhibitionNationalityDistribution($em, $exhibitionId = null)
    {
        $qb = $em->createQueryBuilder();

        $qb->select([
                'P.nationality',
                'COUNT(DISTINCT P.id) AS numArtists',
                'COUNT(DISTINCT IE.id) AS numEntries'
            ])
            ->from('AppBundle:ItemExhibition', 'IE')
            ->innerJoin('IE.person', 'P')
            ->where('IE.title IS NOT NULL OR IE.item IS NULL')
            ->groupBy('P.nationality')
            ;

        if (!is_null($exhibitionId)) {
            $qb->innerJoin('AppBundle:Exhibition', 'E',
                       \Doctrine\ORM\Query\Expr\Join::WITH,
                       'IE.exhibition = E AND E.id = :exhibitionId')
                ->setParameter('exhibitionId', $exhibitionId);
        }

        $statsByNationality = [];
        $totalArtists = 0;
        $totalItemExhibition = 0;
        $result = $qb->getQuery()->getResult();
        foreach ($result as $row) {
            $nationality = empty($row['nationality'])
                ? 'XX' : $row['nationality'];

            if (array_key_exists($nationality, self::$countryMap)) {
                $nationality = self::$countryMap[$nationality];
            }

            if (array_key_exists($nationality, $statsByNationality)) {
                // we merge different countries together
                $statsByNationality[$nationality]['countArtists'] += $row['numArtists'];
                $statsByNationality[$nationality]['countItemExhibition'] += $row['numEntries'];
            }
            else {
                $statsByNationality[$nationality] = [
                    'label' => 'XX' == $nationality
                        ? '[unknown]' : $this->expandCountryCode($nationality),
                    'countArtists' => $row['numArtists'],
                    'countItemExhibition' => $row['numEntries'],
                ];
            }


            $totalArtists += $row['numArtists'];
            $totalItemExhibition += $row['numEntries'];
        }

        uasort($statsByNationality,
               function ($a, $b) {
                    if ($a['countItemExhibition'] == $b['countItemExhibition']) return 0;
                    return $a['countItemExhibition'] < $b['countItemExhibition'] ? 1 : -1;
                });

        return [
            'totalArtists' => $totalArtists,
            'totalItemExhibition' => $totalItemExhibition,
            'nationalities' => $statsByNationality,
            'exhibitionId' => $exhibitionId
        ];
    }

    /**
     *
     * ARTIST FUNCTIONS
     *
     */
    private function artistExhibitingInCityStats($artists)
    {
        $allExhibitedCities = $this->getCitiesOfExhibitionsOfArtistByArtistArray($artists);

        $citiesExhibited = array_count_values(array_filter($allExhibitedCities));

        arsort($citiesExhibited);

        return $citiesExhibited;
    }

    /**
     * Very inefficient lookup
     * TODO: Switch to proper SQL
     */
    private function getCitiesOfExhibitionsOfArtistByArtistArray($artists)
    {
        $qb = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();

        $qb->select([
                'P'
            ])
            ->from('AppBundle:Person', 'P')
            ->where('P.id IN (:artists) AND P.status <> -1')
            ->setParameter('artists', $artists )
            ;

        $exhibitionCities = [];

        foreach ($qb->getQuery()->getResult() as $person) {
            foreach ($person->getExhibitions() as $exhibition) {
                $location = $exhibition->getLocation();
                // needs to be checked since location could be empty
                if (!is_null($location)) {
                    $exhibitionCities[] = $location->getPlaceLabel();
                }
            }
        }

        return $exhibitionCities;
    }

    private function getArtistsExhibitingAtExhibitionByExhibitionId($exhibitionId)
    {
        $qb = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();

        $qb->select([
                'P.id AS id',
                'P.nationality AS nationality'
            ])
            ->from('AppBundle:Exhibition', 'E')
            ->leftJoin('AppBundle:ItemExhibition', 'IE',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'IE.exhibition = E AND (IE.title IS NOT NULL OR IE.item IS NULL)')
            ->leftJoin('AppBundle:Person', 'P',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'P.id = IE.person AND P.id IS NOT NULL')
            // ->leftJoin('IE.person', 'P')
            ->where('E.id = :exhibitionId AND P.id IS NOT NULL' )
            ->groupBy('P.id')
            ->setParameter('exhibitionId', $exhibitionId)
            ;

        return $qb->getQuery()->getResult();
    }

    private function artistsNationalityByExhibitionStatistics($exhibitionId)
    {
        $qb = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();

        $qb->select([
                'COUNT(DISTINCT P.id) AS howMany',
                'P.nationality AS nationality'
            ])
            ->from('AppBundle:Exhibition', 'E')
            ->innerJoin('AppBundle:ItemExhibition', 'IE',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'IE.exhibition = E AND (IE.title IS NOT NULL OR IE.item IS NULL)')
            ->innerJoin('AppBundle:Person', 'P',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'P.id = IE.person AND P.status <> -1')
            ->where('E.id = :exhibitionId' )
            ->setParameter('exhibitionId', $exhibitionId)
            ->groupBy('P.nationality')
            ;

        $nationalityCounts = [];
        foreach ($qb->getQuery()->getResult() as $row) {
            $country = $this->expandCountryCode($row['nationality']);
            $nationalityCounts[$country] = $row['howMany'];
        }

        arsort($nationalityCounts);

        return $this->assoc2NameYArray($nationalityCounts);
    }

    private function artistsByGenderExhibitionStatistics($exhibitionId)
    {
        $qb = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();

        $qb->select([
                'P.id AS id',
                'P.gender as gender'
            ])
            ->from('AppBundle:Exhibition', 'E')
            ->innerJoin('AppBundle:ItemExhibition', 'IE',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'IE.exhibition = E AND (IE.title IS NOT NULL OR IE.item IS NULL)')
            ->innerJoin('AppBundle:Person', 'P',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'P.id = IE.person AND P.status <> -1')
            ->where('E.id = :exhibitionId' )
            ->groupBy('P.id')
            ->setParameter('exhibitionId', $exhibitionId)
            ;

        $allArtists = $qb->getQuery()->getResult();

        $allArtists = array_unique($allArtists, SORT_REGULAR );
        $gendersOnly = array_column($allArtists, 'gender');
        $gendersOnly = array_replace($gendersOnly, array_fill_keys(array_keys($gendersOnly, null), '')); // remove null values if existing
        $genderStats = array_count_values($gendersOnly);

        // creating better named keys
        foreach ([ 'M' => 'male', 'F' => 'female', '' => '[unknown]' ] as $src => $target) {
            if (array_key_exists($src, $genderStats)) {
                $genderStats[$target] = $genderStats[$src];
                unset($genderStats[$src]);
            }
        }

        return $genderStats;
    }
}
