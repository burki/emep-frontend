<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use Pagerfanta\Pagerfanta;

use AppBundle\Utils\CsvResponse;
use AppBundle\Utils\SearchListBuilder;
use AppBundle\Utils\SearchListPagination;
use AppBundle\Utils\SearchListAdapter;

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
                'P.countryCode',
            ])
            ->distinct()
            ->from('AppBundle:Exhibition', 'E')
            ->leftJoin('E.location', 'L')
            ->leftJoin('L.place', 'P')
            ->where(\AppBundle\Utils\SearchListBuilder::exhibitionVisibleCondition('E'))
            ->andWhere('P.countryCode IS NOT NULL')
            ;

        return $this->buildActiveCountries($qb);
    }

    protected function buildSaveSearchParams(Request $request, UrlGeneratorInterface $urlGenerator)
    {
        $route = str_replace('-save', '-index', $request->get('_route'));

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

        $citeProc = $this->instantiateCiteProc($request->getLocale());
        if ($exhibition->hasInfo()) {
            // expand the publications
            $exhibition->buildInfoFull($this->getDoctrine()->getManager(), $citeProc);
        }

        $result = $this->findSimilar($exhibition);

        $exhibitionIds = array_keys($result);
        $exhibitionsByIds = [];
        if (!empty($exhibitionIds)) {
            $exhibitions = $this->hydrateExhibitions($exhibitionIds);
            $exhibitionsByIds = array_combine(
                array_map(function ($exhibition) { return $exhibition->getId(); }, $exhibitions),
                $exhibitions
            );
        }

        $csvResult = [];
        foreach ($result as $id => $value) {
            if ($value['count'] > 0) {
                $innerArray = [];

                $exhibition = $exhibitionsByIds[$id];

                array_push($innerArray,
                    $exhibition->getStartdate(), $exhibition->getEnddate(), $exhibition->getDisplaydate(),
                    $exhibition->getTitle(),
                    $exhibition->getLocation()->getPlaceLabel(),
                    $exhibition->getLocation()->getName(),
                    $value['count']
                );

                array_push($csvResult, $innerArray);
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

        $artists = [];

        foreach ($catalogueEntries as $entry) {
            $currPerson = $entry->person;
            if (!in_array($currPerson, $artists)) {
                array_push($artists, $currPerson);
            }
        }

        $csvResult = [];
        foreach ($artists as $person) {
            array_push($csvResult, [ $person->getFullname(true), $person->getBirthDate(), $person->getDeathDate() ]);
        }

        return new CsvResponse($csvResult, 200, explode( ', ', 'Name, Birth Date, Death Date'));
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


    /**
     * @Route("/exhibition/{id}", requirements={"id" = "\d+"}, name="exhibition")
     */
    public function detailAction(Request $request, $id, $itemexhibitionId = null)
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

        $locale = $request->getLocale();
        if (in_array($request->get('_route'), [ 'exhibition-jsonld' ])) {
            return new JsonLdResponse($person->jsonLdSerialize($locale));
        }

        $citeProc = $this->instantiateCiteProc($request->getLocale());
        if ($exhibition->hasInfo()) {
            // expand the publications
            $exhibition->buildInfoFull($this->getDoctrine()->getManager(), $citeProc);
        }

        $catalogueEntries = $this->findCatalogueEntries($exhibition, $request->get('sort'));
        $artists = []; $catalogueEntriesByPersonCount = [];
        foreach ($catalogueEntries as $entry) {
            $person = $entry->person;
            if (!is_null($person)) {
                $personId = $person->getId();
                if (!array_key_exists($personId, $catalogueEntriesByPersonCount)) {
                    $catalogueEntriesByPersonCount[$personId] = 0;
                    array_push($artists, $person);
                }
                ++$catalogueEntriesByPersonCount[$personId];
            }
        }

        $artists = array_unique($artists, SORT_REGULAR); // remove multiple artists

        $artistsCountries = [];
        $genderSplit = ['M' => 0, 'F' => 0]; // first male, second female
        foreach ($artists as $artist) {
            if (is_null($artist)) {
                continue;
            }

            $currNationality = $artist->getNationality();
            if ($artist->getGender() === 'M') {
                $genderSplit['M'] = $genderSplit['M'] + 1;
            }
            else if ($artist->getGender() === 'F') {
                $genderSplit['F'] = $genderSplit['F'] + 1;
            }

            array_push($artistsCountries, $currNationality);
        }

        $artistsCountries = array_unique($artistsCountries); // remove multiple countries

        $artistsByGenderExhibitionStatistic = $this->assoc2NameYArray($this->artistsByGenderExhibitionStatistics($exhibition->getId()));

        $artistsByNationalityExhibiting = $this->artistsNationalityByExhibitionStatistics($exhibition->getId());

        $catalogueStatus = SearchListBuilder::$STATUS_LABELS;

        // $artistExhibitingInCityStats = $this->assoc2NameYArray($this->artistExhibitingInCityStats($artists));

        $catalogues = $exhibition->findBibitem($em = $this->getDoctrine()->getManager(), 1);
        // expand the notes
        foreach ($catalogues as $catalogue) {
            if ($catalogue->hasInfo()) {
                $catalogue->buildInfoFull($em, $citeProc);
            }
        }

        // for traveling, find related
        $relatedExhibitions = $exhibition->isTraveling()
            ? $this->lookupExhibitionGroup($em, $exhibition)
            : [];

        return $this->render('Exhibition/detail.html.twig', [
            'artists' => $artists,
            'pageTitle' => $exhibition->title, // TODO: dates in brackets
            'exhibition' => $exhibition,
            'catalogue' => $catalogues,
            'citeProc' => $citeProc,
            'catalogueEntries' => $catalogueEntries,
            'catalogueEntriesByPersonCount' => $catalogueEntriesByPersonCount,
            'showWorks' => false, // !empty($_SESSION['user']),
            'related' => $relatedExhibitions,
            'similar' => $this->findSimilar($exhibition),
            'currentPageId' => $id,
            'catalogueStatus' => $catalogueStatus,
            'genderSplit' => $genderSplit,
            'genderStatsStatisticsFormat' => $artistsByGenderExhibitionStatistic,
            'nationalitiesStats' => $artistsByNationalityExhibiting,
            'artistCountries' => $artistsCountries,
            // 'artistExhibitingInCityStats' => $artistExhibitingInCityStats,
            'pageMeta' => [
                /*
                'jsonLd' => $exhibition->jsonLdSerialize($locale),
                'og' => $this->buildOg($exhibition, $routeName, $routeParams),
                'twitter' => $this->buildTwitter($exhibition, $routeName, $routeParams),
                */
            ],
        ]);
    }

    /**
     * @Route("/exhibition/{id}/{itemexhibitionId}", requirements={"id" = "\d+", "itemexhibitionId" = "\d+"}, name="itemexhibition")
     */
    public function detailItemExhibitionAction(Request $request, $id, $itemexhibitionId)
    {
        return $this->detailAction($request, $id, $itemexhibitionId);
    }

    /**
     * @Route("/exhibition/{id}/stats/info", requirements={"id" = "\d*"}, name="exhibition-stats-info")
     */
    public function statsInfoAction(Request $request, $id)
    {
        $chart = $request->get('chart');
        switch ($chart) {
            case 'container-age':
                $personIds = StatisticsController::exhibitionAgePersonIds($em = $this->getDoctrine()->getManager(), $request->get('point'), $id);
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
                $personIds = StatisticsController::exhibitionNationalityPersonIds($em = $this->getDoctrine()->getManager(), $request->get('point'), $id);

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

    /**
     * @Route("/exhibition/{id}/stats.embed", requirements={"id" = "\d+"}, name="exhibition-stats-partial")
     * @Route("/exhibition/{id}/stats", requirements={"id" = "\d+"}, name="exhibition-stats")
     */
    public function statsAction(Request $request, $id = null)
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

        $charts = [];

        // genderStatsStatisticsFormat
        $artistsByGenderExhibitionStatistic = $this->assoc2NameYArray($this->artistsByGenderExhibitionStatistics($exhibition->getId()));

        $template = $this->get('twig')->loadTemplate('Statistics/exhibition-gender-stats.html.twig');
        $charts[] = $template->renderBlock('chart', [
            'container' => 'container-artist-by-gender',
            'data' => $artistsByGenderExhibitionStatistic,
            'exhibitionId' => $exhibition->getId(),
        ]);

        // display the artists by birth-year
        $stats = StatisticsController::exhibitionAgeDistribution($em = $this->getDoctrine()->getManager(), $exhibition->getId());
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
            'exhibition_id' => $id,
        ]);

        // artists' nationality
        $artistsByNationalityExhibiting = $this->artistsNationalityByExhibitionStatistics($exhibition->getId());
        $template = $this->get('twig')->loadTemplate('Statistics/exhibition-nationality-stats.html.twig');
        $charts[] = $template->renderBlock('chart', [
            'container' => 'container-nationalities-pie',
            'data' => $artistsByNationalityExhibiting,
            'exhibitionId' => $exhibition->getId(),
        ]);

        // exhibiting cities of artists
        $template = $this->get('twig')->loadTemplate('Statistics/exhibition-city-stats.html.twig');
        $artistExhibitingInCityStats = $this->assoc2NameYArray($this->artistExhibitingInCityStats($this->getArtistsExhibitingAtExhibitionByExhId($exhibition->getId())));
        $charts[] = $template->renderBlock('chart', [
            'container' => 'container-exhibiting-pie',
            'data' => $artistExhibitingInCityStats,
            'exhibitionId' => $exhibition->getId(),
        ]);

        // types of work
        $stats = StatisticsController::itemExhibitionTypeDistribution($em, $exhibition->getId());
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
        $stats = StatisticsController::itemExhibitionNationalityDistribution($em, $exhibition->getId());
        $data = [];
        $key = 'ItemExhibition'; // alternative is 'Artists'
        foreach ($stats['nationalities'] as $nationality => $counts) {
            $count = $counts['count' . $key];
            $percentage = 100.0 * $count / $stats['total' . $key];
            $dataEntry = [
                'name' => 'XX' == $nationality ? '[unknown]' : $nationality,
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

        // display the static content
        return $this->render('Exhibition/stats.html.twig', [
            'chart' => implode("\n", $charts),
            'exhibitionId' => $exhibition->getId()
        ]);
    }

    public function statsActionDetail($id)
    {
        if (!empty($id)) {
            $repo = $this->getDoctrine()
                ->getRepository('AppBundle:Exhibition');
            $routeParams = [ 'id' => $id ];
            $exhibition = $repo->findOneById($id);
        }

        if (!isset($exhibition) || !$exhibition->checkStatus(-1)) {
            return $this->redirectToRoute('exhibition-index');
        }

        // display the artists by birth-year
        $stats = StatisticsController::exhibitionAgeDistribution($em = $this->getDoctrine()->getManager(), $exhibition->getId());
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
        $charts = [
            $template->renderBlock('chart', [
                'container' => 'container-age',
                'categories' => json_encode($categories),
                'age_at_exhibition_living' => json_encode(array_values($total['age_living'])),
                'age_at_exhibition_deceased' => json_encode(array_values($total['age_deceased'])),
                'exhibition_id' => $id,
            ]),
        ];

        // nationalities of participating artists
        $stats = StatisticsController::itemExhibitionNationalityDistribution($em, $exhibition->getId());
        $data = [];
        $key = 'ItemExhibition'; // alternative is 'Artists'
        foreach ($stats['nationalities'] as $nationality => $counts) {
            $count = $counts['count' . $key];
            $percentage = 100.0 * $count / $stats['total' . $key];
            $dataEntry = [
                'name' => $nationality,
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
            'exhibitionId' => $exhibition->getId()
        ]);

        // type of work
        $stats = StatisticsController::itemExhibitionTypeDistribution($em, $exhibition->getId());
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
            'exhibitionId' => $exhibition->getId(),
        ]);

        // display the static content
        return $this->render('Exhibition/stats-detail.html.twig', [
            'chart' => implode("\n", $charts),
            'exhibitionId' => $exhibition->getId(),
        ]);
    }

    /**
     *
     * ARTIST FUNCTIONS
     *
     */
    public function artistExhibitingInCityStats($artists)
    {
        $allExhibitedCities = $this->getCitiesOfExhibitionsOfArtistByArtistArray($artists);

        $citiesExhibited = array_count_values(array_filter($allExhibitedCities));

        return $citiesExhibited;
    }

    public function getCitiesOfExhibitionsOfArtistByArtistArray($artists)
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

        $persons = $qb->getQuery()->getResult();

        $exhibitionCities = [];

        foreach ($persons as $person) {
            $exhibitions = $person->getExhibitions();

            foreach ($exhibitions as $exhibition) {
                if ($exhibition) {
                    $currLocation = $exhibition->getLocation();
                    $currPlaceLabel = "";

                    // needs to be checked since location could be empty
                    if ($currLocation) {
                        $currPlaceLabel = $currLocation->getPlaceLabel();
                    }


                    if ($currPlaceLabel) {
                        array_push($exhibitionCities,  (string)$currPlaceLabel);
                    }
                }
            }
        }

        return $exhibitionCities;
    }

    public function getCitiesOfExhibitionsOfArtist($artistId)
    {
        $qb = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();

        $qb->select([
                'P'
            ])
            ->from('AppBundle:Person', 'P')
            ->where('P.id = :artist AND P.status <> -1')
            ->setParameter('artist', $artistId );

        $person = $qb->getQuery()->getResult();

        $exhibitions = $person[0]->getExhibitions();

        $exhibitionCities = [];

        foreach ($exhibitions as $exhibition) {
            $currPlaceLabel = $exhibition->getLocation()->getPlaceLabel();

            if ($currPlaceLabel) {
                array_push($exhibitionCities,  (string)$currPlaceLabel);
            }
        }

        return $exhibitionCities;
    }

    public function getArtistsExhibitingAtExhibitionByExhId($exhId)
    {
        $qb = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();

        $qb->select([
                'P.id AS id',
                'P.nationality as nationality'
            ])
            ->from('AppBundle:Exhibition', 'E')
            ->leftJoin('AppBundle:ItemExhibition', 'IE',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'IE.exhibition = E AND (IE.title IS NOT NULL OR IE.item IS NULL)')
            ->leftJoin('AppBundle:Person', 'P',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'P.id = IE.person AND P.id IS NOT NULL')
            // ->leftJoin('IE.person', 'P')
            ->where('E.id = :exhId AND P.id IS NOT NULL' )
            ->groupBy('P.id')
            ->setParameter('exhId', $exhId)
            ;

        $allArtists = $qb->getQuery()->getResult();

        $allArtists = array_unique($allArtists, SORT_REGULAR);

        return $allArtists;
    }

    public function artistsNationalityByExhibitionStatistics($exhId)
    {
        $qb = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();

        $qb->select([
                'P.id AS id',
                'P.nationality as nationality'
            ])
            ->from('AppBundle:Exhibition', 'E')
            ->leftJoin('AppBundle:ItemExhibition', 'IE',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'IE.exhibition = E AND (IE.title IS NOT NULL OR IE.item IS NULL)')
            ->leftJoin('AppBundle:Person', 'P',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'P.id = IE.person AND P.id IS NOT NULL')
            ->where('E.id = :exhId AND P.id IS NOT NULL AND P.status <> -1' )
            ->groupBy('P.id')
            ->setParameter('exhId', $exhId)
            ;

        $allArtists = $qb->getQuery()->getResult();

        $allArtists = array_unique($allArtists, SORT_REGULAR);

        $countriesOnly = array_column($allArtists, 'nationality');

        $countriesOnly = array_replace($countriesOnly, array_fill_keys(array_keys($countriesOnly, null), '[unknown]')); // remove null values if existing

        $countriesStats = array_count_values($countriesOnly);

        return $this->assoc2NameYArray($countriesStats);
    }

    protected function artistsByGenderExhibitionStatistics($exhId)
    {
        $qb = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();

        $qb->select([
                'P.id AS id',
                'P.gender as gender'
            ])
            ->from('AppBundle:Exhibition', 'E')
            ->leftJoin('AppBundle:ItemExhibition', 'IE',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'IE.exhibition = E AND (IE.title IS NOT NULL OR IE.item IS NULL)')
            ->leftJoin('AppBundle:Person', 'P',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'P.id = IE.person AND P.id IS NOT NULL')
            ->where('E.id = :exhId AND P.id IS NOT NULL' )
            ->groupBy('P.id')
            ->setParameter('exhId', $exhId)
            ;

        $allArtists = $qb->getQuery()->getResult();

        $allArtists = array_unique($allArtists, SORT_REGULAR );
        $gendersOnly = array_column($allArtists, 'gender');
        $gendersOnly = array_replace($gendersOnly,array_fill_keys(array_keys($gendersOnly, null), '')); // remove null values if existing
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
