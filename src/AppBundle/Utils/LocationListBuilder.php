<?php

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
            'label' => '# of Exhibitions',
        ],
        'count_itemexhibition' => [
            'label' => '# of Cat. Entries',
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
                                $mode = '')
    {
        $this->mode = $mode;

        parent::__construct($connection, $request, $urlGenerator, $queryFilters);

        if ('stats-type' == $this->mode) {
            $this->orders = [ 'default' => [ 'asc' => [ 'type' ] ] ];
        }
        else if ('stats-country' == $this->mode) {
            $this->orders = [ 'default' => [ 'asc' => [ 'country_code' ] ] ];
        }
        else if ('extended' == $this->mode) {
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
                    'label' => '# of Exhibitions',
                ],
                'count_itemexhibition' => [
                    'label' => '# of Cat. Entries',
                ],
                'status' => [
                    'label' => 'Status',
                    'buildValue' => function (&$row, $val, $listBuilder, $key, $format) {
                        return $this->formatRowValue($listBuilder->buildStatusLabel($val), [], $format);
                    },
                ],
            ];
        }

        $entity = $this->entity;
        $this->rowDescr['location']['buildValue'] = function (&$row, $val, $listBuilder, $key, $format) use ($entity) {
            return $entity == $this->entity
                ? $listBuilder->buildLinkedOrganizer($row, $val, $format)
                : $listBuilder->buildLinkedLocation($row, $val, $format);
        };

        $this->rowDescr['place']['buildValue'] = function (&$row, $val, $listBuilder, $key, $format) {
            if (empty($row['place_tgn'])) {
                return false;
            }

            return $listBuilder->buildLinkedValue($val, 'place-by-tgn', [ 'tgn' => $row['place_tgn'] ], $format);
        };

        if (empty($mode)) {
            $routeParams = [
                'entity' => 'Exhibition',
                'filter' => $this->getQueryFilters(true),
            ];

            if (empty($routeParams['filter']['search'])) {
                $this->rowDescr['count_exhibition']['buildValue']
                    = function (&$row, $val, $listBuilder, $key, $format) use ($routeParams) {
                        if ('html' != $format) {
                            return false;
                        }

                        $key = 'Organizer' == $this->getEntity() ? 'organizer' : 'location';

                        $routeParams['filter'][$key][$key] = [ $row['id'] ];

                        return sprintf('<a href="%s">%s</a>',
                                       $this->urlGenerator->generate('search-index', $routeParams),
                                       $this->formatRowValue($val, [], $format));
                    };

                $routeParams = [
                    'entity' => 'ItemExhibition',
                    'filter' => $this->getQueryFilters(true),
                ];

                $this->rowDescr['count_itemexhibition']['buildValue']
                    = function (&$row, $val, $listBuilder, $key, $format) use ($routeParams, $entity) {
                        if ('html' != $format) {
                            return false;
                        }

                        $key = 'Organizer' == $this->getEntity() ? 'organizer' : 'location';

                        $routeParams['filter'][$key][$key] = [ $row['id'] ];

                        return sprintf('<a href="%s">%s</a>',
                                       $this->urlGenerator->generate('search-index', $routeParams),
                                       $this->formatRowValue($val, [], $format));
                    };
            }
        }
    }

    /*
     * DEPRECATED: Does not work correctly for Venue with filters
     */
    protected function buildSelectExhibitionCount()
    {
        return '(SELECT COUNT(*) FROM Exhibition EC WHERE EC.id_location='
            . $this->alias . '.id AND EC.status <> -1) AS count_exhibition';
    }

    protected function setSelect($queryBuilder)
    {
        if ('stats-type' == $this->mode) {
            $queryBuilder->select([
                $this->alias . '.type AS type',
                'COUNT(DISTINCT ' . $this->alias . '.id) AS how_many',
            ]);

            return $this;
        }

        if ('stats-country' == $this->mode) {
            $queryBuilder->select([
                'P' . $this->alias . '.country_code AS country_code',
                'COUNT(DISTINCT ' . $this->alias . '.id) AS how_many',
            ]);

            return $this;
        }

        $queryBuilder->select([
            'SQL_CALC_FOUND_ROWS ' . $this->alias . '.id',
            $this->alias . '.id AS location_id',
            $this->alias . '.name AS location',
            $this->alias . '.name_alternate AS location_alternate',
            $this->alias . '.name_translit AS location_translit',
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
            'COUNT(DISTINCT E.id) AS count_exhibition',  // $this->buildSelectExhibitionCount(),
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
        if ('stats-type' == $this->mode) {
            $queryBuilder->groupBy($this->alias . '.type');
        }
        else if ('stats-country' == $this->mode) {
            $queryBuilder->groupBy('country_code');
        }
        else {
            $queryBuilder->groupBy($this->alias . '.id');
        }

        $queryBuilder->leftJoin($this->alias,
                                'Geoname', 'P' . $this->alias,
                                'P' . $this->alias . '.tgn=' . $this->alias . '.place_tgn');

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

	public function getAlias()
	{
		return $this->alias;
	}
}
