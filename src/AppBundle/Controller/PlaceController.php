<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Intl\Intl;
use Symfony\Contracts\Translation\TranslatorInterface;

use Knp\Component\Pager\PaginatorInterface;

use Lexik\Bundle\FormFilterBundle\Filter\FilterBuilderUpdaterInterface;

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
            $countriesActive[$result['countryCode']] = $this->expandCountryCode($result['countryCode']);
        }

        asort($countriesActive);

        return $countriesActive;
    }

    /**
     * @Route("/place", name="place-inhabited")
     */
    public function indexAction(Request $request,
                                TranslatorInterface $translator,
                                PaginatorInterface $paginator,
                                FilterBuilderUpdaterInterface $queryBuilderUpdater)
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

        $form = $this->createForm(\AppBundle\Filter\PlaceFilterType::class, [
            'choices' => array_flip($this->buildCountries()),
        ]);

        if ($request->query->has($form->getName())) {
            // manually bind values from the request
            $form->submit($request->query->get($form->getName()));

            // build the query from the given form object
            $queryBuilderUpdater->addFilterConditions($form, $qb);
        }

        $pagination = $this->buildPagination($request, $paginator, $qb->getQuery(), [
            // the following leads to wrong display in combination with our
            // helper.pagination_sortable()
            // 'defaultSortFieldName' => 'nameSort', 'defaultSortDirection' => 'asc',
        ]);

        return $this->render('Place/index.html.twig', [
            'pageTitle' => $translator->trans('Places'),
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
     * @Route("/place/{id}.jsonld", name="place-jsonld")
     * @Route("/place/{id}", requirements={"id" = "\d+"}, name="place")
     * @Route("/place/tgn/{tgn}.jsonld", name="place-by-tgn-jsonld")
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
            return new JsonLdResponse($place->jsonLdSerialize($locale, false, $this->getDoctrine()->getManager()));
        }

        $place->setDateModified(\AppBundle\Utils\PlaceListBuilder::fetchDateModified($this->getDoctrine()->getConnection(), $place->getTgn()));

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
            ->where('L.place = :place AND L.status <> -1')
            ->andWhere(\AppBundle\Utils\SearchListBuilder::exhibitionVisibleCondition('E'))
            ->setParameter('place', $place)
            ->groupBy('E.id')
            ;

        $exhibitionStats = [];
        foreach ($qb->getQuery()->getResult() as $row) {
            $exhibitionStats[$row['id']] = $row;
        }

        $exhibitionTypeStats = $this->getStatsExhibitionTypes($tgn);

        $exhibitions = $this->getExhibitionsByTgn($tgn);
        $venues = $this->getVenuesList($tgn = $place->getTgn());

        $allArtists = $this->getAllArtists($tgn);
        $genderCounts = $this->buildGenderCounts($allArtists);

        return $this->render('Place/detail.html.twig', [
            'pageTitle' => $place->getNameLocalized($locale),
            'place' => $place,
            'persons' => $allArtists,
            'exhibitions' => $exhibitions,
            'venues' => $venues,
            'organizers' => $this->getOrganizersByTgn($tgn),
            'numberBorn' => $this->getNumberArtistsBorn($tgn),
            'numberDied' => $this->getNumberArtistsDied($tgn),
            'numberActive' => $this->getNumberArtistsActive($tgn),
            'numberExhibited' => $this->getNumberArtistsExhibited($tgn),
            'numberExhibitions' => count($exhibitions),
            'numberVenues' => count($venues),
            'exhibitionTypeStats' => $exhibitionTypeStats,
            'genderStats' => $genderCounts,
            'exhibitionStats' => $exhibitionStats,
            'em' => $this->getDoctrine()->getManager(),

            // tabcontent-statistics.html.twig
            'nationalitiesStats' => $this->assoc2IdNameYArray($this->buildNationalityCounts($allArtists)),
            'exhibitionsGroupedByYearStats' => $this->getExhibitionsGroupedByYearByTgn($tgn),
            'exhibitionTypeStatisticsFormat' => $this->assoc2NameYArray($exhibitionTypeStats),
            'genderStatsStatisticsFormat' => $this->assoc2NameYArray($genderCounts),

            // meta
            'pageMeta' => [
                'jsonLd' => $place->jsonLdSerialize($locale, false,
                                                    $this->getDoctrine()->getManager()),
            ],
        ]);
    }

    /**
     * Venues including stats
     * This is currently utterly inefficient (one DB-call for each Catalogue Entry)
     *
     * TODO: merge with getOrganizersByTgn
     */
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
            ->innerJoin('AppBundle:Exhibition', 'E',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'E.location = L AND ' . \AppBundle\Utils\SearchListBuilder::exhibitionVisibleCondition('E'))
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
                'numItems' => $this->getNumberOfWorksByVenueId($venue['id']),
                'numExhibitions' => $this->getNumberOfExhibitionsByVenueId($venue['id']),
                'exhibition_types' => [],
            ];

            foreach ($this->getNumberOfExhibitionsByTypeByVenueId($venue['id']) as $type => $num) {
                $venueInfo['exhibition_types'][$type] = $num;
            }

            $venues[] = $venueInfo;
        }

        return $venues;
    }

    /**
     *
     */
    public function getNumberOfWorksByVenueId($id)
    {
        $qb = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();

        $qb->select([
                'COUNT(DISTINCT IE.id) as numItems',
            ])
            ->from('AppBundle:Exhibition', 'E')
            ->innerJoin('AppBundle:ItemExhibition', 'IE',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'IE.exhibition = E AND (IE.title IS NOT NULL OR IE.item IS NULL)')
            ->where('E.location = :location')
            ->andWhere(\AppBundle\Utils\SearchListBuilder::exhibitionVisibleCondition('E'))
            ->setParameter('location', $id)
            ;

        $result = $qb->getQuery()->getResult();

        return $result[0]['numItems'];
    }

    public function getNumberOfArtistsByVenueId($id)
    {
        $qb = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();

        $qb->select([
                'COUNT(DISTINCT P.id) as numArtists',
            ])
            ->from('AppBundle:Person', 'P')
            ->innerJoin('AppBundle:ItemExhibition', 'IE',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'IE.person = P AND (IE.title IS NOT NULL OR IE.item IS NULL)')
            ->innerJoin('IE.exhibition', 'E')
            ->where('E.location = :location AND P.status <> -1')
            ->andWhere(\AppBundle\Utils\SearchListBuilder::exhibitionVisibleCondition('E'))
            ->setParameter('location', $id)
            ;

        $result = $qb->getQuery()->getResult();

        return $result[0]['numArtists'];
    }

    public function getNumberOfNationalitiesByVenueId($id)
    {
        $qb = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();

        $qb->select([
                "COUNT (DISTINCT COALESCE(P.nationality, '_unknown')) AS total", // we want to count NULL as well
            ])
            ->from('AppBundle:Person', 'P')
            ->innerJoin('AppBundle:ItemExhibition', 'IE',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'IE.person = P AND (IE.title IS NOT NULL OR IE.item IS NULL)')
            ->innerJoin('IE.exhibition', 'E')
            ->where('E.location = :location AND P.status <> -1')
            ->andWhere(\AppBundle\Utils\SearchListBuilder::exhibitionVisibleCondition('E'))
            ->setParameter('location', $id)
            ;

        $result = $qb->getQuery()->getResult();

        return $result[0]['total'];
    }

    public function getNumberOfExhibitionsByTypeByVenueId($id)
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

    /**
     * Org. Bodies including stats
     */
    public function getOrganizersByTgn($tgn)
    {
        $qb = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();

        $qb->select([
                'L AS location',
                'L.id AS id',
                'L.name AS name',
                'L.type AS type',
                'COUNT(DISTINCT E.id) as numExhibitions',
                'COUNT(DISTINCT IE.id) as numItems',
                'COUNT(DISTINCT P.id) as numArtists',
            ])
            ->from('AppBundle:Location', 'L')
            ->innerJoin('AppBundle:Exhibition', 'E',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'L MEMBER OF E.organizers'
                . ' AND ' . \AppBundle\Utils\SearchListBuilder::exhibitionVisibleCondition('E'))
            ->leftJoin('AppBundle:ItemExhibition', 'IE',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'IE.exhibition = E AND (IE.title IS NOT NULL OR IE.item IS NULL)')
            ->leftJoin('IE.person', 'P')
            ->where('L.place = :tgn')
            ->andWhere('L.status <> -1')
            ->setParameter('tgn', $tgn)
            ->groupBy('E.id')
            ->groupBy('L.id')
            ;

        $organizers = [];
        foreach ($qb->getQuery()->getResult() as $organizer) {
            $organizers[] = $organizer + $this->getNumberOfNationalitiesByOrganizer($organizer['location']);
        }

        return $organizers;
    }

    public function getNumberOfNationalitiesByOrganizer($organizer)
    {
        $qb = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();

        $qb->select([
                "COUNT(DISTINCT COALESCE(P.nationality, '_unknown')) AS numNationalities", // we want to count NULL as well
            ])
            ->from('AppBundle:Person', 'P')
            ->innerJoin('AppBundle:ItemExhibition', 'IE',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'IE.person = P AND (IE.title IS NOT NULL OR IE.item IS NULL)')
            ->innerJoin('IE.exhibition', 'E')
            ->innerJoin('AppBundle:Location', 'L',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'L MEMBER OF E.organizers'
                . ' AND ' . \AppBundle\Utils\SearchListBuilder::exhibitionVisibleCondition('E'))
            ->where('L = :location')
            ->setParameter('location', $organizer)
            ;

        $result = $qb->getQuery()->getResult();

        return $result[0];
    }

    protected function getExhibitionsGroupedByYearByTgn($tgn)
    {
        $qb = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();

        $qb->select([
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

        $exhibitionByYear = [];
        foreach ($qb->getQuery()->getResult() as $row){
            $exhibitionByYear[$row['start_year']] = (int)$row['how_many'];
        }

        return $exhibitionByYear;
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
            ->innerJoin('AppBundle:Person', 'P',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'P.id = IE.person AND P.status <> -1')
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

    protected function buildNationalityCounts($allArtists)
    {
        $nationalityCounts = [];

        foreach ($allArtists as $info) {
            $person = $info[0];

            $key = $person->getNationality();

            if (!array_key_exists($key, $nationalityCounts)) {
                $nationalityCounts[$key] = [
                    'name' => $this->expandCountryCode($key),
                    'count' => 0,
                ];
            }

            ++$nationalityCounts[$key]['count'];
        }

        uasort($nationalityCounts, function($a, $b) {
            return $b['count'] <=> $a['count'];
        });

        return $nationalityCounts;
    }

    public function buildGenderCounts($allArtists)
    {
        $stats = [];

        foreach ($allArtists as $info) {
            $person = $info[0];

            $gender = $person->getGender();
            if (is_null($gender)) {
                $key = '[unknown]';
            }
            else {
                $key = $person->getGenderLabel();
            }

            if (!array_key_exists($key, $stats)) {
                $stats[$key] = 0;
            }

            ++$stats[$key];
        }

        arsort($stats);

        return $stats;
    }

    public function getNumberArtistsExhibited($tgn)
    {
        $qb = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();

        $qb->select([
                'COUNT(DISTINCT P.id) AS total',
            ])
            ->from('AppBundle:Exhibition', 'E')
            ->innerJoin('E.location', 'L')
            ->innerJoin('AppBundle:ItemExhibition', 'IE',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'IE.exhibition = E AND (IE.title IS NOT NULL OR IE.item IS NULL)')
            ->innerJoin('IE.person', 'P')
            ->where(\AppBundle\Utils\SearchListBuilder::exhibitionVisibleCondition('E'))
            ->andWhere('L.place = :tgn AND L.status <> -1')
            ->andWhere('P.status <> -1')
            ->setParameter('tgn', $tgn)
            ;

        $result = $qb->getQuery()->getResult();

        return $result[0]['total'];
    }

    public function getNumberArtistsBorn($tgn)
    {
        $qb = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();

        $qb->select('COUNT(DISTINCT P.id) AS total')
            ->from('AppBundle:Person', 'P')
            ->where('P.birthPlace = :tgn AND P.status <> -1')
            ->setParameter('tgn', $tgn)
            ;

        $result = $qb->getQuery()->getResult();

        return $result[0]['total'];
    }

    public function getNumberArtistsDied($tgn)
    {
        $qb = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();

        $qb->select([
                'COUNT(DISTINCT P.id) AS total',
            ])
            ->from('AppBundle:Person', 'P')
            ->where('P.deathPlace = :tgn AND P.status <> -1')
            ->setParameter('tgn', $tgn)
            ;

        $result = $qb->getQuery()->getResult();

        return $result[0]['total'];
    }

    public function getNumberArtistsActive($tgn)
    {
        $qb = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();

        $qb->select([
                'COUNT(DISTINCT P.id) AS total',
            ])
            ->from('AppBundle:Person', 'P')
            ->where('P.status <> -1 AND JSON_CONTAINS(P.addresses, :placeTgn) = 1')
            ->setParameter('placeTgn', sprintf('{"place_tgn": "%s"}', $tgn))
            ;

        $result = $qb->getQuery()->getResult();

        return $result[0]['total'];
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
            ->innerJoin('E.location', 'L')
            ->innerJoin('AppBundle:ItemExhibition', 'IE',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'IE.exhibition = E AND (IE.title IS NOT NULL OR IE.item IS NULL)')
            ->innerJoin('IE.person', 'P')
            ->where('L.place = :tgn AND L.status <> -1')
            ->andWhere(\AppBundle\Utils\SearchListBuilder::exhibitionVisibleCondition('E'))
            ->setParameter('tgn', $tgn)
            ->groupBy('E.id')
            ->groupBy('L.id')
        ;

        $artists = $qb->getQuery()->getResult();

        return count($artists);
    }

    private function getNumberOfExhibitions($tgn)
    {
        $qb = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();

        $qb->select([
                'COUNT(DISTINCT E.id) AS numExhibitions',
            ])
            ->from('AppBundle:Exhibition', 'E')
            ->innerJoin('E.location', 'L')
            ->where('L.place = :tgn AND L.status <> -1')
            ->andWhere(\AppBundle\Utils\SearchListBuilder::exhibitionVisibleCondition('E'))
            ->setParameter('tgn', $tgn)
            ;


        $exhibitions = $qb->getQuery()->getResult();

        return $exhibitions[0]['numExhibitions'];
    }

    protected function getStatsExhibitionTypes($tgn)
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
            ;

        $exhibitions = $qb->getQuery()->getResult();

        $exhibitionsReturn = [];

        foreach ($exhibitions as $exhibition) {
            $exhibitionsReturn[$exhibition['type']] = $exhibition['numExhibitions'];
        }

        return $exhibitionsReturn;
    }
}
