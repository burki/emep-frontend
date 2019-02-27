<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Intl\Intl;
use Symfony\Component\Security\Core\User\UserInterface;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;


/**
 *
 */
abstract class CrudController
extends Controller
{
    protected $pageSize = 50;

    static function array_filter_recursive($array, $callback = null, $remove_empty_arrays = false)
    {
        foreach ($array as $key => & $value) { // mind the reference
            if (is_array($value)) {
                $value = self::array_filter_recursive($value, $callback, $remove_empty_arrays);
                if ($remove_empty_arrays && ! (bool) $value) {
                    unset($array[$key]);
                }
            }
            else {
                if (!is_null($callback) && ! $callback($value)) {
                    unset($array[$key]);
                }
                elseif ('' === $value || ! (bool) $value) {
                    unset($array[$key]);
                }
            }
        }
        unset($value); // kill the reference

        return $array;
    }

    protected function buildActiveCountries($qb)
    {
        $countriesActive = [];

        foreach ($qb->getQuery()->getResult() as $result) {
            $countryCode = $result['countryCode'];
            $countriesActive[$countryCode] = Intl::getRegionBundle()->getCountryName($countryCode);
        }

        asort($countriesActive);

        return $countriesActive;
    }

    /**
     *
     */
    protected function buildPersonNationalities()
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

    protected function buildVenueTypes()
    {
        $em = $this->getDoctrine()
            ->getManager();
        $result = $em->createQuery('SELECT DISTINCT L.type FROM AppBundle:Location L'
            . ' WHERE L.status <> -1 AND 0 = BIT_AND(L.flags, 256) AND L.type IS NOT NULL'
            . ' ORDER BY L.type')
            ->getScalarResult();

        return array_column($result, 'type');
    }

    /* TODO: should be renamed to buildExhibitionOrganizerTypes */
    protected function buildOrganizerTypes()
    {
        $em = $this->getDoctrine()
            ->getManager();
        $result = $em->createQuery("SELECT DISTINCT E.organizerType AS type FROM AppBundle:Exhibition E"
            . " WHERE E.status <> -1 AND E.organizerType <> ''"
            . " ORDER BY type")
            ->getScalarResult();

        return array_column($result, 'type');
    }

    /**
     * Get all countries and places
     */
    protected function buildVenueGeonames()
    {
        $geonames = [];

        $qb = $this->getDoctrine()
                ->getManager()
                ->createQueryBuilder();

        $qb->select([
                'PL.countryCode',
                'C.name AS country',
                'PL.tgn',
                'COALESCE(PL.alternateName,PL.name) AS name'
            ])
            ->distinct()
            ->from('AppBundle:Location', 'L')
            ->leftJoin('L.place', 'PL')
            ->leftJoin('PL.country', 'C')
            ->where('L.status <> -1 AND 0 = BIT_AND(L.flags, 256) AND PL.countryCode IS NOT NULL')
            ->orderBy('country, name')
            ;

        $lastCountryCode = '';

        foreach ($qb->getQuery()->getResult() as $result) {
            if ($lastCountryCode != $result['countryCode']) {
                $key = 'cc:' . $result['countryCode'];
                $geonames[$key] = $result['country'];
            }

            $key = 'tgn:' . $result['tgn'];
            $geonames[$key] = "\xC2\xA0\xC2\xA0\xC2\xA0\xC2\xA0" . $result['name'];
        }

        return $geonames;
    }

    /**
     * Get all countries and places
     */
    protected function buildOrganizerGeonames(Request $request, UrlGeneratorInterface $urlGenerator)
    {
        $organizerListBuilder = new \AppBundle\Utils\OrganizerListBuilder($conn = $this->getDoctrine()->getEntityManager()->getConnection(), $request, $urlGenerator);
        $alias = $organizerListBuilder->getAlias();

        $queryBuilder = $conn->createQueryBuilder();

        $queryBuilder->select([
                'DISTINCT C.cc AS countryCode',
                'C.name AS country',
                'P' . $alias . '.tgn',
                'COALESCE(P' . $alias . '.name_alternate,P' . $alias . '.name) AS name',
            ])
            ->from('Location', $alias)
            ->innerJoin($alias,
                                'Geoname', 'P' . $alias,
                                'P' . $alias . '.tgn=' . $alias.'.place_tgn')
            ->innerJoin('P' . $alias,
                                'Country', 'C',
                                'P' . $alias . '.country_code=' . 'C.cc')
            ->innerJoin($alias,
                                'ExhibitionLocation', 'EL',
                                'EL.id_location=' . $alias . '.id AND EL.role = 0')
            ->innerJoin('EL',
                                'Exhibition', 'E',
                                'EL.id_exhibition=E.id AND E.status <> -1')
            ->orderBy('country, place')
            ;

        // die($queryBuilder->getSql());


        $geonames = [];


        $lastCountryCode = '';

        foreach ($queryBuilder->execute()->fetchAll() as $result) {
            if ($lastCountryCode != $result['countryCode']) {
                $key = 'cc:' . $result['countryCode'];
                $geonames[$key] = $result['country'];
            }

            $key = 'tgn:' . $result['tgn'];
            $geonames[$key] = "\xC2\xA0\xC2\xA0\xC2\xA0\xC2\xA0" . $result['name'];
        }

        return $geonames;
    }

    /**
     * Get all countries
     */
    protected function buildHolderGeonames()
    {
        $geonames = [];

        $qb = $this->getDoctrine()
                ->getManager()
                ->createQueryBuilder();

        $qb->select([
                'H.countryCode',
                'H.countryCode AS country',
            ])
            ->distinct()
            ->from('AppBundle:Holder', 'H')
            ->where('H.status <> -1')
            ->orderBy('country')
            ;

        $lastCountryCode = '';

        foreach ($qb->getQuery()->getResult() as $result) {
            if ($lastCountryCode != $result['countryCode']) {
                $key = 'cc:' . $result['countryCode'];
                $geonames[$key] = $result['country'];
            }
        }

        return $geonames;
    }

    protected function assoc2NameYArray($keyValue) {
        $ret = [];

        foreach ($keyValue as $key => $value) {
            $ret[] = [ 'name' => $key, 'y' => (float)$value ];
        }

        return $ret;
    }

    /**
     * Checks if a saved search is requested and if so, looks it up and redirects
     */
    protected function handleUserAction(Request $request, UserInterface $user = null)
    {
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
                        'route' => $request->get('_route'),
                    ]);

                if (!is_null($userAction)) {
                    return $this->redirectToRoute($userAction->getRoute(),
                                                  $userAction->getRouteParams());
                }
            }
        }
    }

    protected function expandSaveSearchRoute($route)
    {
        if (!preg_match('/\-index$/', $route)) {
            // we only save the base, so append the -index
            $route .= '-index';
        }

        return $route;
    }

    /*
     * Saves a search
     */
    protected function handleSaveSearchAction(Request $request,
                                              UrlGeneratorInterface $urlGenerator,
                                              UserInterface $user)
    {
        list($route, $routeParams) = $this->buildSaveSearchParams($request, $urlGenerator);

        if (empty($routeParams)) {
            // nothing to save
            return $this->redirectToRoute($this->expandSaveSearchRoute($route), $routeParams);
        }

        $form = $this->createForm(\AppBundle\Form\Type\SaveSearchType::class);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $userAction = new \AppBundle\Entity\UserAction();

            $userAction->setUser($user);
            $userAction->setRoute($route);
            $userAction->setRouteParams($routeParams);

            $userAction->setName($data['name']);

            $em = $this->getDoctrine()
                ->getManager();

            $em->persist($userAction);
            $em->flush();

            return $this->redirectToRoute($this->expandSaveSearchRoute($route), $routeParams);
        }

        return $this->render('User/save.html.twig', [
            'pageTitle' => $this->get('translator')->trans('Save your query'),
            'form' => $form->createView(),
        ]);
    }

    /**
     *
     */
    protected function buildItemExhibitionTypes()
    {
        $conn = $this->getDoctrine()->getEntityManager()->getConnection();

        $queryBuilder = $conn->createQueryBuilder();
        $queryBuilder->select('DISTINCT IE.type')
            ->from('ItemExhibition', 'IE')
            ->where('IE.type IS NOT NULL')
            ;

        $termIds = $queryBuilder->execute()->fetchAll(\PDO::FETCH_COLUMN);

        $queryBuilder = $conn->createQueryBuilder();
        $queryBuilder->select('T.id, T.name')
            ->from('Term', 'T')
            ->where('T.id IN (:ids)')
            ->setParameter('ids', $termIds, \Doctrine\DBAL\Connection::PARAM_INT_ARRAY)
            ->orderBy('T.name')
            ;

        $types = [];

        foreach ($queryBuilder->execute()->fetchAll() as $row)  {
            if ('0_unknown' == $row['name']) {
                continue;
            }
            $types[$row['id']] = $row['name'];
        }

        return $types;
    }

    /*
     * Looks up saved searches of a $user for a specific route
     */
    protected function lookupSearches($user, $routeName)
    {
        if (is_null($user)) {
            return [];
        }

        $qb = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();
        $qb->select('UA')
            ->from('AppBundle:UserAction', 'UA')
            ->where("UA.route = :route")
            ->andWhere("UA.user = :user")
            ->orderBy("UA.createdAt", "DESC")
            ->setParameter('route', $routeName)
            ->setParameter('user', $user)
            ;

        $searches = [];

        foreach ($qb->getQuery()->getResult() as $userAction) {
            $searches[$userAction->getId()] = $userAction->getName();
        }

        return $searches;
    }

    protected function buildPagination(Request $request, $query, $options = [])
    {
        $paginator = $this->get('knp_paginator');

        $limit = $this->pageSize;
        if (array_key_exists('pageSize', $options)) {
            $limit = $options['pageSize'];
        }

        return $paginator->paginate(
            $query, // query, NOT result
            $request->query->getInt('page', 1), // page number
            $limit, // limit per page
            $options
        );
    }

    protected function createSearchForm(Request $request, $urlGenerator)
    {
        $venueTypes = $this->buildVenueTypes();
        $exhibitionTypes = [ 'group', 'solo', 'auction' ];
        $exhibitionOrganizerTypes = $this->buildOrganizerTypes();

        return $this->createForm(\AppBundle\Form\Type\SearchFilterType::class, [
            'choices' => [
                'nationality' => array_flip($this->buildPersonNationalities()),
                'location_geoname' => array_flip($this->buildVenueGeonames()),
                'location_type' => array_combine($venueTypes, $venueTypes),
                'organizer_geoname' => array_flip($this->buildOrganizerGeonames($request, $urlGenerator)),
                'organizer_type' => array_combine($venueTypes, $venueTypes),
                'holder_geoname' => array_flip($this->buildHolderGeonames()),
                'exhibition_type' => array_combine($exhibitionTypes, $exhibitionTypes),
                'exhibition_organizer_type' => array_combine($exhibitionOrganizerTypes, $exhibitionOrganizerTypes),
                'itemexhibition_type' => array_flip($this->buildItemExhibitionTypes()),
            ],
        ]);
    }

    protected function getSearchFilters($request, $form)
    {
        $parameters = $request->query->all();
        $parameters = self::array_filter_recursive($parameters, null, true); // remove empty values

        if (array_key_exists('filter', $parameters)) {
            // some values must be arrays and not scalar as in Basic Search
            $forceArray = [
                'location' => [ 'geoname' ],
                'organizer' => [ 'geoname' ],
                'holder' => [ 'geoname' ],
                'exhibition' => [ 'organizer_type' ],
            ];

            foreach ($forceArray as $group => $fields) {
                if (!array_key_exists($group, $parameters['filter'])) {
                    continue;
                }

                foreach ($fields as $field) {
                    if (array_key_exists($field, $parameters['filter'][$group]) && !is_array($parameters['filter'][$group][$field])) {
                        $parameters['filter'][$group][$field] = [ $parameters['filter'][$group][$field] ];
                    }
                }
            }

            $form->submit($parameters['filter']);
        }

        $filters = $form->getData();

        return $filters;
    }


    protected function instantiateListBuilder(Request $request,
                                              UrlGeneratorInterface $urlGenerator,
                                              $mode = false,
                                              $entity = null)
    {
        $connection = $this->getDoctrine()->getEntityManager()->getConnection();

        $parameters = $request->query->all();
        $parameters = self::array_filter_recursive($parameters, null, true); // remove empty values

        if (array_key_exists('filter', $parameters)) {
            static $submitCalled = false; // buildExhibitionCharts calls instantiateListBuilder multiple times

            if (!$submitCalled) {
                // var_dump($parameters['filter']);
                $this->form->submit($parameters['filter']);
                $submitCalled = true;
            }
        }

        $filters = $this->form->getData();

        switch ($entity) {
            case 'Venue':
                return new \AppBundle\Utils\VenueListBuilder($connection, $request, $urlGenerator, $filters, $mode);
                break;

            case 'Organizer':
                return new \AppBundle\Utils\OrganizerListBuilder($connection, $request, $urlGenerator, $filters, $mode);
                break;

            case 'Holder':
                return new \AppBundle\Utils\HolderListBuilder($connection, $request, $urlGenerator, $filters, $mode);
                break;

            case 'Person':
                return new \AppBundle\Utils\PersonListBuilder($connection, $request, $urlGenerator, $filters, $mode);
                break;

            case 'ItemExhibition':
                return new \AppBundle\Utils\ItemExhibitionListBuilder($connection, $request, $urlGenerator, $filters, $mode);

            case 'Exhibition':
            default:
                return new \AppBundle\Utils\ExhibitionListBuilder($connection, $request, $urlGenerator, $filters, $mode);
                break;
        }
    }

    protected function hydrateExhibitions($ids, $preserveOrder = false)
    {
        // hydrate with doctrine entity
        $qb = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();
        $hydrationQuery = $qb->select([ 'E', 'field(E.id, :ids) as HIDDEN field', 'E.startdate HIDDEN dateSort' ])
            ->from('AppBundle:Exhibition', 'E')
            ->where('E.id IN (:ids)')
            ->orderBy($preserveOrder ? 'field' : 'dateSort')
            ->getQuery();
            ;

        $hydrationQuery->setParameter('ids', $ids);

        return $hydrationQuery->getResult();
    }

    protected function hydratePersons($ids, $preserveOrder = false)
    {
        // hydrate with doctrine entity
        $qb = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();
        $hydrationQuery = $qb->select([ 'P', 'field(P.id, :ids) as HIDDEN field', 'P.sortName HIDDEN nameSort' ])
            ->from('AppBundle:Person', 'P')
            ->where('P.id IN (:ids)')
            ->orderBy($preserveOrder ? 'field' : 'nameSort')
            ->getQuery();
            ;

        $hydrationQuery->setParameter('ids', $ids);

        return $hydrationQuery->getResult();
    }

    protected  function hydrateWorks($ids, $preserveOrder = false){
        // hydrate with doctrine entity
        $qb = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();

        $hydrationQuery = $qb->select([ 'IE', 'field(IE.id, :ids) as HIDDEN field', 'IE.title as nameSort', 'IE.titleTransliterated','IE.measurements', 'IE.technique', 'IE.forsale', 'IE.price', 'IE.owner' ])
            ->from('AppBundle:ItemExhibition', 'IE')
            ->where('IE.id IN (:ids)')
            ->orderBy($preserveOrder ? 'field' : 'nameSort')
            ->getQuery();
        ;


        $hydrationQuery->setParameter('ids', $ids);


        return $hydrationQuery->getResult();
    }

    protected function instantiateCiteProc($locale)
    {
        $kernel = $this->get('kernel');
        $path = $kernel->locateResource('@AppBundle/Resources/csl/infoclio-de.csl.xml');

        return new \AcademicPuma\CiteProc\CiteProc(file_get_contents($path), $locale);
    }
}
