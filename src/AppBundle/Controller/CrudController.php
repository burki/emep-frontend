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
        $result = $em->createQuery("SELECT DISTINCT L.type FROM AppBundle:Location L"
            . " WHERE L.status <> -1 AND 0 = BIT_AND(L.flags, 256) AND L.type IS NOT NULL"
            . " ORDER BY L.type")
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

    protected function handleSaveSearchAction(Request $request,
                                              UrlGeneratorInterface $urlGenerator,
                                              UserInterface $user)
    {
        list($route, $routeParams) = $this->buildSaveSearchParams($request, $urlGenerator);

        if (empty($routeParams)) {
            // nothing to save
            return $this->redirectToRoute($route, $routeParams);
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

            return $this->redirectToRoute($route, $routeParams);
        }

        return $this->render('User/save.html.twig', [
            'pageTitle' => $this->get('translator')->trans('Save your query'),
            'form' => $form->createView(),
        ]);
    }

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

    protected function buildPagination($request, $query, $options = [])
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
