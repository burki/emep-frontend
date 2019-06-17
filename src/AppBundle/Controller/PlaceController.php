<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Intl\Intl;

/**
 *
 */
class PlaceController
extends CrudController
{
    protected $pageSize = 200;

    protected function buildCountries()
    {
        $qb = $this->getDoctrine()
                ->getManager()
                ->createQueryBuilder();

        $qb->select([
                'P.countryCode',
            ])
            ->distinct()
            ->from('AppBundle:Place', 'P')
            ->where("P.type IN ('inhabited places') AND P.countryCode IS NOT NULL")
            ;

        $countriesActive = [];

        foreach ($qb->getQuery()->getResult() as $result) {
            $countryCode = $result['countryCode'];
            $countriesActive[$countryCode] = Intl::getRegionBundle()->getCountryName($countryCode);
        }

        asort($countriesActive);

        return $countriesActive;
    }

    /**
     * @Route("/place", name="place-inhabited")
     */
    public function indexAction(Request $request)
    {
        $qb = $this->getDoctrine()
                ->getManager()
                ->createQueryBuilder();

        $qb->select([
                'P', 'C',
                'COUNT(DISTINCT E.id) AS numExhibitionSort',
                'COUNT(DISTINCT IE.id) AS numCatEntrySort',
                "COALESCE(P.alternateName,P.name) HIDDEN nameSort",
                "CONCAT(COALESCE(C.name, P.countryCode), COALESCE(P.alternateName,P.name)) HIDDEN countrySort",
            ])
            ->from('AppBundle:Place', 'P')
            ->leftJoin('P.country', 'C')
            ->leftJoin('AppBundle:Location', 'L',
                       \Doctrine\ORM\Query\Expr\Join::WITH,
                       'L.place = P')
            ->leftJoin('AppBundle:Exhibition', 'E',
                       \Doctrine\ORM\Query\Expr\Join::WITH,
                       'E.location = L AND ' . \AppBundle\Utils\SearchListBuilder::exhibitionVisibleCondition('E'))
            ->leftJoin('AppBundle:ItemExhibition', 'IE',
                       \Doctrine\ORM\Query\Expr\Join::WITH,
                       'IE.exhibition = E AND (IE.title IS NOT NULL OR IE.item IS NULL)')
            ->where("P.type IN ('inhabited places')")
            ->groupBy('P.id')
            ->orderBy('nameSort')
            ;

        $form = $this->get('form.factory')->create(\AppBundle\Filter\PlaceFilterType::class, [
            'choices' => array_flip($this->buildCountries()),
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
            // 'defaultSortFieldName' => 'nameSort', 'defaultSortDirection' => 'asc',
        ]);

        return $this->render('Place/index.html.twig', [
            'pageTitle' => $this->get('translator')->trans('Places'),
            'pagination' => $pagination,
            'form' => $form->createView(),
        ]);
    }

    protected function buildSaveSearchParams(Request $request, UrlGeneratorInterface $urlGenerator)
    {
        $settings = $this->lookupSettingsFromRequest($request);
        $route = $settings['base'];

        $this->form = $this->createSearchForm($request, $urlGenerator);

        $listBuilder = $this->instantiateListBuilder($request, $urlGenerator, false, 'Place');
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
     * @Route("/place/save", name="place-save")
     */
    public function saveSearchAction(Request $request,
                                     UrlGeneratorInterface $urlGenerator,
                                     UserInterface $user)
    {
        return $this->handleSaveSearchAction($request, $urlGenerator, $user);
    }

    /**
     * @Route("/place/{id}", requirements={"id" = "\d+"}, name="place")
     * @Route("/place/tgn/{tgn}", requirements={"tgn" = "\d+"}, name="place-by-tgn")
     */
    public function detailAction(Request $request, $id = null, $tgn = null)
    {
        $placeRepo = $this->getDoctrine()
            ->getRepository('AppBundle:Place');

        if (!empty($id)) {
            $place = $placeRepo->findOneById($id);
        }
        else if (!empty($tgn)) {
            $place = $placeRepo->findOneByTgn($tgn);
        }

        if (!isset($place) /* || $place->getStatus() < 0 */) {
            return $this->redirectToRoute('place-index');
        }

        $locale = $request->getLocale();

        if (in_array($request->get('_route'), [ 'place-jsonld', 'place-by-tgn-jsonld' ])) {
            return new JsonLdResponse($place->jsonLdSerialize($locale));
        }

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
                'IE.exhibition = E AND (IE.title IS NOT NULL OR IE.item IS NULL)')
            ->leftJoin('IE.person', 'P')
            ->where('L.place = :place')
            ->andWhere(\AppBundle\Utils\SearchListBuilder::exhibitionVisibleCondition('E'))
            ->setParameter('place', $place)
            ->groupBy('E.id')
            ;

        $exhibitionStats = [];
        foreach ($qb->getQuery()->getResult() as $row) {
            $exhibitionStats[$row['id']] = $row;
        }

        $numberOfExhibitions = $this->getNumberOfExhibitions($id, $tgn, $place->getId());
        $numberOfVenues = $this->getNumberOfVenues($tgn);
        $numberofArtists = $this->getNumberOfArtists($tgn);

        $numberOfArtistsBorn = $this->getNumberArtistsBorn($tgn);
        $numberOfArtistsDied = $this->getNumberArtistsDied($tgn);
        $numberOfArtistsActive = $this->getNumberArtistsActive($tgn);

        $allArtists = $this->getAllArtists($tgn);

        $numberOfArtistsExhibitioned = $this->getNumberArtistsExhibitioned($tgn);

        $nationalitiesStats = $this->getNumberOfNationalities($tgn);

        $genderStats = $this->getGenderSplit($tgn);

        $exhibitionTypeStats = $this->getStatsExhibitionTypes($tgn);

        $exhibitionTypeStatisticsFormat = $this->assoc2NameYArray($exhibitionTypeStats);

        $genderStatsStatisticsFormat = $this->assoc2NameYArray($genderStats);

        $exhibitions = $this->getExhibitionsByTgn($tgn);

        $exhibitionsGroupedByYearStats = $this->getExhibitionsGroupedYearByTgn($tgn);

        $venuesList = $this->getVenuesList($tgn);

        return $this->render('Place/detail.html.twig', [
            'pageTitle' => $place->getNameLocalized($locale),
            'place' => $place,
            'persons' => $allArtists,
            'numberBorn' => $numberOfArtistsBorn,
            'numberDied' => $numberOfArtistsDied,
            'numberActive' => $numberOfArtistsActive,
            'numberVenues' => $numberOfVenues,
            'numberExhibitions' => $numberOfExhibitions,
            'exhibitionTypeStats' => $exhibitionTypeStats,
            'nationalitiesStats' => $nationalitiesStats,
            'genderStats' => $genderStats,
            'genderStatsStatisticsFormat' => $genderStatsStatisticsFormat,
            'exhibitionTypeStatisticsFormat' => $exhibitionTypeStatisticsFormat,
            'numberOfArtistsExhibitioned' => $numberOfArtistsExhibitioned,
            'exhibitions' => $exhibitions,
            'exhibitionsGroupedByYearStats' => $exhibitionsGroupedByYearStats,
            'venuesList' => $venuesList,
            'exhibitionStats' => $exhibitionStats,
            'em' => $this->getDoctrine()->getManager(),
            'pageMeta' => [
                'jsonLd' => $place->jsonLdSerialize($locale),
            ],
        ]);
    }

    public function getVenuesList($tgn)
    {
        $qb = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();

        $qb->select([
                'L AS location',
                'L.id AS id',
                'L.name AS name',
                'L.type AS type',
            ])
            ->from('AppBundle:Location', 'L')
            ->leftJoin('AppBundle:Exhibition', 'E',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'E.location = L AND ' . \AppBundle\Utils\SearchListBuilder::exhibitionVisibleCondition('E'))
            ->leftJoin('AppBundle:ItemExhibition', 'IE',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'IE.exhibition = E AND (IE.title IS NOT NULL OR IE.item IS NULL)')
            ->leftJoin('IE.person', 'P')
            ->where('L.place = :tgn')
            ->andWhere('L.status <> -1 AND 0 = BIT_AND(L.flags, 256)')
            ->setParameter('tgn', $tgn)
            ->groupBy('E.id')
            ->groupBy('L.id')
            ;

        $venues = [];
        foreach ($qb->getQuery()->getResult() as $venue) {
            $venueInfo = $venue + [
                'numArtists' => $this->getNumberOfArtistsByVenueId($venue['id']),
                'numNationalities' => $this->getNumberOfNationalitiesByVenueId($venue['id']),
                'numItems' => $this->getTotalNumberOfWorksByVenueId($venue['id']),
                'numExhibitions' => $this->getNumberOfExhibitionsByVenueId($venue['id']),
                'exhibition_types' => [],
            ];

            foreach ($this->getTypesAndNumberOfExhibitionsByVenueId($venue['id']) as $type => $num) {
                $venueInfo['exhibition_types'][$type] = $num;
            }

            $venues[] = $venueInfo;
        }

        return $venues;
    }


    /**
     *
     * VENUE QUERIES
     *
     * @param $id
     * @return int
     *
     */
    public function getTotalNumberOfWorksByVenueId($id)
    {
        $qb = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();

        $qb->select([
                'COUNT (DISTINCT IE.id) as numItems',
                // 'COUNT(DISTINCT E.id) AS numExhibitionSort',
                // 'COUNT(DISTINCT IE.id) AS numCatEntrySort',
            ])
            ->from('AppBundle:Person', 'P')
            ->innerJoin('AppBundle:ItemExhibition', 'IE',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'IE.person = P AND (IE.title IS NOT NULL OR IE.item IS NULL)')
            ->innerJoin('IE.exhibition', 'E')
            ->where('E.location = :location')
            ->andWhere(\AppBundle\Utils\SearchListBuilder::exhibitionVisibleCondition('E'))
            ->setParameter('location', $id)
            ->groupBy('IE.id')
            // ->orderBy('nameSort')
            ;

        $items = $qb->getQuery()->getResult();

        return count($items);
    }

    public function getNumberOfArtistsByVenueId($id)
    {
        $qb = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();

        $qb->select([
                'COUNT (DISTINCT P.id) as numArtists',
                // 'COUNT(DISTINCT E.id) AS numExhibitionSort',
                // 'COUNT(DISTINCT IE.id) AS numCatEntrySort',
            ])
            ->from('AppBundle:Person', 'P')
            ->innerJoin('AppBundle:ItemExhibition', 'IE',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'IE.person = P AND (IE.title IS NOT NULL OR IE.item IS NULL)')
            ->innerJoin('IE.exhibition', 'E')
            ->where('E.location = :location')
            ->andWhere(\AppBundle\Utils\SearchListBuilder::exhibitionVisibleCondition('E'))
            ->setParameter('location', $id)
            ->groupBy('P.id')
            // ->orderBy('nameSort')
            ;

        $artists = $qb->getQuery()->getResult();

        return count( $artists );
    }

    public function getNumberOfNationalitiesByVenueId($id)
    {
        $qb = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();

        $qb->select([
                'P.nationality as nationality',
                'COUNT (DISTINCT P.id)  AS numArtist',

            ])
            ->from('AppBundle:Person', 'P')
            ->innerJoin('AppBundle:ItemExhibition', 'IE',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'IE.person = P AND (IE.title IS NOT NULL OR IE.item IS NULL)')
            ->innerJoin('IE.exhibition', 'E')
            ->where('E.location = :location')
            ->andWhere(\AppBundle\Utils\SearchListBuilder::exhibitionVisibleCondition('E'))
            ->setParameter('location', $id)
            ->groupBy('P.id')
            ->groupBy('P.nationality');
            ;

        $allNationalities = $qb->getQuery()->getResult();

        return count($allNationalities);
    }

    public function getTypesAndNumberOfExhibitionsByVenueId($id)
    {
        $qb = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();

        $qb->select([
                'E.type',
                'COUNT(DISTINCT E.id) AS numExhibitions'
            ])
            ->from('AppBundle:Exhibition', 'E')
            ->where('E.location = :location')
            ->andWhere(\AppBundle\Utils\SearchListBuilder::exhibitionVisibleCondition('E'))
            ->setParameter('location', $id)
            ->groupBy('E.type');
            ;

        $countExhibitionsByType = [];

        foreach ($qb->getQuery()->getResult() as $exhibition) {
            $countExhibitionsByType[$exhibition['type']] = $exhibition['numExhibitions'];
        }

        return $countExhibitionsByType;
    }

    public function getNumberOfExhibitionsByVenueId($id)
    {

        $qb = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();

        $qb->select([
                'COUNT(E.id) AS numExhibitions'
            ])
            ->from('AppBundle:Exhibition', 'E')
            ->where('E.location = :location')
            ->andWhere(\AppBundle\Utils\SearchListBuilder::exhibitionVisibleCondition('E'))
            ->setParameter('location', $id)
            ;

        $result = $qb->getQuery()->getResult();

        return $result[0]['numExhibitions'] ;
    }

    public function getExhibitionsGroupedYearByTgn($tgn)
    {
        $qb = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();

        $qb->select([
                // 'E.id as Id',
                'YEAR(E.startdate) AS start_year',
                'COUNT(DISTINCT E.id) AS how_many'
            ])
            ->from('AppBundle:Exhibition', 'E')
            ->leftJoin('E.location', 'L')
            ->leftJoin('AppBundle:ItemExhibition', 'IE',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'IE.exhibition = E AND (IE.title IS NOT NULL OR IE.item IS NULL)')
            ->leftJoin('IE.person', 'P')
            ->where('L.place = :tgn')
            ->andWhere(\AppBundle\Utils\SearchListBuilder::exhibitionVisibleCondition('E'))
            ->setParameter('tgn', $tgn)
            ->groupBy('E.id')
            ->groupBy('start_year');
            ;

        $exhibitions = $qb->getQuery()->getResult();

        $xAxis = [];
        $yAxis = [];
        foreach ($exhibitions as $exhibition){
            array_push($xAxis,$exhibition['start_year']);
            array_push($yAxis, (float)$exhibition['how_many']);
        }

        return [ $xAxis, $yAxis ];
    }

    public function getExhibitionsByTgn($tgn)
    {
        $qb = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();

        $qb->select([
                'E'
            ])
            ->from('AppBundle:Exhibition', 'E')
            ->leftJoin('E.location', 'L')
            ->leftJoin('AppBundle:ItemExhibition', 'IE',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'IE.exhibition = E AND (IE.title IS NOT NULL OR IE.item IS NULL)')
            ->leftJoin('IE.person', 'P')
            ->where('L.place = :tgn')
            ->andWhere(\AppBundle\Utils\SearchListBuilder::exhibitionVisibleCondition('E'))
            ->setParameter('tgn', $tgn)
            ->groupBy('E.id');
            ;

        $exhibitions = $qb->getQuery()->getResult();

        return $exhibitions;
    }

    public function getAllArtists($tgn)
    {
        $qb = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();

        $qb->select([
                'P',
                'P.id AS id',
                "CONCAT(COALESCE(P.familyName,P.givenName), ' ', COALESCE(P.givenName, '')) HIDDEN nameSort",
                "0 AS exhibited"
            ])
            ->from('AppBundle:Exhibition', 'E')
            ->leftJoin('E.location', 'L')
            ->leftJoin('AppBundle:ItemExhibition', 'IE',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'IE.exhibition = E AND (IE.title IS NOT NULL OR IE.item IS NULL)')
            ->leftJoin('AppBundle:Person', 'P',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'P.id = IE.person AND P.id IS NOT NULL')
            ->where('L.place = :tgn AND P.id IS NOT NULL' )
            ->groupBy('P.id')
            ->setParameter('tgn', $tgn)
            ;

        $artists = $qb->getQuery()->getResult();

        $qb2 = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();

        $qb2->select([
                'P',
                'P.id AS id',
                "CONCAT(COALESCE(P.familyName,P.givenName), ' ', COALESCE(P.givenName, '')) HIDDEN nameSort",
                "0 AS exhibited",
            ])
            ->from('AppBundle:Person', 'P')
            ->where('P.status <> -1 AND (P.birthPlace = :tgn or P.deathPlace = :tgn OR JSON_CONTAINS(P.addresses, :placeTgn) = 1)')
            ->setParameter('tgn', $tgn)
            ->setParameter('placeTgn', sprintf('{"place_tgn": "%s"}', $tgn))
            ;


        $artistsLiving = $qb2->getQuery()->getResult();

        $allArtists = array_merge($artists, $artistsLiving);

        $allArtists = array_unique($allArtists, SORT_REGULAR);

        $counter = 0;

        // setting exhibited to true for all exhibiting artists
        foreach ($artists as $artist) {
            $key = array_search($artist['id'], array_column($allArtists, 'id'));

            if ($allArtists[$key]['id']) {
                $allArtists[$key]['exhibited'] = 1;
                $counter++;
            }
        }

        return $allArtists;
    }

    public function getNumberOfNationalities($tgn)
    {

        $qb = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();

        $qb->select([
                'P.id AS id',
                'P.nationality as nationality'
            ])
            ->from('AppBundle:Exhibition', 'E')
            ->leftJoin('E.location', 'L')
            ->leftJoin('AppBundle:ItemExhibition', 'IE',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'IE.exhibition = E AND (IE.title IS NOT NULL OR IE.item IS NULL)')
            ->leftJoin('AppBundle:Person', 'P',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'P.id = IE.person AND P.id IS NOT NULL')
            // ->leftJoin('IE.person', 'P')
            ->where('L.place = :tgn AND P.id IS NOT NULL' )
            ->groupBy('P.id')
            ->setParameter('tgn', $tgn)
            ;

        $artists = $qb->getQuery()->getResult();

        $qb2 = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();

        $qb2->select([
                'P.id AS id',
                'P.nationality as nationality'
            ])
            ->from('AppBundle:Person', 'P')
            ->where('P.birthPlace = :tgn or P.deathPlace = :tgn AND P.id IS NOT NULL')
            ->setParameter('tgn', $tgn)
            ;

        $artistsLiving = $qb2->getQuery()->getResult();

        $allArtists = array_merge($artists, $artistsLiving);

        $allArtists = array_unique($allArtists, SORT_REGULAR);

        $countriesOnly = array_column($allArtists, 'nationality');

        $countriesOnly = array_replace($countriesOnly, array_fill_keys(array_keys($countriesOnly, null), '[unknown]')); // remove null values if existing

        $countriesStats = array_count_values($countriesOnly);

        return $this->assoc2NameYArray($countriesStats);
    }


    public function getGenderSplit($tgn)
    {
        $qb = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();

        $qb->select([
                'P.id AS id',
                'P.gender as gender'
            ])
            ->from('AppBundle:Exhibition', 'E')
            ->leftJoin('E.location', 'L')
            ->leftJoin('AppBundle:ItemExhibition', 'IE',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'IE.exhibition = E AND (IE.title IS NOT NULL OR IE.item IS NULL)')
            ->leftJoin('AppBundle:Person', 'P',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'P.id = IE.person AND P.id IS NOT NULL')
            ->where('L.place = :tgn AND P.id IS NOT NULL' )
            ->groupBy('P.id')
            ->setParameter('tgn', $tgn)
            ;

        $artists = $qb->getQuery()->getResult();

        $qb2 = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();

        $qb2->select([
                'P.id AS id',
                'P.gender as gender'
            ])
            ->from('AppBundle:Person', 'P')
            ->where('P.birthPlace = :tgn or P.deathPlace = :tgn AND P.id IS NOT NULL')
            ->setParameter('tgn', $tgn)
            ;

        $artistsLiving = $qb2->getQuery()->getResult();

        $allArtists = array_merge($artists, $artistsLiving);
        $allArtists = array_unique ( $allArtists, SORT_REGULAR );
        $gendersOnly = array_column($allArtists, 'gender');
        $gendersOnly = array_replace($gendersOnly,array_fill_keys(array_keys($gendersOnly, null),'')); // remove null values if existing
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

    public function getNumberArtistsExhibitioned($tgn)
    {
        $qb = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();

        $qb->select([
                'COUNT(DISTINCT P.id) AS numArtistsExhibited',
            ])
            ->from('AppBundle:Exhibition', 'E')
            ->leftJoin('E.location', 'L')
            ->leftJoin('AppBundle:ItemExhibition', 'IE',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'IE.exhibition = E AND (IE.title IS NOT NULL OR IE.item IS NULL)')
            ->leftJoin('IE.person', 'P')
            ->where('L.place = :tgn')
            ->setParameter('tgn', $tgn)
            ;

        $artists = $qb->getQuery()->getResult();

        return $artists;
    }

    public function getNumberArtistsBorn($tgn)
    {
        $qb = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();


        $qb->select([
                'COUNT(DISTINCT P.id) AS numArtistsBorn',
            ])
            ->from('AppBundle:Person', 'P')
            ->where('P.birthPlace = :tgn')
            ->setParameter('tgn', $tgn)
            ;

        $artists = $qb->getQuery()->getResult();

        return $artists[0]['numArtistsBorn'];
    }

    public function getNumberArtistsDied($tgn)
    {
        $qb = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();

        $qb->select([
                'COUNT(DISTINCT P.id) AS numArtistsDied',
            ])
            ->from('AppBundle:Person', 'P')
            ->where('P.deathPlace = :tgn')
            ->setParameter('tgn', $tgn)
            ;

        $artists = $qb->getQuery()->getResult();

        return $artists[0]['numArtistsDied'];
    }

    public function getNumberArtistsActive($tgn)
    {
        $qb = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();

        $qb->select([
                'COUNT(DISTINCT P.id) AS numArtistsActive',
            ])
            ->from('AppBundle:Person', 'P')
            ->where('JSON_CONTAINS(P.addresses, :placeTgn) = 1')
            ->setParameter('placeTgn', sprintf('{"place_tgn": "%s"}', $tgn))
            ;

        $artists = $qb->getQuery()->getResult();

        return $artists[0]['numArtistsActive'];
    }

    public function getNumberOfVenues($tgn)
    {
        $qb = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();

        $qb->select([
                'COUNT(DISTINCT L.id) AS numVenues',
            ])
            ->from('AppBundle:Exhibition', 'E')
            ->leftJoin('E.location', 'L')
            ->leftJoin('AppBundle:ItemExhibition', 'IE',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'IE.exhibition = E AND (IE.title IS NOT NULL OR IE.item IS NULL)')
            ->leftJoin('IE.person', 'P')
            ->where('L.place = :tgn')
            ->andWhere(\AppBundle\Utils\SearchListBuilder::exhibitionVisibleCondition('E'))
            ->setParameter('tgn', $tgn)
            ->groupBy('E.id')
            ->groupBy('L.id')
            ;

        $venues = $qb->getQuery()->getResult();


        // there is still a discrepency between this result and when iteration through all exhibitions --> checked with SQL -- same result

        return count( $venues );
    }

    public function getNumberOfArtists($tgn)
    {

        $qb = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();

        $qb->select([
                'COUNT(DISTINCT P.id) AS numArtists',
            ])
            ->from('AppBundle:Exhibition', 'E')
            ->leftJoin('E.location', 'L')
            ->leftJoin('AppBundle:ItemExhibition', 'IE',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'IE.exhibition = E AND (IE.title IS NOT NULL OR IE.item IS NULL)')
            ->leftJoin('IE.person', 'P')
            ->where('L.place = :tgn AND L.status <> -1')
            ->andWhere(\AppBundle\Utils\SearchListBuilder::exhibitionVisibleCondition('E'))
            ->setParameter('tgn', $tgn)
            ->groupBy('E.id')
            ->groupBy('L.id')
        ;

        $artists = $qb->getQuery()->getResult();

        return count($artists);
    }

    public function getNumberOfExhibitions($id, $tgn, $placeId)
    {
        $qb = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();


        $qb->select([
                'COUNT(DISTINCT E.id) AS numExhibitions',
            ])
            ->from('AppBundle:Exhibition', 'E')
            ->leftJoin('E.location', 'L')
            ->leftJoin('AppBundle:ItemExhibition', 'IE',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'IE.exhibition = E AND (IE.title IS NOT NULL OR IE.item IS NULL)')
            ->leftJoin('IE.person', 'P')
            ->where('L.place = :tgn')
            ->andWhere(\AppBundle\Utils\SearchListBuilder::exhibitionVisibleCondition('E'))
            ->setParameter('tgn', $tgn)
            // ->groupBy('E.id')
            ;


        $exhibitions = $qb->getQuery()->getResult();

        return $exhibitions[0]['numExhibitions'];
    }

    public function getStatsExhibitionTypes($tgn)
    {
        $qb = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();

        $qb->select([
                'COUNT(DISTINCT E.id) AS numExhibitions',
                'E.type'
            ])
            ->from('AppBundle:Exhibition', 'E')
            ->leftJoin('E.location', 'L')
            ->leftJoin('AppBundle:ItemExhibition', 'IE',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'IE.exhibition = E AND (IE.title IS NOT NULL OR IE.item IS NULL)')
            ->leftJoin('IE.person', 'P')
            ->where('L.place = :tgn')
            ->andWhere(\AppBundle\Utils\SearchListBuilder::exhibitionVisibleCondition('E'))
            ->setParameter('tgn', $tgn)
            ->groupBy('E.type')
            // ->groupBy('E.id')
            ;

        $exhibitions = $qb->getQuery()->getResult();

        $exhibitionsReturn = [];

        foreach ($exhibitions as $exhibition) {
            $exhibitionsReturn[$exhibition['type']] = $exhibition['numExhibitions'];
        }

        return $exhibitionsReturn;
    }
}
