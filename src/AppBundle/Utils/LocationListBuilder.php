<?php

namespace AppBundle\Utils;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

abstract class LocationListBuilder
extends SearchListBuilder
{
    protected $entity = 'Location';
    protected $alias = 'L';

    /* TODO: share across entities and use logic in setJoin to build the query */
    static function fetchDateModified(\Doctrine\DBAL\Connection $connection, $id)
    {
        $querystr = "SELECT GREATEST(MAX(P.changed), MAX(E.changed), MAX(IE.changed), MAX(L.changed), MAX(PL.changed)) AS modified"
            . " FROM Location L"
            . " LEFT OUTER JOIN Exhibition E ON E.id_location=L.id"
            . " LEFT OUTER JOIN Geoname PL ON L.place_tgn=PL.tgn"
            . " LEFT OUTER JOIN ItemExhibition IE ON IE.id_exhibition=E.id"
            . " LEFT OUTER JOIN Person P ON IE.id_person=P.id"
            . " WHERE L.id=?";

        $stmt = $connection->executeQuery($querystr, [ $id ]);
        if ($row = $stmt->fetch()) {
            return new \DateTime($row['modified'] . '+0');
        }
    }

    var $rowDescr = [
        'location' => [
            'label' => 'Name',
            'class' => 'title',
        ],
        'place' => [
            'label' => 'City',
            'class' => 'normal',
        ],
        'type' => [
            'label' => 'Type',
        ],
        'count_exhibition' => [
            'label' => '# of Exhibitions',
            'class' => 'normal',
        ],
        'count_itemexhibition' => [
            'label' => '# of Cat. Entries',
            'class' => 'normal',
        ],
        'count_person' => [
            'label' => '# of Artists',
            'class' => 'normal',
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
        'count_person' => [
            'desc' => [
                'count_person DESC',
                'L.place',
                'L.name',
                'L.id',
            ],
            'asc' => [
                'count_person',
                'L.place',
                'L.name',
                'L.id',
            ],
        ],
    ];

    public function __construct(\Doctrine\DBAL\Connection $connection,
                                Request $request = null,
                                UrlGeneratorInterface $urlGenerator = null,
                                $queryFilters = null,
                                $mode = '')
    {
        $this->mode = $mode;

        parent::__construct($connection, $request, $urlGenerator, $queryFilters);

        if ('stats-type' == $this->mode) {
            $this->orders = [ 'default' => [ 'asc' => [ 'type' ] ] ];
        }
        else if ('stats-country' == $this->mode) {
            $this->orders = [ 'default' => [ 'asc' => [ 'how_many DESC' ] ] ];
        }
        else if ('sitemap' == $this->mode) {
            $this->orders = [ 'default' => [ 'asc' => [ 'id' ] ] ];
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
                'count_person' => [
                    'label' => '# of Artists',
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

                $routeParams['entity'] = 'Person';
                $this->rowDescr['count_person']['buildValue']
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

        if ('sitemap' == $this->mode) {
            $queryBuilder->select([
                $this->alias . '.id AS id',
                $this->alias . '.changed AS changedAt',
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
            'ANY_VALUE(P' . $this->alias . '.country_code) AS country_code',
            'DATE(' . $this->alias . '.foundingdate) AS foundingdate',
            'DATE(' . $this->alias . '.dissolutiondate) AS dissolutiondate',
            $this->alias . '.gnd AS gnd',
            $this->alias . '.ulan AS ulan',
            $this->alias . '.type AS type',
            $this->alias . '.status AS status',
            $this->alias . '.place_geo',
            'ANY_VALUE(P' . $this->alias . '.latitude) AS latitude',
            'ANY_VALUE(P' . $this->alias . '.longitude) AS longitude',
            'COUNT(DISTINCT E.id) AS count_exhibition',
            'COUNT(DISTINCT IE.id) AS count_itemexhibition',
            'COUNT(DISTINCT IE.id_person) AS count_person',
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
        $queryBuilder->innerJoin($this->alias,
                                'Exhibition', 'E',
                                'E.id_location=' . $this->alias . '.id AND ' . $this->buildExhibitionVisibleCondition('E'));
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
