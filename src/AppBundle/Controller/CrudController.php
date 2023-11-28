<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Symfony\Component\Intl\Intl;

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

    protected function instantiateCiteProc($locale)
    {
        $kernel = $this->get('kernel');
        $path = $kernel->locateResource('@AppBundle/Resources/csl/infoclio-de.csl.xml');

        return new \Seboettg\CiteProc\CiteProc(file_get_contents($path), $locale);
    }
}