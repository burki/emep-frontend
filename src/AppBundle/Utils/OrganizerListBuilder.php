<?php

namespace AppBundle\Utils;

namespace AppBundle\Utils;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class OrganizerListBuilder
extends LocationListBuilder
{
    protected $alias = 'O'; // change from L so we can both join Venue and Organizer

    var $orders = [
        'place' => [
            'asc' => [
                'O.place',
                'O.name',
                'O.id',
            ],
            'desc' => [
                'O.place DESC',
                'O.name DESC',
                'O.id DESC',
            ],
        ],
        'location' => [
            'asc' => [
                'O.name',
                'O.place',
                'O.id',
            ],
            'desc' => [
                'O.name DESC',
                'O.place DESC',
                'O.id DESC',
            ],
        ],
        'type' => [
            'asc' => [
                'O.type IS NOT NULL DESC',
                'O.type',
                'O.place',
                'O.name',
                'O.id',
            ],
            'desc' => [
                'O.type IS NOT NULL',
                'O.type DESC',
                'O.place DESC',
                'O.name DESC',
                'O.id DESC',
            ],
        ],
        'count_exhibition' => [
            'desc' => [
                'count_exhibition DESC',
                'O.place',
                'O.name',
                'O.id',
            ],
            'asc' => [
                'count_exhibition',
                'O.place',
                'O.name',
                'O.id',
            ],
        ],
        'count_itemexhibition' => [
            'desc' => [
                'count_itemexhibition DESC',
                'O.place',
                'O.name',
                'O.id',
            ],
            'asc' => [
                'count_itemexhibition',
                'O.place',
                'O.name',
                'O.id',
            ],
        ],
    ];

    /**
     * @override
     */
    public function getEntity()
    {
        return 'Organizer';
    }

    protected function setExhibitionJoin($queryBuilder)
    {
        // Organizer joins to Exhibition through ExhibitionLocation
        $queryBuilder->innerJoin($this->alias,
                                'ExhibitionLocation', 'EL',
                                'EL.id_location=' . $this->alias . '.id AND EL.role = 0');
        $queryBuilder->innerJoin('EL',
                                'Exhibition', 'E',
                                'EL.id_exhibition=E.id AND E.status <> -1');
    }

    protected function buildSelectExhibitionCount()
    {
        return 'COUNT(DISTINCT E.id) AS count_exhibition';
    }

    protected function setFilter($queryBuilder)
    {
        parent::setFilter($queryBuilder);

        if (array_key_exists('location', $this->queryFilters)) {
            // so we can filter on L.*
            $queryBuilder->leftJoin('E',
                                    'Location', 'L',
                                    'E.id_location=L.id AND L.status <> -1');
            $queryBuilder->leftJoin('L',
                                    'Geoname', 'PL',
                                    'L.place_tgn=PL.tgn');
        }

        return $this;
    }
}
