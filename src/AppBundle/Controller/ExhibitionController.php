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
            ->where('E.status <> -1 AND P.countryCode IS NOT NULL')
            ;

        return $this->buildActiveCountries($qb);
    }

    /**
     * @Route("/exhibition/save", name="exhibition-save")
     */
    public function saveSearchActionExhibition(Request $request,
                                     UrlGeneratorInterface $urlGenerator,
                                     UserInterface $user)
    {

        $parametersAsString = $request->get('entity');
        $parametersAsString = str_replace("/exhibition?", '', $parametersAsString);


        parse_str($parametersAsString, $parameters);


        $form = $this->createForm(\AppBundle\Form\Type\SaveSearchType::class);

        //$form->get

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $userAction = new \AppBundle\Entity\UserAction();

            $userAction->setUser($user);
            $userAction->setRoute($route = 'exhibition');
            $userAction->setRouteParams($parameters);

            $userAction->setName($data['name']);

            $em = $this->getDoctrine()
                ->getManager();

            $em->persist($userAction);
            $em->flush();

            return $this->redirectToRoute($route, $parameters);
        }

        return $this->render('Search/save.html.twig', [
            'pageTitle' => $this->get('translator')->trans('Save your query'),
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/exhibition", name="exhibition-index")
     */
    public function indexAction(Request $request,
                                UrlGeneratorInterface $urlGenerator,
                                UserInterface $user = null)
    {
        // redirect to saved query
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
                        'route' => 'exhibition',
                    ]);

                if (!is_null($userAction)) {
                    return $this->redirectToRoute($userAction->getRoute(),
                                                  $userAction->getRouteParams());
                }
            }
        }


        $route = $request->get('_route');

        $leftYear = 1905;
        $rightYear = 1915;

        $organizerTypes = $this->buildOrganizerTypes();
        $form = $this->form = $this->get('form.factory')->create(\AppBundle\Filter\ExhibitionFilterType::class, [
            'country_choices' => array_flip($this->buildCountries()),
            'organizer_type_choices' => array_combine($organizerTypes, $organizerTypes),
            'ids' => range(0, 9999),
            'years' => [$leftYear, $rightYear]
        ]);


        // build up stuff to pass along
        $stringQuery = $form->get('search')->getData();
        $organizerType = $form->get('exhibition')->get('organizer_type')->getData();
        $ids = $form->get('exhibition')->get('id')->getData();
        $countries = $form->get('location')->get('geoname')->getData();
        $artistGender = $form->get('gender')->getData();
        $artistNationalities = $form->get('nationality')->getData();
        $exhibitionStartDate = $form->get('startdate')->getData();


        $currIds = [];
        /*
        foreach ($result as $exh){
            array_push($currIds, $exh[0]->getId());
        }
        */

        // refactor this --- only reason is because to many get variable
        if (count($currIds) > 500){
            $currIds = [];
        }

        // print_r(($result[0]));

        $requestURI = $request->getRequestUri();

        $listBuilder = $this->instantiateListBuilder($request, $urlGenerator, false, 'Exhibition');

        $listPagination = new SearchListPagination($listBuilder);

        $page = $request->get('page', 1);
        $listPage = $listPagination->get($this->pageSize, ($page - 1) * $this->pageSize);

        $adapter = new SearchListAdapter($listPage);
        $pager = new Pagerfanta($adapter);
        $pager->setMaxPerPage($listPage['limit']);
        $pager->setCurrentPage(intval($listPage['offset'] / $listPage['limit']) + 1);

        return $this->render('Exhibition/index.html.twig', [
            // 'realIds' => $currIds,
            'pageTitle' => $this->get('translator')->trans('Exhibitions'),
            // 'pagination' => $pagination,
            'pager' => $pager,

            'listBuilder' => $listBuilder,
            'form' => $this->form->createView(),

            //'formIds'=> $formIds->createView(),
            //'realData' => $result,
            'countryArray' => $this->buildCountries(),
            'organizerTypesArray' => $organizerTypes,
            'countries' => $countries,
            'ids' => $ids,
            'organizerType' => $organizerType,
            'stringPart' => $stringQuery,
            'minStartYear' => $leftYear,
            'maxStartYear' => $rightYear,
            'artistGender' => $artistGender,
            'artistNationalities' => $artistNationalities,
            'exhibitionStartDate' => $exhibitionStartDate,
            'requestURI' => $requestURI,
            'searches' => $this->lookupSearches($user, 'exhibition')
        ]);
    }

    /**
     * @Route("/exhibition/map", name="exhibition-index-map")
     */
    public function indexMapAction(Request $request,
                                   UrlGeneratorInterface $urlGenerator,
                                   UserInterface $user = null)
    {
        $leftYear = 1905;
        $rightYear = 1915;
        $organizerTypes = $this->buildOrganizerTypes();
        $form = $this->form = $this->get('form.factory')->create(\AppBundle\Filter\ExhibitionFilterType::class, [
            'country_choices' => array_flip($this->buildCountries()),
            'organizer_type_choices' => array_combine($organizerTypes, $organizerTypes),
            'ids' => range(0, 9999),
            'years' => [$leftYear, $rightYear]
        ]);

        $listBuilder = $this->instantiateListBuilder($request, $urlGenerator, 'extended', $entity = 'Exhibition');
        $query = $listBuilder->query();
        // echo($query->getSQL());

        $stmt = $query->execute();

        $renderParams = $this->processMapEntries($stmt, $entity);

        return $this->render('Map/place-map-index.html.twig', $renderParams + [
            'filter' => null,
            'bounds' => [
                [ 60, -120 ],
                [ -15, 120 ],
            ],
            'markerStyle' => 'exhibition-by-place' == 'default',
            'persons' => [], // $persons,
        ]);
    }

    /**
     * @Route("/exhibition/stats", name="exhibition-index-stats")
     */
    public function indexStatsAction(Request $request,
                                     UrlGeneratorInterface $urlGenerator,
                                     UserInterface $user = null)
    {
        $leftYear = 1905;
        $rightYear = 1915;
        $organizerTypes = $this->buildOrganizerTypes();
        $form = $this->form = $this->get('form.factory')->create(\AppBundle\Filter\ExhibitionFilterType::class, [
            'country_choices' => array_flip($this->buildCountries()),
            'organizer_type_choices' => array_combine($organizerTypes, $organizerTypes),
            'ids' => range(0, 9999),
            'years' => [$leftYear, $rightYear]
        ]);

        $listBuilder = $this->instantiateListBuilder($request, $urlGenerator, false, $entity = 'Exhibition');

        $charts = $this->buildExhibitionCharts($request, $urlGenerator, $listBuilder);

        return new \Symfony\Component\HttpFoundation\Response(implode("\n", $charts));
    }

    // TODO MOVE TO SHARED
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
            ->where("UA.route = 'exhibition'")
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
            ->where('E.status <> -1')
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
                  . " INNER JOIN Exhibition ON ItemExhibition.id_exhibition = Exhibition.id AND Exhibition.status <> -1"
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
                      . " LEFT OUTER JOIN Exhibition ON ItemExhibition.id_exhibition=Exhibition.id AND Exhibition.status <> -1"
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
            ->andWhere('IE.title IS NOT NULL')
            ;

        $results = $qb->getQuery()
            ->setParameter('exhibition', $exhibition)
            ->getResult();

        // sort, either catalogueId or person first
        $sortPersonFirst = 'person' == $sort || $exhibition->isSortedByPerson();

        usort($results, function ($a, $b) use ($sortPersonFirst) {
            $sortPartsA = [ $a->catalogueSection . ' ' . $a->catalogueId, $a->getPerson()->getFullname() ];
            $sortPartsB = [ $b->catalogueSection . ' ' . $b->catalogueId, $b->getPerson()->getFullname() ];
            if ($sortPersonFirst) {
                $sortPartsA = array_reverse($sortPartsA);
                $sortPartsB = array_reverse($sortPartsB);
            }

            return strnatcmp(implode('', $sortPartsA), implode('', $sortPartsB));
        });

        return $results;
    }

    /**
     * @Route("/exhibition/catalogue/csv/{id}", requirements={"id" = "\d+"}, name="exhibition-catalogue-csv")
     */
    public function detailActionCatalogueCSV(Request $request, $id = null)
    {
        $routeName = $request->get('_route'); $routeParams = [];

        $repo = $this->getDoctrine()
            ->getRepository('AppBundle:Exhibition');

        if (!empty($id)) {
            $routeParams = [ 'id' => $id ];
            $exhibition = $repo->findOneById($id);
        }

        if (!isset($exhibition) || $exhibition->getStatus() == -1) {
            return $this->redirectToRoute('exhibition-index');
        }

        $citeProc = $this->instantiateCiteProc($request->getLocale());
        if ($exhibition->hasInfo()) {
            // expand the publications
            $exhibition->buildInfoFull($this->getDoctrine()->getManager(), $citeProc);
        }


        $result = $this->findCatalogueEntries($exhibition, $request->get('sort'));

        $csvResult = [];

        foreach ($result as $value) {
            $innerArray = [];

            $holder = $value;

            array_push($innerArray, $holder->getExhibition()->getTitle(), $holder->person->getFullname(true) );

            array_push($csvResult, $innerArray);
        }

        $response = new CSVResponse( $csvResult, 200, explode( ', ', 'Startdate, Enddate, Title, City, Venue, # of Cat. Entries, type' ) );
        $response->setFilename( "data.csv" );
        return $response;

    }

    /**
     * @Route("/exhibition/similars/csv/{id}", requirements={"id" = "\d+"}, name="exhibition-similars-csv")
     */
    public function detailActionSimilarsCSV(Request $request, $id = null)
    {
        $routeName = $request->get('_route'); $routeParams = [];

        $repo = $this->getDoctrine()
            ->getRepository('AppBundle:Exhibition');

        if (!empty($id)) {
            $routeParams = [ 'id' => $id ];
            $exhibition = $repo->findOneById($id);
        }

        if (!isset($exhibition) || $exhibition->getStatus() == -1) {
            return $this->redirectToRoute('exhibition-index');
        }

        $citeProc = $this->instantiateCiteProc($request->getLocale());
        if ($exhibition->hasInfo()) {
            // expand the publications
            $exhibition->buildInfoFull($this->getDoctrine()->getManager(), $citeProc);
        }


        $result = $this->findSimilar($exhibition);

        $csvResult = [];

        foreach ($result as $key=>$value) {

            if ($value['count'] > 1) {
                $innerArray = [];

                array_push($innerArray, $value['title'], $value['count'] );

                array_push($csvResult, $innerArray);
            }
        }

        $response = new CSVResponse( $csvResult, 200, explode( ', ', 'Startdate, Enddate, Title, City, Venue, # of Cat. Entries, type' ) );
        $response->setFilename( "data.csv" );

        return $response;
    }

    /**
     * @Route("/exhibition/artists/csv/{id}", requirements={"id" = "\d+"}, name="exhibition-artists-csv")
     */
    public function detailActionArtistsCSV(Request $request, $id = null)
    {
        $routeName = $request->get('_route'); $routeParams = [];

        $repo = $this->getDoctrine()
            ->getRepository('AppBundle:Exhibition');

        if (!empty($id)) {
            $routeParams = [ 'id' => $id ];
            $exhibition = $repo->findOneById($id);
        }

        if (!isset($exhibition) || $exhibition->getStatus() == -1) {
            return $this->redirectToRoute('exhibition-index');
        }

        $citeProc = $this->instantiateCiteProc($request->getLocale());
        if ($exhibition->hasInfo()) {
            // expand the publications
            $exhibition->buildInfoFull($this->getDoctrine()->getManager(), $citeProc);
        }


        $catalogueEntries = $this->findCatalogueEntries($exhibition, $request->get('sort'));

        $artists = [];

        foreach ($catalogueEntries as $entry) {
            $currPerson = $entry->person;
            if (!in_array($currPerson, $artists)) {
                array_push($artists, $currPerson );
            }
        }

        $result = $artists;

        $csvResult = [];

        foreach ($result as $key=>$value) {

            $innerArray = [];
            $person = $value;

            array_push($innerArray, $person->getFullname(true), $person->getBirthDate(), $person->getDeathDate() );

            array_push($csvResult, $innerArray);
        }

        $response = new CSVResponse( $csvResult, 200, explode( ', ', 'Startdate, Enddate, Title, City, Venue, # of Cat. Entries, type' ) );
        $response->setFilename( "data.csv" );
        return $response;

    }


    /**
     * @Route("/exhibition/{id}", requirements={"id" = "\d+"}, name="exhibition")
     */
    public function detailAction(Request $request, $id = null)
    {
        $routeName = $request->get('_route'); $routeParams = [];

        $repo = $this->getDoctrine()
                ->getRepository('AppBundle:Exhibition');

        if (!empty($id)) {
            $routeParams = [ 'id' => $id ];
            $exhibition = $repo->findOneById($id);
        }

        if (!isset($exhibition) || $exhibition->getStatus() == -1) {
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

        $artists = [];
        $genderSplit = ['M' => 0, 'F' => 0]; // first male, second female
        $artistsCountries = [];

        foreach ($catalogueEntries as $entry) {
            $currPerson = $entry->person;
            array_push($artists, $currPerson);
        }

        $artists = array_unique($artists, SORT_REGULAR); // remove multiple artists

        foreach ($artists as $artist) {
            $currNationality = $artist->getNationality();
            if( $artist->getGender() === 'M' ){
                $genderSplit['M'] = $genderSplit['M'] + 1;
            }else if ( $artist->getGender() === 'F' ){
                $genderSplit['F'] = $genderSplit['F'] + 1;
            }

            array_push($artistsCountries, $currNationality );

        }

        $artistsCountries = array_unique($artistsCountries); // remove multiple countries




        $catalogueStatus = SearchListBuilder::$STATUS_LABELS;

        return $this->render('Exhibition/detail.html.twig', [
            'artists' => $artists,
            'pageTitle' => $exhibition->title, // TODO: dates in brackets
            'exhibition' => $exhibition,
            'catalogue' => $exhibition->findBibitem($this->getDoctrine()->getManager(), 1),
            'citeProc' => $citeProc,
            'catalogueEntries' => $catalogueEntries,
            'showWorks' => !empty($_SESSION['user']),
            'similar' => $this->findSimilar($exhibition),
            'currentPageId' => $id,
            'catalogueStatus' => $catalogueStatus,
            'genderSplit' => $genderSplit,
            'artistCountries' => $artistsCountries,
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
     * @Route("/exhibition/{id}/stats/info", requirements={"id" = "\d*"}, name="exhibition-stats-info")
     */
    public function statsInfoAction(Request $request, $id)
    {
        $chart = $request->get('chart');
        switch ($chart) {
            case 'container-age':
                $personIds = StatisticsController::exhibitionAgePersonIds($em = $this->getDoctrine()->getEntityManager(), $request->get('point'), $id);
                foreach ($personIds as $type => $ids) {
                    $personIds[$type] = $this->hydratePersons($ids);
                }

                return $this->render('Exhibition/stats-info-age.html.twig', [
                    'age' => $request->get('point'),
                    'personsByType' => $personIds,
                ]);
                break;
            case 'container-countries':
                $personIds = StatisticsController::exhibitionNationalityPersonIds($em = $this->getDoctrine()->getEntityManager(), $request->get('point'), $id);

                // print_r($personIds['artists']);
                foreach ($personIds as $type => $ids) {
                    $personIds[$type] = $this->hydratePersons($ids);
                }

                return $this->render('Shared/modal.html.twig', [
                    'heading' => 'Artists exhibiting in ' . $request->get('point'),
                    'elements' => $personIds['artists'],
                    'type' => 'person'
                ]);
                break;
            case 'container-works':
                $worksIds = StatisticsController::itemExhibitionTypeDistributionFull($em = $this->getDoctrine()->getEntityManager(), $request->get('point'), $id);

                // print_r($personIds['artists']);

                foreach ($worksIds as $type => $ids) {
                    $worksIds[$type] = $this->hydrateWorks($ids);
                }

                return $this->render('Shared/modal.html.twig', [
                    'heading' => 'Works in Catalogue with type of ' . $request->get('point'),
                    'elements' => $worksIds,
                    'type' => 'works'
                ]);
                break;
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

        if (!isset($exhibition) || $exhibition->getStatus() == -1) {
            return $this->redirectToRoute('exhibition-index');
        }

        // display the artists by birth-year
        $stats = StatisticsController::exhibitionAgeDistribution($em = $this->getDoctrine()->getEntityManager(), $exhibition->getId());
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
            'exhibitionId' => $exhibition->getId()
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

        if (!isset($exhibition) || $exhibition->getStatus() == -1) {
            return $this->redirectToRoute('exhibition-index');
        }

        // display the artists by birth-year
        $stats = StatisticsController::exhibitionAgeDistribution($em = $this->getDoctrine()->getEntityManager(), $exhibition->getId());
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


    public function statsActionIndex($ids = null)
    {
        if (!empty($id)) {
            $repo = $this->getDoctrine()
                ->getRepository('AppBundle:Exhibition');
            $routeParams = [ 'id' => $id ];
            $exhibition = $repo->findOneById($id);
        }

        if (!isset($exhibition) || $exhibition->getStatus() == -1) {
            return $this->redirectToRoute('exhibition-index');
        }

        // display the artists by birth-year
        $stats = StatisticsController::exhibitionAgeDistribution($em = $this->getDoctrine()->getEntityManager(), null, null, null, null, $ids);
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
            'exhibitionId' => $exhibition->getId()
        ]);

        // display the static content
        return $this->render('Exhibition/stats-detail.html.twig', [
            'chart' => implode("\n", $charts),
            'exhibitionId' => $exhibition->getId(),
        ]);
    }
}
