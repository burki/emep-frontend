<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Intl\Intl;
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

    protected function buildFilterForm($entity = 'Venue')
    {
        if ('Organizer' == $entity) {
            $venueTypes = $this->buildVenueTypes();
            $this->form = $this->createForm(\AppBundle\Filter\OrganizerFilterType::class, [
                'choices' => [
                    'country' => array_flip($this->buildCountries()),
                    'location_type' => array_combine($venueTypes, $venueTypes),
                ],
            ]);
        }
        else {
            $venueTypes = $this->buildVenueTypes();
            $this->form = $this->createForm(\AppBundle\Filter\LocationFilterType::class, [
                'choices' => [
                    'country' => array_flip($this->buildCountries()),
                    'location_type' => array_combine($venueTypes, $venueTypes),
                ],
            ]);
        }
    }

    protected function buildSaveSearchParams(Request $request, UrlGeneratorInterface $urlGenerator)
    {
        $route = str_replace('-save', '-index', $request->get('_route'));
        $entity = 'organizer-index' == $route ? 'Organizer' : 'Venue';

        $this->form = $this->createSearchForm($request, $urlGenerator);

        $listBuilder = $this->instantiateListBuilder($request, $urlGenerator, false, $entity);
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
     * @Route("/location/save", name="venue-save")
     * @Route("/organizer/save", name="organizer-save")
     */
    public function saveSearchAction(Request $request,
                                     UrlGeneratorInterface $urlGenerator,
                                     UserInterface $user)
    {
        return $this->handleSaveSearchAction($request, $urlGenerator, $user);
    }


    protected function getExhibitionIds($location)
    {
        // get exhibition-ids both as venue and as organizers
        $exhibitionIds = [];

        $exhibitions = $location->getExhibitions();
        if (!is_null($exhibitions)) {
            $exhibitionIds = array_map(function ($exhibition) { return $exhibition->getId(); }, $exhibitions->toArray());
        }

        $exhibitions = $location->getOrganizerOf();
        if (!is_null($exhibitions)) {
            $exhibitionIds = array_unique(
                array_merge($exhibitionIds,
                            array_map(function ($exhibition) { return $exhibition->getId(); }, $exhibitions->toArray())));
        }

        return $exhibitionIds;
    }

    protected function getArtistsByExhibitionIds($exhibitionIds)
    {
        if (empty($exhibitionIds)) {
            return [];
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
            ->where('E.id IN(:ids) AND E.status <> -1')
            ->setParameter('ids', $exhibitionIds)
            ->groupBy('P.id')
            ->orderBy('nameSort')
            ;

        return $qb->getQuery()->getResult();
    }

    protected function getGenderSplitByExhId($exhibitionIds)
    {
        if (empty($exhibitionIds)) {
            return [];
        }

        // artists this venue
        $qb = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();

        $qb->select([
            'P.gender as gender',
            'COUNT(DISTINCT P.id) AS how_many',
        ])
            ->from('AppBundle:Person', 'P')
            ->innerJoin('AppBundle:ItemExhibition', 'IE',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'IE.person = P AND IE.title IS NOT NULL')
            ->innerJoin('IE.exhibition', 'E')
            ->where('E.id IN(:ids) AND E.status <> -1')
            ->setParameter('ids', $exhibitionIds)
            ->groupBy('gender')
        ;

        $results = $qb->getQuery()->getResult();

        $data = [];

        foreach ($results as $result){
            $key = 'unknown';


            if($result['gender'] == 'M'){
                $key = 'Male';
            }else if ($result['gender'] == 'F'){
                $key = 'Female';
            }

            $data[$key] = $result['how_many'];
        }

        return $data;
    }

    protected function getExhibitionStatsByIds($exhibitionIds)
    {
        $exhibitionStats = [];

        if (empty($exhibitionIds)) {
            return $exhibitionStats;
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
            ->leftJoin('AppBundle:ItemExhibition', 'IE',
                       \Doctrine\ORM\Query\Expr\Join::WITH,
                       'IE.exhibition = E AND IE.title IS NOT NULL')
            ->leftJoin('IE.person', 'P')
            ->where('E.id IN (:ids) AND E.status <> -1')
            ->setParameter('ids', $exhibitionIds)
            ->groupBy('E.id')
            ;

        foreach ($qb->getQuery()->getResult() as $row) {
           $exhibitionStats[$row['id']] = $row;
        }

        return $exhibitionStats;
    }


    /**
     * @Route("/location/{id}/artists/csv", requirements={"id" = "\d+"}, name="location-artists-csv")
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
            return $this->redirectToRoute('venue-index');
        }

        $exhibitionIds = $this->getExhibitionIds($location);

        $artists = $this->getArtistsByExhibitionIds($exhibitionIds);

        $csvResult = [];

        foreach ($artists as $key => $value) {
            $artist = $value[0];

            $innerArray = [];
            array_push($innerArray,
                       $artist->getFullName(true),
                       $value['numExhibitionSort'],
                       $value['numCatEntrySort'] );

            array_push($csvResult, $innerArray);
        }

        return new CsvResponse($csvResult, 200, explode( ', ', 'Person, # of Exhibitions, # of Cat. Entries'));
    }

    /**
     * @Route("/location/{id}/exhibitions/csv", requirements={"id" = "\d+"}, name="location-exhibitions-csv")
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
            return $this->redirectToRoute('venue-index');
        }

        $csvResult = [];

        foreach ($location->getAllExhibitions() as $exhibition) {
            $innerArray = [];
            array_push($innerArray,
                       $exhibition->getStartdate(), $exhibition->getEnddate(), $exhibition->getDisplaydate(),
                       $exhibition->getTitle(),
                       $exhibition->getLocation()->getPlaceLabel(),
                       $exhibition->getLocation()->getName(),
                       $exhibition->getOrganizerType(),
                       $exhibitionStats[$exhibition->getId()]['numCatEntrySort']);

            array_push($csvResult, $innerArray);
        }

        return new CsvResponse($csvResult, 200, explode( ', ', 'Start Date, End Date, Display Date, Title, City, Venue, Type of Org. Body, # of Cat. Entries'));
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
            return $this->redirectToRoute('venue-index');
        }

        $locale = $request->getLocale();
        if (in_array($request->get('_route'), [ 'location-jsonld' ])) {
            return new JsonLdResponse($location->jsonLdSerialize($locale));
        }

        $exhibitionIds = $this->getExhibitionIds($location);

        $artists = $this->getArtistsByExhibitionIds($exhibitionIds);
        $exhibitionStats = $this->getExhibitionStatsByIds($exhibitionIds);

        // get alternative location for the case that the geo is empty
        $qbAlt = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();

        $qbAlt->select([
                'L',
                "L.placeLabel",
                "P.latitude",
                "P.longitude"
            ])
            ->from('AppBundle:Location', 'L')
            ->leftJoin('AppBundle:Place', 'P',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'P.name = L.placeLabel')
            ->where('L.id = ' . $id)
            ;

        $place = $qbAlt->getQuery()->execute();

        $dataNumberOfArtistsPerCountry = $this->detailDataNumberOfArtistsPerCountry($artists);

        $detailDataNumberItemTypes = $this->detailDataNumberItemTypes($location);

        $genderStats = $this->getGenderSplitByExhId($exhibitionIds);

        $genderStatsStatisticsFormat = PlaceController::assoc2NameYArray($genderStats);


        return $this->render('Location/detail.html.twig', [
            'pageTitle' => $location->getName(),
            'location' => $location,
            'altPlace' => $place[0],
            'exhibitionStats' => $exhibitionStats,
            'artists' => $artists,
            'dataNumberOfArtistsPerCountry' => $dataNumberOfArtistsPerCountry,
            'detailDataNumberItemTypes' => $detailDataNumberItemTypes,
            'genderStats' => $genderStats,
            'genderStatsStatisticsFormat' => $genderStatsStatisticsFormat,
            'pageMeta' => [
                /*
                'jsonLd' => $location->jsonLdSerialize($locale),
                'og' => $this->buildOg($location, $routeName, $routeParams),
                'twitter' => $this->buildTwitter($location, $routeName, $routeParams),
                */
            ],
        ]);
    }

    public function detailDataNumberItemTypes($location)
    {
        $exhibitions = $location->getAllExhibitions();

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
        arsort($typesTotal);

        $finalData = array_map(function ($key) use ($typesTotal) {
                return [ 'name' => $key, 'y' => (int)$typesTotal[$key]];
            },
            array_keys($typesTotal));

        $sumOfAllTypes = array_sum(array_values($typesTotal));

        return [ json_encode($finalData), $sumOfAllTypes ];
    }

    public function detailDataNumberOfArtistsPerCountry($artists)
    {
        $artistNationalities = [];

        foreach ($artists as $artist) {
            array_push($artistNationalities, (string)$artist[0]->getNationality() );
        }

        $artistNationalitiesTotal = array_count_values($artistNationalities);
        arsort($artistNationalitiesTotal);

        $finalData = array_map(function ($key) use ($artistNationalitiesTotal) {
                $name = '' === $key ? 'unknown' : Intl::getRegionBundle()->getCountryName($key);
                return [ 'name' => $name, 'y' => (int)$artistNationalitiesTotal[$key]];
            },
            array_keys($artistNationalitiesTotal));

        $sumOfAllNationalities = array_sum(array_values($artistNationalitiesTotal));

        return [ json_encode($finalData), $sumOfAllNationalities, count(array_keys($artistNationalitiesTotal)) ];
    }
}
