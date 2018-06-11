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
}