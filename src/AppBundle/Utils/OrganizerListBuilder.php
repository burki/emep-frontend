<?php

namespace AppBundle\Utils;

namespace AppBundle\Utils;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class OrganizerListBuilder
extends LocationListBuilder
{
    /**
     * @override
     */
    public function getEntity()
    {
        return 'Organizer';
    }

    protected function setJoin($queryBuilder)
    {
        $queryBuilder->groupBy('L.id');

        $queryBuilder->leftJoin('L',
                                'Geoname', 'PL',
                                'PL.tgn=L.place_tgn');

		// Organizer joins to Exhibition through ExhibitionLocation
        $queryBuilder->innerJoin('L',
                                'ExhibitionLocation', 'EL',
                                'EL.id_location=L.id AND EL.role = 0');
        $queryBuilder->innerJoin('EL',
                                'Exhibition', 'E',
                                'EL.id_exhibition=E.id AND E.status <> -1');

        $queryBuilder->leftJoin('E',
                                'ItemExhibition', 'IE',
                                'E.id=IE.id_exhibition AND (IE.title IS NOT NULL OR IE.id_item IS NULL)');

        if (array_key_exists('person', $this->queryFilters)) {
            // so we can filter on P.*
            $queryBuilder->join('IE',
                                    'Person', 'P',
                                    'P.id=IE.id_person AND P.status <> -1');
        }

        return $this;
    }

    protected function setFilter($queryBuilder)
    {
        $this->addSearchFilters($queryBuilder, [
            'L.name',
            'L.name_translit',
            'L.name_alternate',
            'L.gnd',
            'L.ulan',
            'L.place',
        ]);

        $this->addQueryFilters($queryBuilder);

        return $this;
    }
}
