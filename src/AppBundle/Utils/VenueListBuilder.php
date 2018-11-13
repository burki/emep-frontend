<?php

namespace AppBundle\Utils;

namespace AppBundle\Utils;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class VenueListBuilder
extends SearchListBuilder
{
    protected $entity = 'Location';

    var $rowDescr = [
        'location' => [
            'label' => 'Name',
        ],
        'place' => [
            'label' => 'City',
        ],
        'type' => [
            'label' => 'Type',
        ],
        'count_exhibition' => [
            'label' => 'Number of Exhibitions',
        ],
        'count_itemexhibition' => [
            'label' => 'Number of Cat. Entries',
        ],
    ];

    var $orders = [
        'place' => [
            'asc' => [
                'L.place',
                'L.name',
                'L.id',
            ],
            'desc' => [
                'L.place DESC',
                'L.name DESC',
                'L.id DESC',
            ],
        ],
        'location' => [
            'asc' => [
                'L.name',
                'L.place',
                'L.id',
            ],
            'desc' => [
                'L.name DESC',
                'L.place DESC',
                'L.id DESC',
            ],
        ],
        'type' => [
            'asc' => [
                'L.type IS NOT NULL DESC',
                'L.type',
                'L.place',
                'L.name',
                'L.id',
            ],
            'desc' => [
                'L.type IS NOT NULL',
                'L.type DESC',
                'L.place DESC',
                'L.name DESC',
                'L.id DESC',
            ],
        ],
        'count_exhibition' => [
            'desc' => [
                'count_exhibition DESC',
                'L.place',
                'L.name',
                'L.id',
            ],
            'asc' => [
                'count_exhibition',
                'L.place',
                'L.name',
                'L.id',
            ],
        ],
        'count_itemexhibition' => [
            'desc' => [
                'count_itemexhibition DESC',
                'L.place',
                'L.name',
                'L.id',
            ],
            'asc' => [
                'count_itemexhibition',
                'L.place',
                'L.name',
                'L.id',
            ],
        ],
    ];

    public function __construct(\Doctrine\DBAL\Connection $connection,
                                Request $request,
                                UrlGeneratorInterface $urlGenerator,
                                $queryFilters = null,
                                $extended = false)
    {
        parent::__construct($connection, $request, $urlGenerator, $queryFilters);

        if ($extended) {
            $this->rowDescr = [
                'location_id' => [
                    'label' => 'ID',
                ],
                'location' => [
                    'label' => 'Name',
                ],
                'place' => [
                    'label' => 'Place',
                ],
                'country_code' => [
                    'label' => 'Country Code',
                ],
                'type' => [
                    'label' => 'Type',
                ],
                'foundingdate' => [
                    'label' => 'Founding Date',
                ],
                'dissolutiondate' => [
                    'label' => 'Dissolution Date',
                ],
                'ulan' => [
                    'label' => 'ULAN',
                ],
                'gnd' => [
                    'label' => 'GND',
                ],
                'count_exhibition' => [
                    'label' => 'Number of Exhibitions',
                ],
                'count_itemexhibition' => [
                    'label' => 'Number of Cat. Entries',
                ],
                'status' => [
                    'label' => 'Status',
                    'buildValue' => function (&$row, $val, $listBuilder, $key, $format) {
                        return $this->formatRowValue($listBuilder->buildStatusLabel($val), [], $format);
                    },
                ],
            ];
        }

        $this->rowDescr['location']['buildValue'] = function (&$row, $val, $listBuilder, $key, $format) {
            return $listBuilder->buildLinkedValue($val, 'location', [ 'id' => $row['id'] ], $format);
        };

        $this->rowDescr['place']['buildValue'] = function (&$row, $val, $listBuilder, $key, $format) {
            if (empty($row['place_tgn'])) {
                return false;
            }

            return $listBuilder->buildLinkedValue($val, 'place-by-tgn', [ 'tgn' => $row['place_tgn'] ], $format);
        };
    }

    /**
     * @override
     */
    public function getEntity()
    {
        return 'Venue';
    }

    protected function setSelect($queryBuilder)
    {
        $queryBuilder->select([
            'SQL_CALC_FOUND_ROWS L.id',
            'L.id AS location_id',
            'L.name AS location',
            'L.place AS place',
            'L.place_tgn AS place_tgn',
            'PL.country_code AS country_code',
            'DATE(L.foundingdate) AS foundingdate',
            'DATE(L.dissolutiondate) AS dissolutiondate',
            'L.gnd AS gnd', 'L.ulan AS ulan',
            'L.type AS type',
            'L.status AS status',
            'L.place_geo',
            'PL.latitude', 'PL.longitude',
            'COUNT(DISTINCT IE.id) AS count_itemexhibition',
            '(SELECT COUNT(*) FROM Exhibition EC WHERE EC.id_location=L.id AND EC.status <> -1) AS count_exhibition',
        ]);

        return $this;
    }

    protected function setFrom($queryBuilder)
    {
        $queryBuilder->from('Location', 'L');

        return $this;
    }

    protected function setJoin($queryBuilder)
    {
        $queryBuilder->groupBy('L.id');

        $queryBuilder->leftJoin('L',
                                'Geoname', 'PL',
                                'PL.tgn=L.place_tgn');

        $queryBuilder->leftJoin('L',
                                'Exhibition', 'E',
                                'E.id_location=L.id AND E.status <> -1');

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
        // don't show organizer-only
        $queryBuilder->andWhere('L.status <> -1 AND 0 = (L.flags & 256)');

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
