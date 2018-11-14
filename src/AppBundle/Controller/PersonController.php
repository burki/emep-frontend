<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Intl\Intl;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

use AppBundle\Utils\CsvResponse;
use Symfony\Component\Security\Core\User\UserInterface;


/**
 *
 */
class PersonController
extends CrudController
{
    use SharingBuilderTrait;

    protected function buildCountries()
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
     * @Route("/person/csv", name="person-csv")
     */
    public function indexToCsvAction(Request $request)
    {
        $route = $request->get('_route');

        $qb = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();

        $qb->select([
            'P',
            'COUNT(DISTINCT E.id) AS numExhibitionSort',
            'COUNT(DISTINCT IE.id) AS numCatEntrySort',
            "P.sortName HIDDEN nameSort"
        ])
            ->from('AppBundle:Person', 'P')
            ->leftJoin('P.exhibitions', 'E')

            ->leftJoin('E.location', 'L')
            ->leftJoin('L.place', 'Pl')

            ->leftJoin('P.catalogueEntries', 'IE')
            ->where('P.status <> -1')
            ->groupBy('P.id') // for Count
            ->orderBy('nameSort')
        ;

        $organizerTypes = $this->buildOrganizerTypes();

        $minBirthYear = 1800;
        $maxBirthYear = 1905;

        $minDeathYear = 1850;
        $maxDeathYear = 2000;


        $form = $this->get('form.factory')->create(\AppBundle\Filter\PersonFilterType::class, [
            'choices' => array_flip($this->buildCountries()),
            'ids' => range(0, 9999),
            'birthyears' => [$minBirthYear, $maxBirthYear],
            'deathyears' => [$minDeathYear, $maxDeathYear],
            'country_choices' => array_flip($this->buildCountries()),
            'organizer_type_choices' => array_combine($organizerTypes, $organizerTypes)
        ]);

        if ($request->query->has($form->getName())) {
            // manually bind values from the request
            $form->submit($request->query->get($form->getName()));

            // build the query from the given form object
            $this->get('lexik_form_filter.query_builder_updater')->addFilterConditions($form, $qb);
        }

        // getting the form data fields to send it back down and use it in hinclude requests async
        $gender = $form->get('gender')->getData();
        $nationalities = $form->get('nationality')->getData();
        $stringQuery = $form->get('search')->getData();
        $ids = $form->get('id')->getData();



        $pagination = $this->buildPagination($request, $qb->getQuery(), [
            // the following leads to wrong display in combination with our
            // helper.pagination_sortable()
            // 'defaultSortFieldName' => 'nameSort', 'defaultSortDirection' => 'asc',
        ]);

        // echo ($mapdata['bounds']);

        $result = $qb->getQuery()->execute();


        /*  CREATE DATABLE LIKE THE FRONTEND */


        $csvResult = [];

        foreach ($result as $value) {
            $innerArray = [];

            $person = $value[0];

            array_push($innerArray, $person->getFullname(true), $person->getBirthDate(), $person->getDeathDate(), $value['numExhibitionSort'], $value['numCatEntrySort'] );


            array_push($csvResult, $innerArray);
        }

        // print_r($csvResult);

        /* END DATATABLE CREATION LIKE FRONTEND */


        $response = new CSVResponse( $csvResult, 200, explode( ', ', 'Startdate, Enddate, Title, City, Venue, # of Cat. Entries, type' ) );
        $response->setFilename( "data.csv" );
        return $response;
    }

    /**
     * @Route("/person", name="person-index")
     * @Route("/person-by-nationality", name="person-nationality")
     */
    public function indexAction(Request $request, UserInterface $user = null)
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
                        'route' => 'person',
                    ]);

                if (!is_null($userAction)) {
                    return $this->redirectToRoute($userAction->getRoute(),
                        $userAction->getRouteParams());
                }
            }
        }

        $requestURI =  $request->getRequestUri();


        $qb = $this->getDoctrine()
                ->getManager()
                ->createQueryBuilder();

        $qb->select([
                'P',
                'COUNT(DISTINCT E.id) AS numExhibitionSort',
                'COUNT(DISTINCT IE.id) AS numCatEntrySort',
                "P.sortName HIDDEN nameSort",

            ])
            ->from('AppBundle:Person', 'P')
            ->leftJoin('P.exhibitions', 'E')

            ->leftJoin('E.location', 'L')
            ->leftJoin('L.place', 'Pl')

            ->leftJoin('P.catalogueEntries', 'IE')
            ->where('P.status <> -1')
            ->groupBy('P.id') // for Count
            ->orderBy('nameSort')
            ;


        $organizerTypes = $this->buildOrganizerTypes();

        $minBirthYear = 1800;
        $maxBirthYear = 1905;

        $minDeathYear = 1850;
        $maxDeathYear = 2000;


        $form = $this->get('form.factory')->create(\AppBundle\Filter\PersonFilterType::class, [
            'choices' => array_flip($this->buildCountries()),
            'ids' => range(0, 9999),
            'birthyears' => [$minBirthYear, $maxBirthYear],
            'deathyears' => [$minDeathYear, $maxDeathYear],
            'country_choices' => array_flip($this->buildCountries()),
            'organizer_type_choices' => array_combine($organizerTypes, $organizerTypes)
        ]);




        if ($request->query->has($form->getName())) {
            // manually bind values from the request
            $form->submit($request->query->get($form->getName()));

            // build the query from the given form object
            $this->get('lexik_form_filter.query_builder_updater')->addFilterConditions($form, $qb);
        }

        // getting the form data fields to send it back down and use it in hinclude requests async
        $gender = $form->get('gender')->getData();
        $nationalities = $form->get('nationality')->getData();
        $stringQuery = $form->get('search')->getData();
        $ids = $form->get('id')->getData();
        $deathDate = $form->get('deathDate')->getData();
        $birthDate = $form->get('birthDate')->getData();
        $exhibitionCountries = $form->get('country')->getData();
        $organizerTypesQuery = $form->get('organizer_type')->getData();


        $pagination = $this->buildPagination($request, $qb->getQuery(), [
            // the following leads to wrong display in combination with our
            // helper.pagination_sortable()
            // 'defaultSortFieldName' => 'nameSort', 'defaultSortDirection' => 'asc',
        ]);


        $artists = $qb->getQuery()->getResult();
        $indexDataNumberCountries = $this->indexDataNumberCountries($artists);

        return $this->render('Person/index.html.twig', [
            'pageTitle' => $this->get('translator')->trans('Artists'),
            'pagination' => $pagination,
            'form' => $form->createView(),
            'nationalities' => $nationalities,
            'countryArray' => $this->buildCountries(),
            'gender' => $gender,
            'ids' => $ids,
            'stringPart' => $stringQuery,
            'minBirthYear' => $minBirthYear,
            'maxBirthYear' => $maxBirthYear,
            'minDeathYear' => $minDeathYear,
            'maxDeathYear' => $maxDeathYear,
            'organizerTypes' => $organizerTypes,
            'deathDate' => $deathDate,
            'birthDate' => $birthDate,
            'exhibitionCountries' => $exhibitionCountries,
            'organizerTypesQuery' => $organizerTypesQuery,
            'indexDataNumberCountries' => $indexDataNumberCountries,
            'artists' => $artists,
            'requestURI' =>  $requestURI,
            'searches' => $this->lookupSearches($user, 'exhibition')
        ]);
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
            ->where("UA.route = 'person'")
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
     * @Route("/person/save", name="person-save")
     */
    public function saveSearchActionPerson(Request $request,
                                               UserInterface $user)
    {

        $parametersAsString = $request->get('entity');
        $parametersAsString = str_replace("/person?", "", $parametersAsString);


        parse_str($parametersAsString, $parameters);


        $form = $this->createForm(\AppBundle\Form\Type\SaveSearchType::class);

        //$form->get

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $userAction = new \AppBundle\Entity\UserAction();

            $userAction->setUser($user);
            $userAction->setRoute($route = 'person');
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


    public function indexDataNumberCountries($artists)
    {
        //$exhibitions = $location->getExhibitions();

        $nationalities = [];

        foreach ($artists as $artist) {

            //print count($entries);
            //print '   ';

            $currNat = (string) $artist[0]->getNationality() == '' ? 'unknown' : $artist[0]->getNationality() ;

            array_push($nationalities, (string) $currNat );

        }

        $nationalitiesTotal = array_count_values ( $nationalities );

        //$exhibitionPlacesArray = array_keys($exhibitionPlaces);

        // print_r($exhibitionPlacesArray);

        $nationalitiesOnly = ( array_keys($nationalitiesTotal) );
        $valuesOnly =  array_values( $nationalitiesTotal );


        $sumOfAllNationalities= array_sum(array_values($nationalitiesTotal));

        $i = 0;
        $finalDataJson = '[';

        foreach ($nationalitiesOnly as $place){

            $i > 0 ? $finalDataJson .= ", " : "";

            $numberOfNationalities = $valuesOnly[$i] ;

            $finalDataJson .= '{ name: "' .$place. '", y: '. $numberOfNationalities . '} ';
            $i += 1;
        }
        $finalDataJson .= ']';



        $returnArray = [$finalDataJson, $sumOfAllNationalities];


        return $returnArray;
    }


    /**
     * @Route("/person/shared.embed/{exhibitions}", name="person-shared-partial")
     * @Route("/person/shared/{exhibitions}", name="person-shared")
     */
    public function sharedAction(Request $request, $exhibitions = null)
    {
        if (!is_null($exhibitions)) {
            $exhibitions = explode(',', $exhibitions);
        }
        if (is_null($exhibitions) || count($exhibitions) < 2) {
            throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException("Invalid argument");
        }

        $names = [];
        $exhibitionRepo = $this->getDoctrine()
                ->getRepository('AppBundle:Exhibition');
        for ($i = 0; $i < 2; $i++) {
            $criteria = new \Doctrine\Common\Collections\Criteria();

            $criteria->where($criteria->expr()->eq('id', $exhibitions[$i]));

            $criteria->andWhere($criteria->expr()->neq('status', -1));

            $matching = $exhibitionRepo->matching($criteria);
            if (0 == count($matching)) {
                throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException("Invalid argument");
            }
            $names[] = $matching[0]->getTitle();
        }

        $qb = $this->getDoctrine()
                ->getManager()
                ->createQueryBuilder();

        $qb->select([
                'P',
                "CONCAT(COALESCE(P.familyName,P.givenName), ' ', COALESCE(P.givenName, '')) HIDDEN nameSort"
            ])
            ->from('AppBundle:Person', 'P')
            ->innerJoin('AppBundle:ItemExhibition', 'IE1',
                       \Doctrine\ORM\Query\Expr\Join::WITH,
                       'IE1.person = P')
            ->innerJoin('AppBundle:Exhibition', 'E1',
                       \Doctrine\ORM\Query\Expr\Join::WITH,
                       'IE1.exhibition = E1 AND E1.id=:exhibition1')
            ->innerJoin('AppBundle:ItemExhibition', 'IE2',
                       \Doctrine\ORM\Query\Expr\Join::WITH,
                       'IE2.person = P')
            ->innerJoin('AppBundle:Exhibition', 'E2',
                       \Doctrine\ORM\Query\Expr\Join::WITH,
                       'IE2.exhibition = E2 AND E2.id=:exhibition2')
            ->setParameters([ 'exhibition1' => $exhibitions[0], 'exhibition2' => $exhibitions[1] ])
            ->where('P.status <> -1')
            ->groupBy('P.id')
            ->orderBy('nameSort')
            ;

        $pagination = $this->buildPagination($request, $qb->getQuery(), [
            'defaultSortFieldName' => 'nameSort', 'defaultSortDirection' => 'asc',
            'pageSize' => 1000,
        ]);

        return $this->render('Person/shared.html.twig', [
            'pageTitle' => $this->get('translator')->trans('Common Artists of')
                . ' ' . implode(' and ', $names),
            'pagination' => $pagination,
        ]);
    }

    /* TODO: try to merge with inverse method in ExhibitionController */
    protected function findSimilar($entity)
    {
        $em = $this->getDoctrine()->getManager();
        $dbconn = $em->getConnection();

        // build all the ids
        $exhibitionIds = [];
        foreach ($entity->getExhibitions() as $exhibition) {
            if ($exhibition->getStatus() <> -1) {
                $exhibitionIds[] = $exhibition->getId();
            }
        }

        $numExhibitions = count($exhibitionIds);
        if (0 == $numExhibitions) {
            return [];
        }

        $querystr = "SELECT DISTINCT id_person, id_exhibition"
                  . " FROM ItemExhibition"
                  . " WHERE id_exhibition IN (" . join(', ', $exhibitionIds) . ')'
                  . " AND id_person <> " . $entity->getId()
                  . " ORDER BY id_person";

        $exhibitionsByPerson = [];
        $stmt = $dbconn->query($querystr);
        while ($row = $stmt->fetch()) {
            if (!array_key_exists($row['id_person'], $exhibitionsByPerson)) {
                $exhibitionsByPerson[$row['id_person']] = [];
            }
            $exhibitionsByPerson[$row['id_person']][] = $row['id_exhibition'];
        }

        $jaccardIndex = [];
        $personIds = array_keys($exhibitionsByPerson);
        if (count($personIds) > 0) {
            $querystr = "SELECT CONCAT(COALESCE(lastname, ''), ' ', COALESCE(firstname,lastname)) AS name, id_person, COUNT(DISTINCT id_exhibition) AS num_exhibitions"
                      . " FROM ItemExhibition"
                      . " LEFT OUTER JOIN Person ON ItemExhibition.id_person=Person.id"
                      . " WHERE id_person IN (" . join(', ', $personIds) . ')'
                      . " GROUP BY id_person";
            $stmt = $dbconn->query($querystr);
            while ($row = $stmt->fetch()) {
                $numShared = count($exhibitionsByPerson[$row['id_person']]);

                $jaccardIndex[$row['id_person']] = [
                    'name' => $row['name'],
                    'count' => $numShared,
                    'coefficient' =>
                          1.0
                          * $numShared // shared
                          /
                          ($row['num_exhibitions'] + $numExhibitions - $numShared),
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

    protected function findCatalogueEntries($person)
    {
        // get the catalogue entries by exhibition
        $qb = $this->getDoctrine()
                ->getManager()
                ->createQueryBuilder();

        $qb->select([
                'IE',
                'E.id',
            ])
            ->from('AppBundle:ItemExhibition', 'IE')
            ->innerJoin('IE.exhibition', 'E')
            ->where("IE.person = :person")
            ;

        $results = $qb->getQuery()
            ->setParameter('person', $person)
            ->getResult();

        $entriesByExhibition = [];
        foreach ($results as $result) {
            $catalogueEntry = $result[0];
            $exhibitionId = $result['id'];
            if (!array_key_exists($exhibitionId, $entriesByExhibition)) {
                $entriesByExhibition[$exhibitionId] = [];
            }
            $entriesByExhibition[$exhibitionId][] = $catalogueEntry;
        }

        // TODO: sort each exhibition by catalogueId

        return $entriesByExhibition;
    }

    /**
     * @Route("/person/coappearances/csv/{id}", requirements={"id" = "\d+"}, name="person-coappearances-csv")
     */
    public function detailActionCoappearances(Request $request, $id = null, $ulan = null, $gnd = null)
    {
        $routeName = 'person';
        $routeParams = [];

        $criteria = new \Doctrine\Common\Collections\Criteria();
        $personRepo = $this->getDoctrine()
            ->getRepository('AppBundle:Person');

        if (!empty($id)) {
            $routeParams = [ 'id' => $id ];
            $criteria->where($criteria->expr()->eq('id', $id));
        }
        else if (!empty($ulan)) {
            $routeName = 'person-by-ulan';
            $routeParams = [ 'ulan' => $ulan ];
            $criteria->where($criteria->expr()->eq('ulan', $ulan));
        }
        else if (!empty($gnd)) {
            $routeName = 'person-by-gnd';
            $routeParams = [ 'gnd' => $gnd ];
            $criteria->where($criteria->expr()->eq('gnd', $gnd));
        }

        $criteria->andWhere($criteria->expr()->neq('status', -1));

        $persons = $personRepo->matching($criteria);

        if (0 == count($persons)) {
            return $this->redirectToRoute('person-index');
        }

        $person = $persons[0];

        $locale = $request->getLocale();
        if (in_array($request->get('_route'), [ 'person-jsonld', 'person-by-ulan-json', 'person-by-gnd-jsonld' ])) {
            return new JsonLdResponse($person->jsonLdSerialize($locale));
        }

        $result = $this->findSimilar($person);
        $csvResult = [];

        foreach ($result as $key=>$value) {

            $person = $value;

            $innerArray = [];

            array_push($innerArray, $person['name'], $person['count'] );

            array_push($csvResult, $innerArray);
        }

        $response = new CSVResponse( $csvResult, 200, explode( ', ', 'Startdate, Enddate, Title, City, Venue, # of Cat. Entries, type' ) );
        $response->setFilename( "data.csv" );
        return $response;
    }

    /**
     * @Route("/person/exhibition/csv/{id}", requirements={"id" = "\d+"}, name="person-exhibition-csv")
     */
    public function detailActionExhibition(Request $request, $id = null, $ulan = null, $gnd = null)
    {
        $routeName = 'person';
        $routeParams = [];

        $criteria = new \Doctrine\Common\Collections\Criteria();
        $personRepo = $this->getDoctrine()
            ->getRepository('AppBundle:Person');

        if (!empty($id)) {
            $routeParams = [ 'id' => $id ];
            $criteria->where($criteria->expr()->eq('id', $id));
        }
        else if (!empty($ulan)) {
            $routeName = 'person-by-ulan';
            $routeParams = [ 'ulan' => $ulan ];
            $criteria->where($criteria->expr()->eq('ulan', $ulan));
        }
        else if (!empty($gnd)) {
            $routeName = 'person-by-gnd';
            $routeParams = [ 'gnd' => $gnd ];
            $criteria->where($criteria->expr()->eq('gnd', $gnd));
        }

        $criteria->andWhere($criteria->expr()->neq('status', -1));

        $persons = $personRepo->matching($criteria);

        if (0 == count($persons)) {
            return $this->redirectToRoute('person-index');
        }

        $person = $persons[0];

        $locale = $request->getLocale();
        if (in_array($request->get('_route'), [ 'person-jsonld', 'person-by-ulan-json', 'person-by-gnd-jsonld' ])) {
            return new JsonLdResponse($person->jsonLdSerialize($locale));
        }

        $result = $person->getExhibitions();

        $csvResult = [];

        $catalogueEntries = $this->findCatalogueEntries($person);

        foreach ($result as $key=>$value) {

            $exhibition = $value;

            $innerArray = [];

            array_push($innerArray, $exhibition->getStartdate(), $exhibition->getTitle(), /*$exhibition->getLocation()->getPlaceLabel(),*/ count($catalogueEntries[$exhibition->getId()]), $exhibition->getOrganizerType() );

            array_push($csvResult, $innerArray);
        }

        $response = new CSVResponse( $csvResult, 200, explode( ', ', 'Startdate, Enddate, Title, City, Venue, # of Cat. Entries, type' ) );
        $response->setFilename( "data.csv" );
        return $response;
    }

    /**
     * @Route("/person/ulan/{ulan}", requirements={"ulan" = "[0-9]+"}, name="person-by-ulan")
     * @Route("/person/gnd/{gnd}", requirements={"gnd" = "[0-9xX]+"}, name="person-by-gnd")
     * @Route("/person/{id}", requirements={"id" = "\d+"}, name="person")
     */
    public function detailAction(Request $request, $id = null, $ulan = null, $gnd = null)
    {
        $routeName = 'person';
        $routeParams = [];

        $criteria = new \Doctrine\Common\Collections\Criteria();
        $personRepo = $this->getDoctrine()
                ->getRepository('AppBundle:Person');

        if (!empty($id)) {
            $routeParams = [ 'id' => $id ];
            $criteria->where($criteria->expr()->eq('id', $id));
        }
        else if (!empty($ulan)) {
            $routeName = 'person-by-ulan';
            $routeParams = [ 'ulan' => $ulan ];
            $criteria->where($criteria->expr()->eq('ulan', $ulan));
        }
        else if (!empty($gnd)) {
            $routeName = 'person-by-gnd';
            $routeParams = [ 'gnd' => $gnd ];
            $criteria->where($criteria->expr()->eq('gnd', $gnd));
        }

        $criteria->andWhere($criteria->expr()->neq('status', -1));

        $persons = $personRepo->matching($criteria);

        if (0 == count($persons)) {
            return $this->redirectToRoute('person-index');
        }

        $person = $persons[0];

        $locale = $request->getLocale();
        if (in_array($request->get('_route'), [ 'person-jsonld', 'person-by-ulan-json', 'person-by-gnd-jsonld' ])) {
            return new JsonLdResponse($person->jsonLdSerialize($locale));
        }

        $dataNumberOfExhibitionsPerYear = $this->detailDataNumberOfExhibitionsPerYear($person);

        $dataNumberOfExhibitionsPerCity = $this->detailDataNumberOfExhibitionsPerCity($person);

        $dataNumberOfExhibitionsPerCountry = $this->detailDataNumberOfExhibitionsPerCountry($person);

        $dataNumberOfWorksPerYear = $this->detailDataNumberOfWorksPerYear($person, $this->findCatalogueEntries($person));

        $dataNumberOfWorksPerType = $this->detailDataNumberOfWorksPerType($this->findCatalogueEntries($person));

        $dataNumberOfExhibitionsPerOrgBody = $this->detailDataNumberOfExhibitionsPerOrgBody($person);



        return $this->render('Person/detail.html.twig', [
            'pageTitle' => $person->getFullname(true), // TODO: lifespan in brackets
            'person' => $person,
            'showWorks' => !empty($_SESSION['user']),
            'catalogueEntries' => $this->findCatalogueEntries($person),
            'similar' => $this->findSimilar($person),
            'currentPageId' => $id,
            'dataNumberOfExhibitionsPerYear' => $dataNumberOfExhibitionsPerYear,
            'dataNumberOfExhibitionsPerCity' => $dataNumberOfExhibitionsPerCity,
            'dataNumberOfExhibitionsPerCountry' => $dataNumberOfExhibitionsPerCountry,
            'dataNumberOfWorksPerYear' => $dataNumberOfWorksPerYear,
            'dataNumberOfWorksPerType' => $dataNumberOfWorksPerType,
            'dataNumberOfExhibitionsPerOrgBody' => $dataNumberOfExhibitionsPerOrgBody,
            'pageMeta' => [
                'jsonLd' => $person->jsonLdSerialize($locale),
                'og' => $this->buildOg($person, $routeName, $routeParams),
                'twitter' => $this->buildTwitter($person, $routeName, $routeParams),
            ],
        ]);
    }


    public function detailDataNumberOfWorksPerType($catalogueEntries){

        $works = [];


        foreach ($catalogueEntries as $catalogueEntry){

            $currExhibition = $catalogueEntry[0]->exhibition;

            foreach ($catalogueEntry as $entry){
                if($entry->type) {
                    $currType = $entry->type->getName();
                    array_push($works, (string)$currType == '0_unknown' ? 'unknown' : $currType);
                }
            }

        }

        $worksTotalPerYear = array_count_values ( $works );

        //$exhibitionPlacesArray = array_keys($exhibitionPlaces);


        $typesOnly = array_keys($worksTotalPerYear) ;
        $valuesOnly =  array_values( $worksTotalPerYear );


        $sumOfAllWorks = array_sum(array_values($worksTotalPerYear));

        $i = 0;
        $finalDataJson = '[';

        foreach ($typesOnly as $type){

            $i > 0 ? $finalDataJson .= ", " : "";

            $numberOfWorks = $valuesOnly[$i] ;

            $finalDataJson .= "{ name: '${type}', y: ${numberOfWorks} } ";
            $i += 1;
        }
        $finalDataJson .= ']';



        $returnArray = [$finalDataJson, $sumOfAllWorks];


        return $returnArray;
    }

    public function detailDataNumberOfWorksPerYear($person, $catalogueEntries){

        $works = [];


        foreach ($catalogueEntries as $catalogueEntry){

            $currExhibition = $catalogueEntry[0]->exhibition;


            $currExhibitionYear =  (int) date('Y', strtotime($currExhibition->getStartDate())) ;


            foreach ($catalogueEntry as $entry){
                array_push($works, (string) $currExhibitionYear );
            }

        }

        $worksTotalPerYear = array_count_values ( $works );

        //$exhibitionPlacesArray = array_keys($exhibitionPlaces);


        $yearsArray = array_keys($worksTotalPerYear);

        if(!empty($yearsArray)) {
            $min = $yearsArray[0];
            $max = max(array_keys($worksTotalPerYear));
        }else{
            $min = 0;
            $max = 0;
        }

        $arrayWithoutGaps = [];

        // create an array without any year gaps inbetween
        for ($i = $min; $i <= $max; $i++) {
            $arrayWithoutGaps[(string)$i] = array_key_exists($i, $worksTotalPerYear) ? $worksTotalPerYear[$i] : 0;
        }



        $yearsOnly = json_encode( array_keys($arrayWithoutGaps) );
        $valuesOnly = json_encode ( array_values($arrayWithoutGaps) );
        $sumOfAllExhibitions = array_sum(array_values($arrayWithoutGaps));
        $yearActive = $max - $min > 0 ? $max - $min : 1;


        $averagePerYear = round( $sumOfAllExhibitions / $yearActive, 1 );

        //return $worksPerYear;




        $returnArray = [$yearsOnly, $valuesOnly, $sumOfAllExhibitions, $yearActive, $averagePerYear];

        return $returnArray;
    }


    public function detailDataNumberOfExhibitionsPerOrgBody($person){

        $exhibitionPlaces = [];


        $exhibitions = $person->exhibitions;

        foreach ($exhibitions as $exhibition){

            // print_r( $exhibition->getLocation()->getPlace()->getCountryCode() );

            //print date('Y', strtotime($exhibition->getStartDate())) ;


            array_push($exhibitionPlaces, (string) $exhibition->getOrganizerType() );
        }



        $exhibitionPlacesTotal = array_count_values ( $exhibitionPlaces );

        //$exhibitionPlacesArray = array_keys($exhibitionPlaces);

        // print_r($exhibitionPlacesArray);

        $placesOnly = ( array_keys($exhibitionPlacesTotal) );
        $valuesOnly =  array_values( $exhibitionPlacesTotal );


        $sumOfAllExhibitions = array_sum(array_values($exhibitionPlacesTotal));

        $i = 0;
        $finalDataJson = '[';

        foreach ($placesOnly as $place){

            $i > 0 ? $finalDataJson .= ", " : "";

            $numberOfExhibitions = $valuesOnly[$i] ;

            $currPlace = $place == '' ? 'unknown' : $place;

            $finalDataJson .= "{ name: '${currPlace}', y: ${numberOfExhibitions} } ";
            $i += 1;
        }
        $finalDataJson .= ']';



        $returnArray = [$finalDataJson, $sumOfAllExhibitions];


        return $returnArray;
    }

    public function detailDataNumberOfExhibitionsPerCountry($person){

        $exhibitionPlaces = [];


        $exhibitions = $person->exhibitions;

        foreach ($exhibitions as $exhibition){

            // print_r( $exhibition->getLocation()->getPlace()->getCountryCode() );

            //print date('Y', strtotime($exhibition->getStartDate())) ;


            array_push($exhibitionPlaces, (string) $exhibition->getLocation()->getPlace()->getCountryCode() );
        }



        $exhibitionPlacesTotal = array_count_values ( $exhibitionPlaces );

        //$exhibitionPlacesArray = array_keys($exhibitionPlaces);

        // print_r($exhibitionPlacesArray);

        $placesOnly = ( array_keys($exhibitionPlacesTotal) );
        $valuesOnly =  array_values( $exhibitionPlacesTotal );


        $sumOfAllExhibitions = array_sum(array_values($exhibitionPlacesTotal));

        $i = 0;
        $finalDataJson = '[';

        foreach ($placesOnly as $place){

            $i > 0 ? $finalDataJson .= ", " : "";

            $numberOfExhibitions = $valuesOnly[$i] ;

            $finalDataJson .= "{ name: '${place}', y: ${numberOfExhibitions} } ";
            $i += 1;
        }
        $finalDataJson .= ']';



        $returnArray = [$finalDataJson, $sumOfAllExhibitions, $i];


        return $returnArray;
    }


    public function detailDataNumberOfExhibitionsPerCity($person){

        $exhibitionPlaces = [];


        $exhibitions = $person->exhibitions;

        foreach ($exhibitions as $exhibition){

            // print_r( $exhibition->getLocation()->getPlaceLabel() );

            //print date('Y', strtotime($exhibition->getStartDate())) ;


            array_push($exhibitionPlaces, (string) $exhibition->getLocation()->getPlaceLabel() );
        }



        $exhibitionPlacesTotal = array_count_values ( $exhibitionPlaces );

        //$exhibitionPlacesArray = array_keys($exhibitionPlaces);

        // print_r($exhibitionPlacesArray);

        $placesOnly = ( array_keys($exhibitionPlacesTotal) );
        $valuesOnly =  array_values( $exhibitionPlacesTotal );


        $sumOfAllExhibitions = array_sum(array_values($exhibitionPlacesTotal));

        $i = 0;
        $finalDataJson = '[';

        foreach ($placesOnly as $place){

            $i > 0 ? $finalDataJson .= ", " : "";

            $numberOfExhibitions = $valuesOnly[$i] ;

            $finalDataJson .= "{ name: '${place}', y: ${numberOfExhibitions} } ";
            $i += 1;
        }
        $finalDataJson .= ']';



        $returnArray = [$finalDataJson, $sumOfAllExhibitions, $i];


        return $returnArray;
    }


    public function detailDataNumberOfExhibitionsPerYear($person){

        $exhibitionYear = [];


        $exhibitions = $person->exhibitions;

        foreach ($exhibitions as $exhibition){

            //print date('Y', strtotime($exhibition->getStartDate())) ;


            array_push($exhibitionYear, (int) date('Y', strtotime($exhibition->getStartDate())) );
        }

        $exhibitionYear = array_count_values ( $exhibitionYear );


        $yearsArray = array_keys($exhibitionYear);

        if(!empty($yearsArray)){
            $min = $yearsArray[0];
            $max = $yearsArray[ (count($exhibitionYear)-1) ];
        }else{
            $min = 0;
            $max = 0;
        }


        $arrayWithoutGaps = [];

        // create an array without any year gaps inbetween
        for ($i = $min; $i <= $max; $i++) {
            $arrayWithoutGaps[(string)$i] = array_key_exists($i, $exhibitionYear) ? $exhibitionYear[$i] : 0;
        }



        $yearsOnly = json_encode( array_keys($arrayWithoutGaps) );
        $valuesOnly = json_encode ( array_values($arrayWithoutGaps) );
        $sumOfAllExhibitions = array_sum(array_values($arrayWithoutGaps));
        $yearActive = $max - $min > 0 ? $max - $min : 1;
        $averagePerYear = round( $sumOfAllExhibitions / $yearActive, 1 );





        $returnArray = [$yearsOnly, $valuesOnly, $sumOfAllExhibitions, $yearActive, $averagePerYear];

        return $returnArray;
    }



    /*
     * TODO: mode=ulan
     */
    public function beaconAction($mode = 'gnd')
    {
        $translator = $this->container->get('translator');
        $twig = $this->container->get('twig');

        $personRepo = $this->getDoctrine()
                ->getRepository('AppBundle:Person');

        $query = $personRepo
                ->createQueryBuilder('P')
                ->where('P.status >= 0')
                ->andWhere('P.gnd IS NOT NULL')
                ->orderBy('P.gnd')
                ->getQuery()
                ;

        $persons = $query->execute();

        $ret = '#FORMAT: BEACON' . "\n"
             . '#PREFIX: http://d-nb.info/gnd/'
             . "\n";
        $ret .= sprintf('#TARGET: %s/gnd/{ID}',
                        $this->generateUrl('person-index', [], true))
              . "\n";

        $globals = $twig->getGlobals();
        $ret .= '#NAME: ' . $translator->trans($globals['siteName'])
              . "\n";
        // $ret .= '#MESSAGE: ' . "\n";

        foreach ($persons as $person) {
            $ret .=  $person->getGnd() . "\n";
        }

        return new \Symfony\Component\HttpFoundation\Response($ret, \Symfony\Component\HttpFoundation\Response::HTTP_OK,
                                                              [ 'Content-Type' => 'text/plain; charset=UTF-8' ]);
    }


    public function statsActionDetail($id)
    {
        $repo = $this->getDoctrine()
            ->getRepository('AppBundle:Person');

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
        ]);

        // display the static content
        return $this->render('Person/stats-detail.html.twig', [
            'chart' => implode("\n", $charts),
        ]);

    }
}
