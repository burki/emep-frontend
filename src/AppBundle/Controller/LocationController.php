<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\User\UserInterface;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;


use Pagerfanta\Pagerfanta;

use AppBundle\Utils\CsvResponse;
use AppBundle\Utils\SearchListBuilder;
use AppBundle\Utils\SearchListPagination;
use AppBundle\Utils\SearchListAdapter;


/**
 *
 */
class LocationController
extends CrudController
{
    use MapBuilderTrait;
    use StatisticsBuilderTrait;
    use SharingBuilderTrait;

    // TODO: share with ExhibitionController
    protected function buildCountries()
    {
        $qb = $this->getDoctrine()
                ->getManager()
                ->createQueryBuilder();

        $qb->select([
                'P.countryCode',
            ])
            ->distinct()
            ->from('AppBundle:Location', 'L')
            ->leftJoin('L.place', 'P')
            ->where('L.status <> -1 AND 0 = BIT_AND(L.flags, 256) AND P.countryCode IS NOT NULL')
            ;

        return $this->buildActiveCountries($qb);
    }


    /**
     * @Route("/organizer", name="organizer-index")
     */
    public function organizerIndexAction(Request $request,
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
                        'route' => 'organizer',
                    ]);

                if (!is_null($userAction)) {
                    return $this->redirectToRoute($userAction->getRoute(),
                        $userAction->getRouteParams());
                }
            }
        }

        $route = $request->get('_route');

        $types = $this->buildVenueTypes();
        $form = $this->form = $this->get('form.factory')->create(\AppBundle\Filter\LocationFilterType::class, [
            'country_choices' => array_flip($this->buildCountries()),
            'location_type_choices' => array_combine($types, $types),
            'location_type_placeholder' => 'select type of organizing body',
            'ids' => range(0, 9999)
        ]);

        // $countries = $form->get('country')->getData();
        // $locationType = $form->get('location_type')->getData();
        $stringQuery = $form->get('search')->getData();
        // $ids = $form->get('id')->getData();

        $requestURI =  $request->getRequestUri();

        $listBuilder = $this->instantiateListBuilder($request, $urlGenerator, false, 'Organizer');
        $listPagination = new SearchListPagination($listBuilder);
        $page = $request->get('page', 1);
        $listPage = $listPagination->get($this->pageSize, ($page - 1) * $this->pageSize);
        $adapter = new SearchListAdapter($listPage);
        $pager = new Pagerfanta($adapter);
        $pager->setMaxPerPage($listPage['limit']);
        $pager->setCurrentPage(intval($listPage['offset'] / $listPage['limit']) + 1);

        return $this->render('Organizer/index.html.twig', [
            'pageTitle' => $this->get('translator')->trans('organizer-index' == $route ? 'Organizing Bodies' : 'Venues'),
            // 'pagination' => $pagination,
            'pager' => $pager,

            'listBuilder' => $listBuilder,
            'form' => $form->createView(),
            'countryArray' => $this->buildCountries(),
            'organizerTypesArray' => $types,
            // 'countries' => $countries,
            // 'ids' => $ids,
            // 'locationType' => $locationType,
            'stringPart' => $stringQuery,
            // 'locations' => $locations,
            'requestURI' =>  $requestURI,
            'searches' => $this->lookupSearches($user, 'organizer')
        ]);
    }

    /**
     * @Route("/organizer/map", name="organizer-index-map")
     */
    public function organizerIndexMapAction(Request $request,
                                            UrlGeneratorInterface $urlGenerator,
                                            UserInterface $user = null)
    {
        $types = $this->buildVenueTypes();
        $form = $this->form = $this->get('form.factory')->create(\AppBundle\Filter\LocationFilterType::class, [
            'country_choices' => array_flip($this->buildCountries()),
            'location_type_choices' => array_combine($types, $types),
            'location_type_placeholder' => 'select type of organizing body',
            'ids' => range(0, 9999)
        ]);

        $listBuilder = $this->instantiateListBuilder($request, $urlGenerator, 'extended', $entity = 'Organizer');
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
     * @Route("/organizer/stats", name="organizer-index-stats")
     */
    public function organizerIndexStatsAction(Request $request,
                                              UrlGeneratorInterface $urlGenerator,
                                              UserInterface $user = null)
    {
        $types = $this->buildVenueTypes();
        $form = $this->form = $this->get('form.factory')->create(\AppBundle\Filter\LocationFilterType::class, [
            'country_choices' => array_flip($this->buildCountries()),
            'location_type_choices' => array_combine($types, $types),
            'location_type_placeholder' => 'select type of organizing body',
            'ids' => range(0, 9999)
        ]);

        $listBuilder = $this->instantiateListBuilder($request, $urlGenerator, false, $entity = 'Organizer');

        $charts = $this->buildLocationCharts($request, $urlGenerator, $listBuilder);

        return new \Symfony\Component\HttpFoundation\Response(implode("\n", $charts));
    }

    /**
     * @Route("/location", name="location-index")
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
                        'route' => 'location',
                    ]);

                if (!is_null($userAction)) {
                    return $this->redirectToRoute($userAction->getRoute(),
                        $userAction->getRouteParams());
                }
            }
        }

        $route = $request->get('_route');

        $types = $this->buildVenueTypes();
        $form = $this->form = $this->get('form.factory')->create(\AppBundle\Filter\LocationFilterType::class, [
            'country_choices' => array_flip($this->buildCountries()),
            'location_type_choices' => array_combine($types, $types),
            'location_type_placeholder' => 'select type of venue',
            'ids' => range(0, 9999)
        ]);

        // $countries = $form->get('country')->getData();
        // $locationType = $form->get('location_type')->getData();
        $stringQuery = $form->get('search')->getData();
        // $ids = $form->get('id')->getData();

        $requestURI =  $request->getRequestUri();

        $listBuilder = $this->instantiateListBuilder($request, $urlGenerator, false, 'Venue');
        $listPagination = new SearchListPagination($listBuilder);
        $page = $request->get('page', 1);
        $listPage = $listPagination->get($this->pageSize, ($page - 1) * $this->pageSize);
        $adapter = new SearchListAdapter($listPage);
        $pager = new Pagerfanta($adapter);
        $pager->setMaxPerPage($listPage['limit']);
        $pager->setCurrentPage(intval($listPage['offset'] / $listPage['limit']) + 1);

        return $this->render('Location/index.html.twig', [
            'pageTitle' => $this->get('translator')->trans('organizer-index' == $route ? 'Organizing Bodies' : 'Venues'),
            // 'pagination' => $pagination,
            'pager' => $pager,

            'listBuilder' => $listBuilder,
            'form' => $form->createView(),
            'countryArray' => $this->buildCountries(),
            'organizerTypesArray' => $types,
            // 'countries' => $countries,
            // 'ids' => $ids,
            // 'locationType' => $locationType,
            'stringPart' => $stringQuery,
            // 'locations' => $locations,
            'requestURI' =>  $requestURI,
            'searches' => $this->lookupSearches($user)
        ]);
    }

    /**
     * @Route("/location/map", name="location-index-map")
     */
    public function indexMapAction(Request $request,
                                   UrlGeneratorInterface $urlGenerator,
                                   UserInterface $user = null)
    {
        $types = $this->buildVenueTypes();
        $form = $this->form = $this->get('form.factory')->create(\AppBundle\Filter\LocationFilterType::class, [
            'country_choices' => array_flip($this->buildCountries()),
            'location_type_choices' => array_combine($types, $types),
            'location_type_placeholder' => 'select type of venue',
            'ids' => range(0, 9999)
        ]);

        $listBuilder = $this->instantiateListBuilder($request, $urlGenerator, 'extended', $entity = 'Venue');
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
     * @Route("/location/stats", name="location-index-stats")
     */
    public function indexStatsAction(Request $request,
                                     UrlGeneratorInterface $urlGenerator,
                                     UserInterface $user = null)
    {
        $types = $this->buildVenueTypes();
        $form = $this->form = $this->get('form.factory')->create(\AppBundle\Filter\LocationFilterType::class, [
            'country_choices' => array_flip($this->buildCountries()),
            'location_type_choices' => array_combine($types, $types),
            'location_type_placeholder' => 'select type of venue',
            'ids' => range(0, 9999)
        ]);

        $listBuilder = $this->instantiateListBuilder($request, $urlGenerator, false, $entity = 'Venue');

        $charts = $this->buildLocationCharts($request, $urlGenerator, $listBuilder);

        return new \Symfony\Component\HttpFoundation\Response(implode("\n", $charts));
    }

    // TODO MOVE TO SHARED
    protected function lookupSearchesOrganizer($user)
    {
        if (is_null($user)) {
            return [];
        }

        $qb = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();

        $qb->select('UA')
            ->from('AppBundle:UserAction', 'UA')
            ->where("UA.route = 'organizer'")
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
            ->where("UA.route = 'location'")
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
     * @Route("/location/save", name="location-save")
     */
    public function saveSearchActionLocation(Request $request,
                                           UserInterface $user)
    {

        $parametersAsString = $request->get('entity');
        $parametersAsString = str_replace("/location?", '', $parametersAsString);


        parse_str($parametersAsString, $parameters);


        $form = $this->createForm(\AppBundle\Form\Type\SaveSearchType::class);

        //$form->get

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $userAction = new \AppBundle\Entity\UserAction();

            $userAction->setUser($user);
            $userAction->setRoute($route = 'location');
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
     * @Route("/organizer/save", name="organizer-save")
     */
    public function saveSearchActionOrganizer(Request $request,
                                             UserInterface $user)
    {

        $parametersAsString = $request->get('entity');
        $parametersAsString = str_replace("/organizer?", '', $parametersAsString);


        parse_str($parametersAsString, $parameters);


        $form = $this->createForm(\AppBundle\Form\Type\SaveSearchType::class);

        //$form->get

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $userAction = new \AppBundle\Entity\UserAction();

            $userAction->setUser($user);
            $userAction->setRoute($route = 'organizer');
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


    public function indexDataNumberCountries($results)
    {
        $countryCodes = [];

        foreach ($results as $result) {
            $location = $result[0];
            $place = $location->getPlace();
            if (!is_null($place)) {
                $countryCode = $place->getCountryCode();
            }
            else {
                $countryCode = $location->getCountry();
            }

            array_push($countryCodes, $countryCode);
        }

        // replacing null values
        $countryCodes = array_replace($countryCodes,array_fill_keys(array_keys($countryCodes, null),''));

        $valuesTotal = array_count_values($countryCodes);

        $valuesOnly = array_keys($valuesTotal);
        $countsOnly =  array_values($valuesTotal);


        $i = 0;
        $finalDataJson = '[';

        foreach ($valuesOnly as $val) {
            $i > 0 ? $finalDataJson .= ", " : '';

            $count = $countsOnly[$i] ;

            $finalDataJson .= '{ name: "' . $val . '", y: '. $count . '} ';
            $i += 1;
        }
        $finalDataJson .= ']';

        return [ $finalDataJson, array_sum($countsOnly) ];
    }


    public function indexDataNumberVenueType($locations)
    {
        //$exhibitions = $location->getExhibitions();

        $venueTypes = [];

        foreach ($locations as $location) {

            //print count($entries);
            //print '   ';

            $currType = (string) $location[0]->getType() == '' ? 'unknown' : (string) $location[0]->getType();

            array_push($venueTypes, (string) $currType );

        }

        $typesTotal = array_count_values ( $venueTypes );

        //$exhibitionPlacesArray = array_keys($exhibitionPlaces);

        // print_r($exhibitionPlacesArray);

        $typesOnly = ( array_keys($typesTotal) );
        $valuesOnly =  array_values( $typesTotal );


        $sumOfAllTypes= array_sum(array_values($typesTotal));

        $i = 0;
        $finalDataJson = '[';

        foreach ($typesOnly as $place) {

            $i > 0 ? $finalDataJson .= ", " : '';

            $numberOfExhibitions = $valuesOnly[$i] ;

            $finalDataJson .= '{ name: "' .$place. '", y: '. $numberOfExhibitions . '} ';
            $i += 1;
        }
        $finalDataJson .= ']';



        $returnArray = [$finalDataJson, $sumOfAllTypes];


        return $returnArray;
    }


    /**
     * @Route("/location/artists/csv/{id}", requirements={"id" = "\d+"}, name="location-artists-csv")
     */
    public function detailActionArtists(Request $request, $id = null, $ulan = null, $gnd = null)
    {
        $routeName = $request->get('_route'); $routeParams = [];

        $repo = $this->getDoctrine()
            ->getRepository('AppBundle:Location');

        if (!empty($id)) {
            $routeParams = [ 'id' => $id ];
            $location = $repo->findOneById($id);
        }

        if (!isset($location) || $location->getStatus() == -1) {
            return $this->redirectToRoute('location-index');
        }

        $locale = $request->getLocale();
        if (in_array($request->get('_route'), [ 'location-jsonld' ])) {
            return new JsonLdResponse($person->jsonLdSerialize($locale));
        }

        // artists this venue
        $qb = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();

        $qb->select([
            'P',
            'COUNT(DISTINCT E.id) AS numExhibitionSort',
            'COUNT(DISTINCT IE.id) AS numCatEntrySort',
            "CONCAT(COALESCE(P.familyName,P.givenName), ' ', COALESCE(P.givenName, '')) HIDDEN nameSort"
        ])
            ->from('AppBundle:Person', 'P')
            ->innerJoin('AppBundle:ItemExhibition', 'IE',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'IE.person = P AND IE.title IS NOT NULL')
            ->innerJoin('IE.exhibition', 'E')
            ->where('E.location = :location AND E.status <> -1')
            ->setParameter('location', $location)
            ->groupBy('P.id')
            ->orderBy('nameSort')
        ;
        $artists = $qb->getQuery()->getResult();

        // stats
        $qb = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();
        $qb->select([
            'E.id AS id',
            'COUNT(DISTINCT IE.id) AS numCatEntrySort',
            'COUNT(DISTINCT P.id) AS numPersonSort',
        ])
            ->from('AppBundle:Exhibition', 'E')
            ->leftJoin('E.location', 'L')
            ->leftJoin('AppBundle:ItemExhibition', 'IE',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'IE.exhibition = E AND IE.title IS NOT NULL')
            ->leftJoin('IE.person', 'P')
            ->where('E.location = :location AND E.status <> -1')
            ->setParameter('location', $location)
            ->groupBy('E.id')
        ;
        $exhibitionStats = [];
        foreach ($qb->getQuery()->getResult() as $row) {
            $exhibitionStats[$row['id']] = $row;
        }

        $result = $artists;

        $csvResult = [];

        foreach ($result as $key=>$value) {

            $artist = $value[0];

            $innerArray = [];
            array_push($innerArray, $artist->getFullName(true), $value['numExhibitionSort'], $value['numCatEntrySort'] );

            array_push($csvResult, $innerArray);
        }

        $response = new CSVResponse( $csvResult, 200, explode( ', ', 'Startdate, Enddate, Title, City, Venue, # of Cat. Entries, type' ) );
        $response->setFilename( "data.csv" );
        return $response;
    }

    /**
     * @Route("/location/exhibitions/csv/{id}", requirements={"id" = "\d+"}, name="location-exhibitions-csv")
     */
    public function detailActionExhibitions(Request $request, $id = null, $ulan = null, $gnd = null)
    {
        $routeName = $request->get('_route'); $routeParams = [];

        $repo = $this->getDoctrine()
            ->getRepository('AppBundle:Location');

        if (!empty($id)) {
            $routeParams = [ 'id' => $id ];
            $location = $repo->findOneById($id);
        }

        if (!isset($location) || $location->getStatus() == -1) {
            return $this->redirectToRoute('location-index');
        }

        $locale = $request->getLocale();
        if (in_array($request->get('_route'), [ 'location-jsonld' ])) {
            return new JsonLdResponse($person->jsonLdSerialize($locale));
        }

        // artists this venue
        $qb = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();

        $qb->select([
            'P',
            'COUNT(DISTINCT E.id) AS numExhibitionSort',
            'COUNT(DISTINCT IE.id) AS numCatEntrySort',
            "CONCAT(COALESCE(P.familyName,P.givenName), ' ', COALESCE(P.givenName, '')) HIDDEN nameSort"
        ])
            ->from('AppBundle:Person', 'P')
            ->innerJoin('AppBundle:ItemExhibition', 'IE',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'IE.person = P AND IE.title IS NOT NULL')
            ->innerJoin('IE.exhibition', 'E')
            ->where('E.location = :location AND E.status <> -1')
            ->setParameter('location', $location)
            ->groupBy('P.id')
            ->orderBy('nameSort')
        ;
        $artists = $qb->getQuery()->getResult();

        // stats
        $qb = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();

        $qb->select([
            'E.id AS id',
            'COUNT(DISTINCT IE.id) AS numCatEntrySort',
            'COUNT(DISTINCT P.id) AS numPersonSort',
        ])
            ->from('AppBundle:Exhibition', 'E')
            ->leftJoin('E.location', 'L')
            ->leftJoin('AppBundle:ItemExhibition', 'IE',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'IE.exhibition = E AND IE.title IS NOT NULL')
            ->leftJoin('IE.person', 'P')
            ->where('E.location = :location AND E.status <> -1')
            ->setParameter('location', $location)
            ->groupBy('E.id')
        ;
        $exhibitionStats = [];
        foreach ($qb->getQuery()->getResult() as $row) {
            $exhibitionStats[$row['id']] = $row;
        }

        $result = $location->getExhibitions();

        $csvResult = [];

        foreach ($result as $exhibition) {
            $innerArray = [];
            array_push($innerArray, $exhibition->getStartdate(), $exhibition->getEnddate(), $exhibition->getLocation()->getPlaceLabel(), $exhibitionStats[$exhibition->getId()]['numCatEntrySort'] );

            array_push($csvResult, $innerArray);
        }

        $response = new CSVResponse( $csvResult, 200, explode( ', ', 'Startdate, Enddate, Title, City, Venue, # of Cat. Entries, type' ) );
        $response->setFilename( "data.csv" );
        return $response;
    }

    /**
     * @Route("/location/{id}", requirements={"id" = "\d+"}, name="location")
     * @Route("/organizer/{id}", requirements={"id" = "\d+"}, name="organizer")
     */
    public function detailAction(Request $request, $id = null, $ulan = null, $gnd = null)
    {
        $routeName = $request->get('_route'); $routeParams = [];

        $repo = $this->getDoctrine()
                ->getRepository('AppBundle:Location');

        if (!empty($id)) {
            $routeParams = [ 'id' => $id ];
            $location = $repo->findOneById($id);
        }

        if (!isset($location) || $location->getStatus() == -1) {
            return $this->redirectToRoute('location-index');
        }

        $locale = $request->getLocale();
        if (in_array($request->get('_route'), [ 'location-jsonld' ])) {
            return new JsonLdResponse($person->jsonLdSerialize($locale));
        }

        // artists this venue
        $qb = $this->getDoctrine()
                ->getManager()
                ->createQueryBuilder();

        $qb->select([
                'P',
                'COUNT(DISTINCT E.id) AS numExhibitionSort',
                'COUNT(DISTINCT IE.id) AS numCatEntrySort',
                "CONCAT(COALESCE(P.familyName,P.givenName), ' ', COALESCE(P.givenName, '')) HIDDEN nameSort"
            ])
            ->from('AppBundle:Person', 'P')
            ->innerJoin('AppBundle:ItemExhibition', 'IE',
                       \Doctrine\ORM\Query\Expr\Join::WITH,
                       'IE.person = P AND IE.title IS NOT NULL')
            ->innerJoin('IE.exhibition', 'E')
            ->where('E.location = :location AND E.status <> -1')
            ->setParameter('location', $location)
            ->groupBy('P.id')
            ->orderBy('nameSort')
            ;
        $artists = $qb->getQuery()->getResult();

        // stats
        $qb = $this->getDoctrine()
                ->getManager()
                ->createQueryBuilder();
        $qb->select([
                'E.id AS id',
                'COUNT(DISTINCT IE.id) AS numCatEntrySort',
                'COUNT(DISTINCT P.id) AS numPersonSort',
            ])
            ->from('AppBundle:Exhibition', 'E')
            ->leftJoin('E.location', 'L')
            ->leftJoin('AppBundle:ItemExhibition', 'IE',
                       \Doctrine\ORM\Query\Expr\Join::WITH,
                       'IE.exhibition = E AND IE.title IS NOT NULL')
            ->leftJoin('IE.person', 'P')
            ->where('E.location = :location AND E.status <> -1')
            ->setParameter('location', $location)
            ->groupBy('E.id')
            ;
        $exhibitionStats = [];
        foreach ($qb->getQuery()->getResult() as $row) {
           $exhibitionStats[$row['id']] = $row;
        }


        // get alternative location for the case that the geo is empty
        $qbAlt = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();

        $qbAlt->select([
            'L',
            "L.placeLabel ",
            "P.latitude",
            "P.longitude"
        ])
            ->from('AppBundle:Location', 'L')
            ->leftJoin('AppBundle:Place', 'P',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'P.name = L.placeLabel')
            ->where('L.id = ' . $id)
        ;

        $place = ($qbAlt->getQuery()->execute());

        $dataNumberOfArtistsPerCountry = $this->detailDataNumberOfArtistsPerCountry($artists);

        $detailDataNumberItemTypes = $this->detailDataNumberItemTypes($location);

        return $this->render('Location/detail.html.twig', [
            'pageTitle' => $location->getName(),
            'location' => $location,
            'altPlace' => $place[0],
            'exhibitionStats' => $exhibitionStats,
            'artists' => $artists,
            'dataNumberOfArtistsPerCountry' => $dataNumberOfArtistsPerCountry,
            'detailDataNumberItemTypes' => $detailDataNumberItemTypes,
            'pageMeta' => [
                /*
                'jsonLd' => $exhibition->jsonLdSerialize($locale),
                'og' => $this->buildOg($exhibition, $routeName, $routeParams),
                'twitter' => $this->buildTwitter($exhibition, $routeName, $routeParams),
                */
            ],
        ]);
    }

    public function detailDataNumberItemTypes($location)
    {
        $exhibitions = $location->getExhibitions();

        $types = [];

        foreach ($exhibitions as $exhibition) {
            $entries = $exhibition->catalogueEntries;

            foreach ($entries as $entry) {
                if ($entry->type) {
                    $currType = $entry->type->getName();
                    array_push($types, (string)$currType == '0_unknown' ? 'unknown' : $currType );
                }
            };
        }

        $typesTotal = array_count_values($types);

        $typesOnly = array_keys($typesTotal);
        $valuesOnly = array_values($typesTotal);


        $sumOfAllTypes= array_sum(array_values($typesTotal));

        $i = 0;
        $finalDataJson = '[';

        foreach ($typesOnly as $place) {
            $i > 0 ? $finalDataJson .= ", " : '';

            $numberOfExhibitions = $valuesOnly[$i] ;

            $finalDataJson .= "{ name: '${place}', y: ${numberOfExhibitions} } ";
            $i += 1;
        }
        $finalDataJson .= ']';


        $returnArray = [ $finalDataJson, $sumOfAllTypes ];


        return $returnArray;
    }




    public function detailDataNumberOfArtistsPerCountry($artists)
    {
        $artistNationalities = [];

        foreach ($artists as $artist) {
            array_push($artistNationalities, (string) $artist[0]->getNationality() );
        }

        $artistNationalitiesTotal = array_count_values($artistNationalities);

        $nationalitiesOnly = array_keys($artistNationalitiesTotal);
        $valuesOnly = array_values($artistNationalitiesTotal);


        $sumOfAllNationalities = array_sum(array_values($artistNationalitiesTotal));

        $i = 0;
        $finalDataJson = '[';

        foreach ($nationalitiesOnly as $place) {

            $i > 0 ? $finalDataJson .= ", " : '';

            $numberOfExhibitions = $valuesOnly[$i] ;

            $finalDataJson .= "{ name: '${place}', y: ${numberOfExhibitions} } ";
            $i += 1;
        }
        $finalDataJson .= ']';


        $returnArray = [ $finalDataJson, $sumOfAllNationalities, $i ];

        return $returnArray;
    }
}
