<?php

namespace AppBundle\Utils;

namespace AppBundle\Utils;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

abstract class LocationListBuilder
extends SearchListBuilder
{
    protected $entity = 'Location';
    protected $alias = 'L';

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

    protected function buildSelectExhibitionCount()
    {
        return '(SELECT COUNT(*) FROM Exhibition EC WHERE EC.id_location='
            . $this->alias . '.id AND EC.status <> -1) AS count_exhibition';
    }

    protected function setSelect($queryBuilder)
    {
        $queryBuilder->select([
            'SQL_CALC_FOUND_ROWS ' . $this->alias . '.id',
            $this->alias . '.id AS location_id',
            $this->alias . '.name AS location',
            $this->alias . '.place AS place',
            $this->alias . '.place_tgn AS place_tgn',
            'P' . $this->alias . '.country_code AS country_code',
            'DATE(' . $this->alias . '.foundingdate) AS foundingdate',
            'DATE(' . $this->alias . '.dissolutiondate) AS dissolutiondate',
            $this->alias . '.gnd AS gnd',
            $this->alias . '.ulan AS ulan',
            $this->alias . '.type AS type',
            $this->alias . '.status AS status',
            $this->alias . '.place_geo',
            'P' . $this->alias . '.latitude',
            'P' . $this->alias . '.longitude',
            'COUNT(DISTINCT IE.id) AS count_itemexhibition',
            $this->buildSelectExhibitionCount()
        ]);

        return $this;
    }

    protected function setFrom($queryBuilder)
    {
        $queryBuilder->from('Location', $this->alias);

        return $this;
    }

    protected function setExhibitionJoin($queryBuilder)
    {
        $queryBuilder->leftJoin($this->alias,
                                'Exhibition', 'E',
                                'E.id_location=' . $this->alias . '.id AND E.status <> -1');
    }

    protected function setJoin($queryBuilder)
    {
        $queryBuilder->groupBy($this->alias . '.id');

        $queryBuilder->leftJoin($this->alias,
                                'Geoname', 'P' . $this->alias,
                                'P' . $this->alias . '.tgn=' . $this->alias.'.place_tgn');

        $this->setExhibitionJoin($queryBuilder);

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
        $queryBuilder->andWhere($this->alias . '.status <> -1');

        $this->addSearchFilters($queryBuilder, [
            $this->alias . '.name',
            $this->alias . '.name_translit',
            $this->alias . '.name_alternate',
            $this->alias . '.gnd',
            $this->alias . '.ulan',
            $this->alias . '.place',
        ]);

        $this->addQueryFilters($queryBuilder);

        return $this;
    }
}
