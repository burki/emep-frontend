<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Intl\Intl;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\User\UserInterface;

use AppBundle\Utils\CsvResponse;

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
        $settings = $this->lookupSettingsFromRequest($request);
        $route = $settings['base'];
        $entity = 'organizer' == $route ? 'Organizer' : 'Venue';

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
                       'IE.person = P AND (IE.title IS NOT NULL OR IE.item IS NULL)')
            ->innerJoin('IE.exhibition', 'E')
            ->where('E.id IN(:ids)')
            ->andWhere(\AppBundle\Utils\SearchListBuilder::exhibitionVisibleCondition('E'))
            ->setParameter('ids', $exhibitionIds)
            ->groupBy('P.id')
            ->orderBy('nameSort')
            ;

        return $qb->getQuery()->getResult();
    }

    protected function getGenderCountsByExhibitionId($exhibitionIds)
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
                'IE.person = P AND (IE.title IS NOT NULL OR IE.item IS NULL)')
            ->innerJoin('IE.exhibition', 'E')
            ->where('E.id IN(:ids) AND P.status <> -1')
            ->andWhere(\AppBundle\Utils\SearchListBuilder::exhibitionVisibleCondition('E'))
            ->setParameter('ids', $exhibitionIds)
            ->groupBy('gender')
            ;

        $results = $qb->getQuery()->getResult();

        $data = [];

        foreach ($results as $result) {
            $key = '[unknown]';

            if ($result['gender'] == 'M') {
                $key = 'male';
            }
            else if ($result['gender'] == 'F') {
                $key = 'female';
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
                       'IE.exhibition = E AND (IE.title IS NOT NULL OR IE.item IS NULL)')
            ->leftJoin('IE.person', 'P')
            ->where('E.id IN (:ids)')
            ->andWhere(\AppBundle\Utils\SearchListBuilder::exhibitionVisibleCondition('E'))
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

        $csvResult = array_map(function ($values) {
                $person = $values[0];
                return [
                    $person->getFullname(false),
                    $person->getBirthDate(), $person->getDeathDate(),
                    $person->getNationality(),
                    $values['numExhibitionSort'], $values['numCatEntrySort'],
                ];
            }, $artists);

        return new CsvResponse($csvResult, 200, explode( ', ', 'Name, Date of Birth, Date of Death, Nationality, # of Exhibitions, # of Cat. Entries'));
    }

    /**
     * @Route("/location/{id}/exhibitions/csv", requirements={"id" = "\d+"}, name="location-exhibitions-csv")
     */
    public function detailActionExhibitions(Request $request, $id = null)
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
        $exhibitionStats = $this->getExhibitionStatsByIds($exhibitionIds);

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
     * @Route("/location/{id}.jsonld", requirements={"id" = "\d+"}, name="location-jsonld")
     * @Route("/location/{id}", requirements={"id" = "\d+"}, name="location")
     * @Route("/organizer/{id}.jsonld", requirements={"id" = "\d+"}, name="organizer-lsonld")
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
        if (in_array($request->get('_route'), [ 'location-jsonld', 'organizer-jsonld' ])) {
            return new JsonLdResponse($location->jsonLdSerialize($locale));
        }

        $location->setDateModified(\AppBundle\Utils\LocationListBuilder::fetchDateModified($this->getDoctrine()->getConnection(), $location->getId()));

        $exhibitionIds = $this->getExhibitionIds($location);

        $artists = $this->getArtistsByExhibitionIds($exhibitionIds);

        $genderCounts = $this->getGenderCountsByExhibitionId($exhibitionIds);

        return $this->render('Location/detail.html.twig', [
            'pageTitle' => $location->getName(),
            'location' => $location,
            'exhibitionStats' => $this->getExhibitionStatsByIds($exhibitionIds),
            'artists' => $artists,
            'dataNumberOfArtistsPerCountry' => $this->detailDataNumberOfArtistsPerCountry($artists),
            'detailDataNumberItemTypes' => $this->detailDataNumberItemTypes($location),
            'genderStats' => $genderCounts,
            'genderStatsStatisticsFormat' => $this->assoc2NameYArray($genderCounts),
            'pageMeta' => [
                /*
                'jsonLd' => $location->jsonLdSerialize($locale),
                'og' => $this->buildOg($location, $routeName, $routeParams),
                'twitter' => $this->buildTwitter($location, $routeName, $routeParams),
                */
            ],
        ]);
    }

    private function detailDataNumberItemTypes($location)
    {
        $exhibitions = $location->getAllExhibitions();

        $types = [];

        foreach ($exhibitions as $exhibition) {
            $entries = $exhibition->getCatalogueEntries();

            foreach ($entries as $entry) {
                if ($entry->type) {
                    $currType = $entry->type->getName();
                    array_push($types, (string)$currType == '0_unknown' ? 'unknown' : $currType);
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

    private function detailDataNumberOfArtistsPerCountry($artists)
    {
        $artistNationalities = array_map(function ($artist) { return (string)$artist[0]->getNationality(); }, $artists);

        $artistNationalitiesTotal = array_count_values($artistNationalities);
        arsort($artistNationalitiesTotal);

        $finalData = [];

        foreach ($artistNationalitiesTotal as $key => $count) {
            $finalData[] = [
                'name' => $this->expandCountryCode($key),
                'y' => (int)$count,
                'id' => $key,
            ];
        }

        return [ json_encode($finalData), count($artists), count(array_keys($artistNationalitiesTotal)) ];
    }
}
