<?php

namespace AppBundle\Utils;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PlaceListBuilder
extends SearchListBuilder
{
    protected $entity = 'Place';
    protected $alias = 'PL';

    /* TODO: share across entities and use logic in setJoin to build the query */
    static function fetchDateModified(\Doctrine\DBAL\Connection $connection, $tgn)
    {
        // TODO: add Person-join through placeOfBirth / placeOfDeath and maybe placeOfActivity
        $querystr = "SELECT GREATEST(MAX(P.changed), MAX(E.changed), MAX(IE.changed), MAX(L.changed), MAX(PL.changed)) AS modified"
            . " FROM Geoname PL"
            . " LEFT OUTER JOIN Location L ON L.place_tgn=PL.tgn"
            . " LEFT OUTER JOIN Exhibition E ON E.id_location=L.id"
            . " LEFT OUTER JOIN ItemExhibition IE ON IE.id_exhibition=E.id"
            . " LEFT OUTER JOIN Person P ON IE.id_person=P.id"
            /*
            // TODO: the following kills performance, investigate
            . " LEFT OUTER JOIN ExhibitionLocation EL ON EL.id_location=L.id AND EL.role = 0"
            . " LEFT OUTER JOIN Exhibition EO ON EO.id = EL.id_exhibition=EO.id"
            . " LEFT OUTER JOIN ItemExhibition IEO ON IEO.id_exhibition=EO.id"
            . " LEFT OUTER JOIN Person PO ON IEO.id_person=PO.id"
            */
            . " WHERE PL.tgn=?";

        $stmt = $connection->executeQuery($querystr, [ $tgn ]);
        if ($row = $stmt->fetch()) {
            return new \DateTime($row['modified'] . '+0');
        }
    }

    var $rowDescr = [
        'place' => [
            'label' => 'City',
            'class' => 'title',
        ],
        'country' => [
            'label' => 'Country',
        ],
        'count_location' => [
            'label' => '# of Venues',
            'class' => 'normal',
        ],
        'count_exhibition' => [
            'label' => '# of Exhibitions',
            'class' => 'normal',
        ],
        'count_itemexhibition' => [
            'label' => '# of Cat. Entries',
            'class' => 'normal',
        ],
    ];

    var $orders = [
        'place' => [
            'asc' => [
                'COALESCE(PL.name_alternate, PL.name)',
                'PL.tgn',
            ],
            'desc' => [
                'COALESCE(PL.name_alternate, PL.name) DESC',
                'PL.tgn DESC',
            ],
        ],
        'country' => [
            'asc' => [
                'C.name',
                'COALESCE(PL.name_alternate, PL.name)',
                'PL.tgn',
            ],
            'desc' => [
                'C.name DESC',
                'COALESCE(PL.name_alternate, PL.name) DESC',
                'PL.tgn DESC',
            ],
        ],
        'count_location' => [
            'desc' => [
                'count_location DESC',
                'COALESCE(PL.name_alternate, PL.name)',
                'PL.tgn',
            ],
            'asc' => [
                'count_location',
                'COALESCE(PL.name_alternate, PL.name)',
                'PL.tgn',
            ],
        ],
        'count_exhibition' => [
            'desc' => [
                'count_exhibition DESC',
                'COALESCE(PL.name_alternate, PL.name)',
                'PL.tgn',
            ],
            'asc' => [
                'count_exhibition',
                'COALESCE(PL.name_alternate, PL.name)',
                'PL.tgn',
            ],
        ],
        'count_itemexhibition' => [
            'desc' => [
                'count_itemexhibition DESC',
                'COALESCE(PL.name_alternate, PL.name)',
                'PL.tgn',
            ],
            'asc' => [
                'count_itemexhibition',
                'COALESCE(PL.name_alternate, PL.name)',
                'PL.tgn',
            ],
        ],
    ];

    public function __construct(\Doctrine\DBAL\Connection $connection,
                                Request $request = null,
                                UrlGeneratorInterface $urlGenerator,
                                $queryFilters = null,
                                $mode = '')
    {
        $this->mode = $mode;

        parent::__construct($connection, $request, $urlGenerator, $queryFilters);

        if ('stats-type' == $this->mode) {
            $this->orders = [ 'default' => [ 'asc' => [ 'type' ] ] ];
        }
        else if (in_array($this->mode, [ 'stats-country', 'stats-exhibition-distribution' ])) {
            $this->orders = [ 'default' => [ 'asc' => [ 'how_many DESC' ] ] ];
        }
        else if ('sitemap' == $this->mode) {
            $this->orders = [ 'default' => [ 'asc' => [ 'id' ] ] ];
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

        if (empty($mode)) {
            if (empty($routeParams['filter']['search'])) {
                $routeParams = [
                    'filter' => $this->getQueryFilters(true),
                ];
                unset($routeParams['filter']['place']); // we don't need country filter when we set to place

                $routeParams['entity'] = 'Venue';
                $this->rowDescr['count_location']['buildValue']
                    = function (&$row, $val, $listBuilder, $key, $format) use ($routeParams) {
                        if ('html' != $format) {
                            return false;
                        }

                        $routeParams['filter']['location']['geoname'] = [ 'tgn:' . $row['place_tgn'] ];

                        return sprintf('<a href="%s">%s</a>',
                                       $this->urlGenerator->generate('search-index', $routeParams),
                                       $this->formatRowValue($val, [], $format));
                    };


                $routeParams['entity'] = 'Exhibition';
                $this->rowDescr['count_exhibition']['buildValue']
                    = function (&$row, $val, $listBuilder, $key, $format) use ($routeParams) {
                        if ('html' != $format) {
                            return false;
                        }

                        $routeParams['filter']['location']['geoname'] = [ 'tgn:' . $row['place_tgn'] ];

                        return sprintf('<a href="%s">%s</a>',
                                       $this->urlGenerator->generate('search-index', $routeParams),
                                       $this->formatRowValue($val, [], $format));
                    };

                $routeParams['entity'] = 'ItemExhibition';
                $this->rowDescr['count_itemexhibition']['buildValue']
                    = function (&$row, $val, $listBuilder, $key, $format) use ($routeParams) {
                        if ('html' != $format) {
                            return false;
                        }

                        $routeParams['filter']['location']['geoname'] = [ 'tgn:' . $row['place_tgn'] ];

                        return sprintf('<a href="%s">%s</a>',
                                       $this->urlGenerator->generate('search-index', $routeParams),
                                       $this->formatRowValue($val, [], $format));
                    };
            }
        }
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

        if ('stats-exhibition-distribution' == $this->mode) {
            $queryBuilder->select([
                'PL.tgn AS place_tgn',
                'COUNT(DISTINCT IE.id_exhibition) AS how_many',
            ]);

            return $this;
        }

        if ('sitemap' == $this->mode) {
            $queryBuilder->select([
                $this->alias . '.id AS id',
                $this->alias . '.tgn AS tgn',
                $this->alias . '.changed AS changedAt',
            ]);

            return $this;
        }

        $queryBuilder->select([
            'SQL_CALC_FOUND_ROWS ' . $this->alias . '.tgn',
            $this->alias . '.tgn AS place_id',
            'COALESCE(' . $this->alias . '.name_alternate, ' . $this->alias . '.name) AS place',
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

        if (array_key_exists('organizer', $this->queryFilters)) {
            // so we can filter on O.*
            $queryBuilder->innerJoin('E',
                                    'ExhibitionLocation', 'EL',
                                    'EL.id_exhibition=E.id AND ' . $this->buildExhibitionVisibleCondition('E'));
            $queryBuilder->innerJoin('EL',
                                    'Location', 'O',
                                    'EL.id_location=O.id AND EL.role = 0');
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

        if ('stats-exhibition-distribution' == $this->mode) {
            $queryBuilder->having('how_many >= 1');
        }

        return $this;
    }

	public function getAlias()
	{
		return $this->alias;
	}
}
