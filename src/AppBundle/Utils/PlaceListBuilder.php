<?php

namespace AppBundle\Utils;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PlaceListBuilder
extends SearchListBuilder
{
    protected $entity = 'Place';
    protected $alias = 'PL';

    var $rowDescr = [
        'place' => [
            'label' => 'City',
        ],
        'country' => [
            'label' => 'Country',
        ],
        'count_location' => [
            'label' => '# of Venues',
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
                'PL.name',
                'PL.tgn',
            ],
            'desc' => [
                'PL.name DESC',
                'PL.tgn DESC',
            ],
        ],
        'country' => [
            'asc' => [
                'C.name',
                'PL.name',
                'PL.tgn',
            ],
            'desc' => [
                'C.name DESC',
                'PL.name DESC',
                'PL.tgn DESC',
            ],
        ],
        'count_location' => [
            'desc' => [
                'count_location DESC',
                'PL.name',
                'PL.tgn',
            ],
            'asc' => [
                'count_location',
                'PL.name',
                'PL.tgn',
            ],
        ],
        'count_exhibition' => [
            'desc' => [
                'count_exhibition DESC',
                'PL.name',
                'PL.tgn',
            ],
            'asc' => [
                'count_exhibition',
                'PL.name',
                'PL.tgn',
            ],
        ],
        'count_itemexhibition' => [
            'desc' => [
                'count_itemexhibition DESC',
                'PL.name',
                'PL.tgn',
            ],
            'asc' => [
                'count_itemexhibition',
                'PL.name',
                'PL.tgn',
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
                'place_tgn' => [
                    'label' => 'TGN',
                ],
                'place' => [
                    'label' => 'City',
                ],
                'country' => [
                    'label' => 'Country',
                ],
                'country_code' => [
                    'label' => 'Country Code',
                ],
                'latitude' => [
                    'label' => 'Latitude',
                ],
                'longitude' => [
                    'label' => 'Longitude',
                ],
                'count_location' => [
                    'label' => '# of Venues',
                ],
                'count_exhibition' => [
                    'label' => '# of Exhibitions',
                ],
                'count_itemexhibition' => [
                    'label' => '# of Cat. Entries',
                ],
            ];
        }

        $entity = $this->entity;

        $this->rowDescr['place']['buildValue'] = function (&$row, $val, $listBuilder, $key, $format) {
            if (empty($row['place_tgn'])) {
                return false;
            }

            return $listBuilder->buildLinkedValue($val, 'place-by-tgn', [ 'tgn' => $row['place_tgn'] ], $format);
        };

        /*
        if (empty($mode)) {
            $routeParams = [
                'entity' => 'Place',
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
        */
    }

    protected function setSelect($queryBuilder)
    {
        if ('stats-country' == $this->mode) {
            $queryBuilder->select([
                $this->alias . '.country_code AS country_code',
                'COUNT(DISTINCT ' . $this->alias . '.tgn) AS how_many',
            ]);

            return $this;
        }

        $queryBuilder->select([
            'SQL_CALC_FOUND_ROWS ' . $this->alias . '.tgn',
            $this->alias . '.tgn AS place_id',
            $this->alias . '.name AS place',
            $this->alias . '.tgn AS place_tgn',
            $this->alias . '.country_code AS country_code',
            'C.name AS country',
            $this->alias . '.latitude',
            $this->alias . '.longitude',
            'COUNT(DISTINCT L.id) AS count_location',
            'COUNT(DISTINCT IE.id) AS count_itemexhibition',
            'COUNT(DISTINCT E.id) AS count_exhibition',  // $this->buildSelectExhibitionCount(),
        ]);

        return $this;
    }

    protected function setFrom($queryBuilder)
    {
        $queryBuilder->from('Geoname', $this->alias);

        return $this;
    }

    protected function setExhibitionJoin($queryBuilder)
    {
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
                                'Country', 'C',
                                $this->alias . '.country_code=C.cc');

        $queryBuilder->innerJoin($this->alias,
                                'Location', 'L',
                                $this->alias . '.tgn=' . 'L.place_tgn');

        $queryBuilder->innerJoin('L',
                                'Exhibition', 'E',
                                'E.id_location=L.id AND ' . $this->buildExhibitionVisibleCondition('E'));

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
            $this->alias . '.name_alternate',
            $this->alias . '.tgn',
            'C.name',
        ]);

        $this->addQueryFilters($queryBuilder);

        return $this;
    }

	public function getAlias()
	{
		return $this->alias;
	}
}
