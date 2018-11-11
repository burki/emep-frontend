<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

use AppBundle\Utils\CsvResponse;

/**
 *
 */
class LocationController
extends CrudController
{
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
     * @Route("/location/csv", name="location-csv")
     */
    public function indexToCsvAction(Request $request)
    {
        $route = $request->get('_route');

        $qb = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();

        $qb->select([
            'L',
            "CONCAT(COALESCE(C.name, P.countryCode), COALESCE(P.alternateName,P.name),L.name) HIDDEN countryPlaceNameSort",
            "L.name HIDDEN nameSort",
            'COUNT(DISTINCT E.id) AS numExhibitionSort',
            'COUNT(DISTINCT IE.id) AS numCatEntrySort',
        ])
            ->from('AppBundle:Location', 'L')
            ->leftJoin('L.place', 'P')
            ->leftJoin('P.country', 'C')
            ->leftJoin('AppBundle:Exhibition', 'E',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'E.location = L AND E.status <> -1')
            ->leftJoin('AppBundle:ItemExhibition', 'IE',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'IE.exhibition = E AND IE.title IS NOT NULL')
            ->where('L.status <> -1 AND 0 = BIT_AND(L.flags, 256)')
            ->groupBy('L.id')
            ->orderBy('countryPlaceNameSort')
        ;

        $types = $this->buildVenueTypes();
        $form = $this->get('form.factory')->create(\AppBundle\Filter\LocationFilterType::class, [
            'country_choices' => array_flip($this->buildCountries()),
            'location_type_choices' => array_combine($types, $types),
            'ids' => range(0, 9999)
        ]);

        if ($request->query->has($form->getName())) {
            // manually bind values from the request
            $form->submit($request->query->get($form->getName()));
            // build the query from the given form object
            $this->get('lexik_form_filter.query_builder_updater')->addFilterConditions($form, $qb);
        }

        $pagination = $this->buildPagination($request, $qb->getQuery(), [
            // the following leads to wrong display in combination with our
            // helper.pagination_sortable()
            // 'defaultSortFieldName' => 'countryPlaceNameSort', 'defaultSortDirection' => 'asc',
        ]);

        $result = $qb->getQuery()->getResult();

        $csvResult = [];

        foreach ($result as $value) {
            $innerArray = [];

            // echo ('  |   ');


            // print_r( $value[0]->getStartdate() );
            // echo '  |   ';
            // print_r( $value[0]->getEnddate() );
            // echo '  |   ';
            // print_r( $value[0]->title );
            // echo '  |   ';

            $location = $value[0];
            $locationLabel = '';
            $locationName = '';

            if($location){
                $locationLabel = $value[0]->getPlaceLabel();
                $locationName = $value[0]->getName();
            }

            // print_r( $locationLabel );
            // echo '  |   ';
            // print_r( $locationName );
            // echo '  |   ';
            // print_r( $value['numCatEntrySort'] );
            // echo '  |   ';
            // print_r( $value[0]->getOrganizerType() );
            // echo '  |   ';

            array_push($innerArray, $locationName, $locationLabel, count($value[0]->getExhibitions()), $value['numCatEntrySort'] );


            array_push($csvResult, $innerArray);
        }

        // print_r($csvResult);

        /* END DATATABLE CREATION LIKE FRONTEND */


        $response = new CSVResponse( $csvResult, 200, explode( ', ', 'Startdate, Enddate, Title, City, Venue, # of Cat. Entries, type' ) );
        $response->setFilename( "data.csv" );
        return $response;
    }

    /**
     * @Route("/location", name="location-index")
     */
    public function indexAction(Request $request)
    {
        $route = $request->get('_route');

        $qb = $this->getDoctrine()
                ->getManager()
                ->createQueryBuilder();

        $qb->select([
                'L',
                "CONCAT(COALESCE(C.name, P.countryCode), COALESCE(P.alternateName,P.name),L.name) HIDDEN countryPlaceNameSort",
                "L.name HIDDEN nameSort",
                'COUNT(DISTINCT E.id) AS numExhibitionSort',
                'COUNT(DISTINCT IE.id) AS numCatEntrySort',
                "P.name HIDDEN placeSort",
                "L.type HIDDEN typeSort"
            ])
            ->from('AppBundle:Location', 'L')
            ->leftJoin('L.place', 'P')
            ->leftJoin('P.country', 'C')
            ->leftJoin('AppBundle:Exhibition', 'E',
                       \Doctrine\ORM\Query\Expr\Join::WITH,
                       'E.location = L AND E.status <> -1')
            ->leftJoin('AppBundle:ItemExhibition', 'IE',
                       \Doctrine\ORM\Query\Expr\Join::WITH,
                       'IE.exhibition = E AND IE.title IS NOT NULL')
            ->where('L.status <> -1 AND 0 = BIT_AND(L.flags, 256)')
            ->groupBy('L.id')
            ->orderBy('countryPlaceNameSort')
            ;

        $types = $this->buildVenueTypes();
        $form = $this->get('form.factory')->create(\AppBundle\Filter\LocationFilterType::class, [
            'country_choices' => array_flip($this->buildCountries()),
            'location_type_choices' => array_combine($types, $types),
            'ids' => range(0, 9999)
        ]);

        if ($request->query->has($form->getName())) {
            // manually bind values from the request
            $form->submit($request->query->get($form->getName()));
            // build the query from the given form object
            $this->get('lexik_form_filter.query_builder_updater')->addFilterConditions($form, $qb);
        }

        $pagination = $this->buildPagination($request, $qb->getQuery(), [
            // the following leads to wrong display in combination with our
            // helper.pagination_sortable()
            // 'defaultSortFieldName' => 'countryPlaceNameSort', 'defaultSortDirection' => 'asc',
        ]);

        $locations = $qb->getQuery()->getResult();

        $countries = $form->get('country')->getData();
        $locationType = $form->get('location_type')->getData();
        $stringQuery = $form->get('search')->getData();
        $ids = $form->get('id')->getData();

        $indexDataNumberVenueType = $this->indexDataNumberVenueType($locations);
        $indexDataNumberCountries = $this->indexDataNumberCountries($locations);

        return $this->render('Location/index.html.twig', [
            'pageTitle' => $this->get('translator')->trans('Venues'),
            'pagination' => $pagination,
            'form' => $form->createView(),
            'countryArray' => $this->buildCountries(),
            'organizerTypesArray' => $types,
            'countries' => $countries,
            'ids' => $ids,
            'locationType' => $locationType,
            'stringPart' => $stringQuery,
            'locations' => $locations,
            'indexDataNumberVenueType' => $indexDataNumberVenueType,
            'indexDataNumberCountries' => $indexDataNumberCountries
        ]);
    }


    public function indexDataNumberCountries($locations)
    {
        //$exhibitions = $location->getExhibitions();

        $venueTypes = [];

        foreach ($locations as $location) {

            //print count($entries);
            //print '   ';

            $currType = (string) $location[0]->getCountry();

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

        foreach ($typesOnly as $place){

            $i > 0 ? $finalDataJson .= ", " : "";

            $numberOfExhibitions = $valuesOnly[$i] ;

            $finalDataJson .= '{ name: "' .$place. '", y: '. $numberOfExhibitions . '} ';
            $i += 1;
        }
        $finalDataJson .= ']';



        $returnArray = [$finalDataJson, $sumOfAllTypes];


        return $returnArray;
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

        foreach ($typesOnly as $place){

            $i > 0 ? $finalDataJson .= ", " : "";

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

        foreach ($result as $key=>$value) {

            $exhibition = $value;

            $innerArray = [];
            array_push($innerArray, $exhibition->getStartdate(), $exhibition->getEnddate(), $exhibition->location->getPlaceLabel(), $exhibitionStats[$exhibition->getID()]->numCatEntrySort );

            array_push($csvResult, $innerArray);
        }

        $response = new CSVResponse( $csvResult, 200, explode( ', ', 'Startdate, Enddate, Title, City, Venue, # of Cat. Entries, type' ) );
        $response->setFilename( "data.csv" );
        return $response;
    }

    /**
     * @Route("/location/{id}", requirements={"id" = "\d+"}, name="location")
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

            //print count($entries);
            //print '   ';
            foreach ($entries as $entry){
                if($entry->type){
                    //print_r($entry->type->getName());
                    $currType = $entry->type->getName();
                    array_push($types, (string) $currType == '0_unknown' ? 'unknown' : $currType );
                }
                //print_r($entry);
            };
        }

        $typesTotal = array_count_values ( $types );

        //$exhibitionPlacesArray = array_keys($exhibitionPlaces);

        // print_r($exhibitionPlacesArray);

        $typesOnly = ( array_keys($typesTotal) );
        $valuesOnly =  array_values( $typesTotal );


        $sumOfAllTypes= array_sum(array_values($typesTotal));

        $i = 0;
        $finalDataJson = '[';

        foreach ($typesOnly as $place){

            $i > 0 ? $finalDataJson .= ", " : "";

            $numberOfExhibitions = $valuesOnly[$i] ;

            $finalDataJson .= "{ name: '${place}', y: ${numberOfExhibitions} } ";
            $i += 1;
        }
        $finalDataJson .= ']';



        $returnArray = [$finalDataJson, $sumOfAllTypes];


        return $returnArray;
    }




    public function detailDataNumberOfArtistsPerCountry($artists){

        $artistNationalities = [];



        foreach ($artists as $artist){

            // print_r( $exhibition->getLocation()->getPlace()->getCountryCode() );

            //print date('Y', strtotime($exhibition->getStartDate())) ;


            array_push($artistNationalities, (string) $artist[0]->getNationality() );
        }



        $artistNationalitiesTotal = array_count_values ( $artistNationalities );

        //$exhibitionPlacesArray = array_keys($exhibitionPlaces);

        // print_r($exhibitionPlacesArray);

        $nationalitiesOnly = ( array_keys($artistNationalitiesTotal) );
        $valuesOnly =  array_values( $artistNationalitiesTotal );


        $sumOfAllNationalities = array_sum(array_values($artistNationalitiesTotal));

        $i = 0;
        $finalDataJson = '[';

        foreach ($nationalitiesOnly as $place){

            $i > 0 ? $finalDataJson .= ", " : "";

            $numberOfExhibitions = $valuesOnly[$i] ;

            $finalDataJson .= "{ name: '${place}', y: ${numberOfExhibitions} } ";
            $i += 1;
        }
        $finalDataJson .= ']';



        $returnArray = [$finalDataJson, $sumOfAllNationalities];


        return $returnArray;
    }
}
