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
class PersonController
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

    protected function buildSaveSearchParams(Request $request, UrlGeneratorInterface $urlGenerator)
    {
        $route = str_replace('-save', '-index', $request->get('_route'));

        $this->form = $this->createSearchForm($request, $urlGenerator);

        $listBuilder = $this->instantiateListBuilder($request, $urlGenerator, false, 'Person');
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
     * @Route("/person/save", name="person-save")
     */
    public function saveSearchAction(Request $request,
                                     UrlGeneratorInterface $urlGenerator,
                                     UserInterface $user)
    {
        return $this->handleSaveSearchAction($request, $urlGenerator, $user);
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
        foreach ($entity->getExhibitions(-1) as $exhibition) {
            $exhibitionIds[] = $exhibition->getId();
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
                'E.title'
            ])
            ->from('AppBundle:ItemExhibition', 'IE')
            ->innerJoin('AppBundle:Exhibition', 'E',
                        \Doctrine\ORM\Query\Expr\Join::WITH,
                        'IE.exhibition = E AND E.status <> -1')
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
     * @Route("/person/{id}/coappearances/csv", requirements={"id" = "\d+"}, name="person-coappearances-csv")
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

        $result = $this->findSimilar($person);
        $csvResult = [];

        foreach ($result as $key => $value) {
            $person = $value;

            $innerArray = [];

            array_push($innerArray, $person['name'], $person['count'] );

            array_push($csvResult, $innerArray);
        }

        return new CsvResponse($csvResult, 200, explode(', ', 'Artist, # of Co-Appearances'));
    }

    /**
     * @Route("/person/{id}/exhibition/csv", requirements={"id" = "\d+"}, name="person-exhibition-csv")
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

        $result = $person->getExhibitions(-1);

        $csvResult = [];

        $catalogueEntries = $this->findCatalogueEntries($person);

        foreach ($result as $key => $value) {
            $exhibition = $value;

            $innerArray = [];

            array_push($innerArray,
                       $exhibition->getStartdate(), $exhibition->getEnddate(), $exhibition->getDisplaydate(),
                       $exhibition->getTitle(),
                       $exhibition->getLocation()->getPlaceLabel(),
                       $exhibition->getLocation()->getName(),
                       $exhibition->getOrganizerType(),
                       count($catalogueEntries[$exhibition->getId()]));

            array_push($csvResult, $innerArray);
        }

        return new CsvResponse($csvResult, 200, explode(', ', 'Start Date, End Date, Display Date, Title, City, Venue, Type of Org. Body, # of Cat. Entries'));
    }

    /**
     * @Route("/person/ulan/{ulan}", requirements={"ulan"="[0-9]+"}, name="person-by-ulan")
     * @Route("/person/gnd/{gnd}", requirements={"gnd"="[0-9xX]+"}, name="person-by-gnd")
     * @Route("/person/{id}", name="person", requirements={"id"="\d+"})
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

        $catEntries = $this->findCatalogueEntries($person);

        $dataNumberOfWorksPerYear = $this->detailDataNumberOfWorksPerYear($catEntries);

        $dataNumberOfWorksPerType = $this->detailDataNumberOfWorksPerType($catEntries);

        $dataNumberOfExhibitionsPerOrgBody = $this->detailDataNumberOfExhibitionsPerOrgBody($person);

        return $this->render('Person/detail.html.twig', [
            'pageTitle' => $person->getFullname(true), // TODO: lifespan in brackets
            'person' => $person,
            'showWorks' => !empty($_SESSION['user']),
            'catalogueEntries' => $this->findCatalogueEntries($person),
            'similar' => $this->findSimilar($person),
            'currentPageId' => $id,
            'countryArray' => $this->buildCountries(),
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


    public function detailDataNumberOfWorksPerType($catalogueEntries)
    {
        $works = [];

        foreach ($catalogueEntries as $catalogueEntry) {
            $currExhibition = $catalogueEntry[0]->exhibition;

            foreach ($catalogueEntry as $entry) {
                if ($entry->type) {
                    $currType = $entry->type->getName();
                    array_push($works, (string)$currType == '0_unknown' ? 'unknown' : $currType);
                }
            }
        }

        $worksTotalPerType = array_count_values($works);
        arsort($worksTotalPerType);

        $sumOfAllWorks = array_sum(array_values($worksTotalPerType));

        $finalData = array_map(function ($key) use ($worksTotalPerType) {
                return [ 'name' => $key, 'y' => (int)$worksTotalPerType[$key]];
            },
            array_keys($worksTotalPerType));

        return [ json_encode($finalData), $sumOfAllWorks ];
    }

    public function detailDataNumberOfWorksPerYear($catalogueEntriesByExhibition)
    {
        $works = [];

        foreach ($catalogueEntriesByExhibition as $catalogueEntries) {
            $currExhibition = $catalogueEntries[0]->exhibition;

            $currExhibitionYear = date('Y', strtotime($currExhibition->getStartDate())) ;

            foreach ($catalogueEntries as $entry) {
                array_push($works, $currExhibitionYear );
            }
        }

        $worksTotalPerYear = array_count_values($works);

        $yearsArray = array_keys($worksTotalPerYear);

        if (!empty($yearsArray)) {
            $min = min($yearsArray);
            $max = max($yearsArray);
        }
        else {
            $min = 0;
            $max = 0;
        }

        $arrayWithoutGaps = [];

        // create an array without any year gaps inbetween
        for ($i = $min; $i <= $max; $i++) {
            $arrayWithoutGaps[(string)$i] = array_key_exists($i, $worksTotalPerYear) ? $worksTotalPerYear[$i] : 0;
        }

        $yearsOnly = json_encode(array_keys($arrayWithoutGaps));
        $valuesOnly = json_encode(array_values($arrayWithoutGaps));
        $sumOfAllExhibitions = array_sum(array_values($worksTotalPerYear));
        $yearActive = $max - $min > 0 ? $max - $min : 1;

        $averagePerYear = round( $sumOfAllExhibitions / $yearActive, 1 );

        return [ $yearsOnly, $valuesOnly, $sumOfAllExhibitions, $yearActive, $averagePerYear];
    }


    public function detailDataNumberOfExhibitionsPerOrgBody($person)
    {
        $exhibitionOrganizerTypes = [];

        foreach ($person->getExhibitions(-1) as $exhibition) {
            $type = (string)$exhibition->getOrganizerType();
            if ('' == $type) {
                $type = 'unknown';
            }
            array_push($exhibitionOrganizerTypes, $type );
        }

        $exhibitionOrganizerTypesTotal = array_count_values($exhibitionOrganizerTypes);
        arsort($exhibitionOrganizerTypesTotal);

        $finalData = array_map(function ($key) use ($exhibitionOrganizerTypesTotal) {
                return [ 'name' => $key, 'y' => (int)$exhibitionOrganizerTypesTotal[$key]];
            },
            array_keys($exhibitionOrganizerTypesTotal));

        $sumOfAllExhibitions = array_sum(array_values($exhibitionOrganizerTypesTotal));

        return [ json_encode($finalData), $sumOfAllExhibitions ];
    }

    public function detailDataNumberOfExhibitionsPerCountry($person)
    {
        $exhibitionPlacesByCountry = [];

        foreach ($person->getExhibitions(-1) as $exhibition) {
            $location = $exhibition->getLocation();
            if (is_null($location)) {
                continue;
            }

            array_push($exhibitionPlacesByCountry, $location->getPlace()->getCountryCode());
        }

        $exhibitionPlacesByCountryTotal = array_count_values($exhibitionPlacesByCountry);
        arsort($exhibitionPlacesByCountryTotal);

        $finalData = array_map(function ($key) use ($exhibitionPlacesByCountryTotal) {
                $name = '' === $key ? 'unknown' : Intl::getRegionBundle()->getCountryName($key);
                return [ 'name' => $name, 'y' => (int)$exhibitionPlacesByCountryTotal[$key]];
            },
            array_keys($exhibitionPlacesByCountryTotal));

        $sumOfAllExhibitions = array_sum(array_values($exhibitionPlacesByCountryTotal));

        return [ json_encode($finalData), $sumOfAllExhibitions, count(array_keys($exhibitionPlacesByCountryTotal)) ];
    }


    public function detailDataNumberOfExhibitionsPerCity($person)
    {
        $exhibitionPlaces = [];

        foreach ($person->getExhibitions(-1) as $exhibition) {
            $location = $exhibition->getLocation();
            if (is_null($location)) {
                continue;
            }

            array_push($exhibitionPlaces, $location->getPlaceLabel());
        }

        $exhibitionPlacesTotal = array_count_values($exhibitionPlaces);
        arsort($exhibitionPlacesTotal);

        $finalData = array_map(function ($key) use ($exhibitionPlacesTotal) {
                return [ 'name' => $key, 'y' => (int)$exhibitionPlacesTotal[$key]];
            },
            array_keys($exhibitionPlacesTotal));

        $sumOfAllExhibitions = array_sum(array_values($exhibitionPlacesTotal));

        return [ json_encode($finalData), $sumOfAllExhibitions, count(array_keys($exhibitionPlacesTotal)) ];
    }


    public function detailDataNumberOfExhibitionsPerYear($person)
    {
        $exhibitionYear = [];


        $exhibitions = $person->getExhibitions(-1);

        foreach ($exhibitions as $exhibition) {
            array_push($exhibitionYear, (int) date('Y', strtotime($exhibition->getStartDate())) );
        }

        $exhibitionYear = array_count_values ( $exhibitionYear );


        $yearsArray = array_keys($exhibitionYear);

        if (!empty($yearsArray)) {
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


    /**
     * @Route("/person/gnd/beacon", name="person-gnd-beacon")
     *
     * Provide a BEACON file as described in
     *  https://de.wikipedia.org/wiki/Wikipedia:BEACON
     */
    public function gndBeaconAction()
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
                        $this->generateUrl('person-index', [], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL))
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
}
