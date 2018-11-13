<?php

namespace AppBundle\Utils;

namespace AppBundle\Utils;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class VenueListBuilder
extends LocationListBuilder
{
    /**
     * @override
     */
    public function getEntity()
    {
        return 'Venue';
    }

    protected function setFilter($queryBuilder)
	{
		parent::setFilter($queryBuilder);

		if (array_key_exists('organizer', $this->queryFilters)) {
			// so we can filter on O.*
			$queryBuilder->innerJoin('E',
									'ExhibitionLocation', 'EL',
									'EL.id_exhibition=E.id AND E.status <> -1');
			$queryBuilder->innerJoin('EL',
									'Location', 'O',
									'EL.id_location=O.id AND EL.role = 0');

			/*
			$queryBuilder->leftJoin('O',
									'Geoname', 'PO',
									'L.place_tgn=P=.tgn');
			*/
		}

		return $this;
	}
}
