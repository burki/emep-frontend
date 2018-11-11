<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

use AppBundle\Utils\CsvResponse;


/**
 *
 */
class HolderController
extends CrudController
{
    use SharingBuilderTrait;

    protected function buildCountries()
    {
        $qb = $this->getDoctrine()
                ->getManager()
                ->createQueryBuilder();

        $qb->select([
                'H.countryCode',
            ])
            ->distinct()
            ->from('AppBundle:Holder', 'H')
            ->where('H.status <> -1 AND H.countryCode IS NOT NULL')
            ;

        return $this->buildActiveCountries($qb);
    }

    /**
     * @Route("/holder/csv", name="holder-csv")
     */
    public function indexToCsvAction(Request $request)
    {
        $route = $request->get('_route');

        $qb = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();

        $qb->select([
            'H',
            "CONCAT(COALESCE(H.countryCode, ''),H.placeLabel,H.name) HIDDEN countryPlaceNameSort",
            "H.name HIDDEN nameSort",
            'COUNT(DISTINCT BH.bibitem) AS numBibitemSort',
        ])
            ->from('AppBundle:Holder', 'H')
            ->leftJoin('AppBundle:BibitemHolder', 'BH',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'BH.holder = H')
            ->where('H.status <> -1')
            ->groupBy('H.id')
            ->orderBy('nameSort')
        ;

        $form = $this->get('form.factory')->create(\AppBundle\Filter\HolderFilterType::class, [
            'country_choices' => array_flip($this->buildCountries()),
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

        $countries = $form->get('country')->getData();
        $stringQuery = $form->get('search')->getData();
        $ids = $form->get('id')->getData();



        $result = $qb->getQuery()->execute();

        $csvResult = [];

        foreach ($result as $value) {
            $innerArray = [];

            $entry = $value[0];



            array_push($innerArray, $entry->person->getFullname(true), $value['numBibitemSort'] );

            array_push($csvResult, $innerArray);
        }

        $response = new CSVResponse( $csvResult, 200, explode( ', ', 'Startdate, Enddate, Title, City, Venue, # of Cat. Entries, type' ) );
        $response->setFilename( "data.csv" );
        return $response;
    }


    /**
     * @Route("/holder", name="holder-index")
     */
    public function indexAction(Request $request)
    {
        $route = $request->get('_route');

        $qb = $this->getDoctrine()
                ->getManager()
                ->createQueryBuilder();

        $qb->select([
                'H',
                "CONCAT(COALESCE(H.countryCode, ''),H.placeLabel,H.name) HIDDEN countryPlaceNameSort",
                "H.name HIDDEN nameSort",
                'COUNT(DISTINCT BH.bibitem) AS numBibitemSort',
            ])
            ->from('AppBundle:Holder', 'H')
            ->leftJoin('AppBundle:BibitemHolder', 'BH',
                       \Doctrine\ORM\Query\Expr\Join::WITH,
                       'BH.holder = H')
            ->where('H.status <> -1')
            ->groupBy('H.id')
            ->orderBy('nameSort')
            ;

        $form = $this->get('form.factory')->create(\AppBundle\Filter\HolderFilterType::class, [
            'country_choices' => array_flip($this->buildCountries()),
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

        $countries = $form->get('country')->getData();
        $stringQuery = $form->get('search')->getData();
        $ids = $form->get('id')->getData();


        // $holders = $qb->getQuery()->getResult();

        return $this->render('Holder/index.html.twig', [
            'pageTitle' => $this->get('translator')->trans('Holding Institutions'),
            'pagination' => $pagination,
            'countryArray' => $this->buildCountries(),
            'countries' => $countries,
            'stringPart' => $stringQuery,
            'ids' => $ids,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/holder/catalogues/csv/{id}", requirements={"id" = "\d+"}, name="holder-catalogue-csv")
     */
    public function detailActionCatalogueCSV(Request $request, $id = null, $ulan = null, $gnd = null)
    {
        $routeName = $request->get('_route'); $routeParams = [];

        $repo = $this->getDoctrine()
            ->getRepository('AppBundle:Holder');

        if (!empty($id)) {
            $routeParams = [ 'id' => $id ];
            $holder = $repo->findOneById($id);
        }

        if (!isset($holder) || $holder->getStatus() == -1) {
            return $this->redirectToRoute('holder-index');
        }

        $locale = $request->getLocale();
        if (in_array($request->get('_route'), [ 'location-jsonld' ])) {
            return new JsonLdResponse($person->jsonLdSerialize($locale));
        }

        $citeProc = $this->instantiateCiteProc($request->getLocale());

        $result = $holder->findBibitems($this->getDoctrine()->getManager());

        $csvResult = [];

        foreach ($result as $key=>$value) {

            $bibitem = $value[0];

            $innerArray = [];



            array_push($innerArray, $bibitem->getTitle(), $bibitem->getPublicationLocation(), $bibitem->getDatePublished());

            array_push($csvResult, $innerArray);
        }

        $response = new CSVResponse( $csvResult, 200, explode( ', ', 'Startdate, Enddate, Title, City, Venue, # of Cat. Entries, type' ) );
        $response->setFilename( "data.csv" );
        return $response;
    }

    /**
     * @Route("/holder/{id}", requirements={"id" = "\d+"}, name="holder")
     */
    public function detailAction(Request $request, $id = null, $ulan = null, $gnd = null)
    {
        $routeName = $request->get('_route'); $routeParams = [];

        $repo = $this->getDoctrine()
                ->getRepository('AppBundle:Holder');

        if (!empty($id)) {
            $routeParams = [ 'id' => $id ];
            $holder = $repo->findOneById($id);
        }

        if (!isset($holder) || $holder->getStatus() == -1) {
            return $this->redirectToRoute('holder-index');
        }

        $locale = $request->getLocale();
        if (in_array($request->get('_route'), [ 'location-jsonld' ])) {
            return new JsonLdResponse($person->jsonLdSerialize($locale));
        }

        $citeProc = $this->instantiateCiteProc($request->getLocale());


        $qb = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();

        $qb->select([
            'H',
            "H.placeLabel ",
            "P.latitude",
            "P.longitude"
        ])
            ->from('AppBundle:Holder', 'H')
            ->leftJoin('AppBundle:Place', 'P',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'P.name = H.placeLabel')
            ->where('H.id = ' . $id)
        ;

        $place = ($qb->getQuery()->execute());

        $bibitems = $holder->findBibitems($this->getDoctrine()->getManager());

        $dataNumberOfItemType = $this->detailDataNumberOfItemType($bibitems);


        return $this->render('Holder/detail.html.twig', [
            'place' => $place[0],
            'pageTitle' => $holder->getName(),
            'holder' => $holder,
            'bibitems' => $bibitems,
            'citeProc' => $citeProc,
            'dataNumberOfItemType' => $dataNumberOfItemType,
            'pageMeta' => [
                /*
                'jsonLd' => $exhibition->jsonLdSerialize($locale),
                'og' => $this->buildOg($exhibition, $routeName, $routeParams),
                'twitter' => $this->buildTwitter($exhibition, $routeName, $routeParams),
                */
            ],
        ]);
    }

    public function detailDataNumberOfItemType($bibitems){

        $exhibitionPlaces = [];
        // publicationLocation


        foreach ($bibitems as $bibi){

            // print_r( $exhibition->getLocation()->getPlace()->getCountryCode() );

            //print date('Y', strtotime($exhibition->getStartDate())) ;


            array_push($exhibitionPlaces, (string) $bibi[0]->getItemType() );
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



        $returnArray = [$finalDataJson, $sumOfAllExhibitions];


        return $returnArray;
    }



}
