<?php

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

        // don't show organizer-only
        $queryBuilder->andWhere(sprintf('0 = (%s.flags & 256)',
                                        $this->alias));

        if (array_key_exists('organizer', $this->queryFilters)) {
            // so we can filter on O.*
            $queryBuilder->innerJoin('E',
                                    'ExhibitionLocation', 'EL',
                                    'EL.id_exhibition=E.id AND ' . $this->buildExhibitionVisibleCondition('E'));
            $queryBuilder->innerJoin('EL',
                                    'Location', 'O',
                                    'EL.id_location=O.id AND EL.role = 0');

            // so we can filter on PO.*
            $queryBuilder->leftJoin('O',
                                    'Geoname', 'PO',
                                    'O.place_tgn=PO.tgn');
        }

        return $this;
    }
}
