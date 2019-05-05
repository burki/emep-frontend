<?php

namespace AppBundle\Utils;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PersonListBuilder
extends SearchListBuilder
{
    protected $entity = 'Person';
    var $mode = '';
    protected $joinLatLong = false; // for maps

    var $rowDescr = [
        'person' => [
            'label' => 'Name',
        ],
        'birthdate' => [
            'label' => 'Year of Birth',
        ],
        'birthplace' => [
            'label' => 'Place of Birth',
        ],
        'deathdate' => [
            'label' => 'Year of Death',
        ],
        'deathplace' => [
            'label' => 'Place of Death',
        ],
        'gender' => [
            'label' => 'Gender',
        ],
        'nationality' => [
            'label' => '(Preferred) Nationality',
        ],
        'count_exhibition' => [
            'label' => '# of Exhibitions',
        ],
        'count_itemexhibition' => [
            'label' => '# of Cat. Entries',
        ],
    ];

    var $orders = [
        'person' => [
            'asc' => [
                'P.lastname',
                'P.firstname',
                'P.id',
            ],
            'desc' => [
                'P.lastname DESC',
                'P.firstname DESC',
                'P.id DESC',
            ],
        ],
        'birthdate' => [
            'desc' => [
                'P.birthdate DESC',
                'P.lastname DESC',
                'P.firstname DESC',
                'P.id DESC',
            ],
            'asc' => [
                'P.birthdate',
                'P.lastname',
                'P.firstname',
                'P.id',
            ],
        ],
        'deathdate' => [
            'desc' => [
                'P.deathdate DESC',
                'P.lastname DESC',
                'P.firstname DESC',
                'P.id DESC',
            ],
            'asc' => [
                'P.deathdate',
                'P.lastname',
                'P.firstname',
                'P.id',
            ],
        ],
        'birthplace' => [
            'asc' => [
                'P.birthplace IS NULL',
                'P.birthplace',
                'P.lastname',
                'P.firstname',
                'P.id',
            ],
            'desc' => [
                'P.birthplace IS NOT NULL',
                'P.birthplace DESC',
                'P.lastname DESC',
                'P.firstname DESC',
                'P.id DESC',
            ],
        ],
        'deathplace' => [
            'asc' => [
                'P.deathplace IS NULL',
                'P.deathplace',
                'P.lastname',
                'P.firstname',
                'P.id',
            ],
            'desc' => [
                'P.deathplace IS NOT NULL',
                'P.deathplace DESC',
                'P.lastname DESC',
                'P.firstname DESC',
                'P.id DESC',
            ],
        ],
        'nationality' => [
            'asc' => [
                'P.country IS NULL',
                'P.country',
                'P.lastname',
                'P.firstname',
                'P.id',
            ],
            'desc' => [
                'P.country IS NOT NULL',
                'P.country DESC',
                'P.lastname DESC',
                'P.firstname DESC',
                'P.id DESC',
            ],
        ],
        'gender' => [
            'asc' => [
                'P.sex IS NULL',
                'P.sex DESC',
                'P.lastname',
                'P.firstname',
                'P.id',
            ],
            'desc' => [
                'P.sex IS NOT NULL',
                'P.sex',
                'P.lastname DESC',
                'P.firstname DESC',
                'P.id DESC',
            ],
        ],
        'count_exhibition' => [
            'desc' => [
                'count_exhibition DESC',
                'P.lastname',
                'P.firstname',
                'P.id',
            ],
            'asc' => [
                'count_exhibition',
                'P.lastname',
                'P.firstname',
                'P.id',
            ],
        ],
        'count_itemexhibition' => [
            'desc' => [
                'count_itemexhibition DESC',
                'P.lastname',
                'P.firstname',
                'P.id',
            ],
            'asc' => [
                'count_itemexhibition',
                'P.lastname',
                'P.firstname',
                'P.id',
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

        // TODO: it might be better to use a new map-$mode instead of _route
        $this->joinLatLong = in_array($request->get('_route'), [ 'search-map', 'person-index-map' ]);

        if (in_array($this->mode, [ 'stats-nationality', 'stats-exhibition-distribution' ])) {
            $this->orders = [ 'default' => [ 'asc' => [ 'how_many DESC' ] ] ];
        }
        else if (in_array($this->mode, [ 'stats-by-year-birth', 'stats-by-year-death' ])) {
            $this->orders = [ 'default' => [ 'asc' => [ 'year' ] ] ];
        }
        else if ('stats-popularity' == $this->mode) {
            $this->orders = [ 'default' => [ 'asc' => [ 'count_exhibition DESC' ] ] ];
        }
        else if ('extended' == $this->mode) {
            $this->rowDescr = [
                'person_id' => [
                    'label' => 'ID',
                ],
                'person' => [
                    'label' => 'Name',
                ],
                'birthdate' => [
                    'label' => 'Date of Birth',
                ],
                'birthplace' => [
                    'label' => 'Place of Birth',
                ],
                'deathdate' => [
                    'label' => 'Date of Death',
                ],
                'deathplace' => [
                    'label' => 'Place of Death',
                ],
                'gender' => [
                    'label' => 'Gender',
                ],
                'nationality' => [
                    'label' => '(Primary) Nationality',
                ],
                'ulan' => [
                    'label' => 'ULAN',
                ],
                'gnd' => [
                    'label' => 'GND',
                ],
                'wikidata' => [
                    'label' => 'Wikidata',
                ],
                'count_exhibition' => [
                    'label' => '# Exhibitions',
                ],
                'count_itemexhibition' => [
                    'label' => '# Cat. Entries',
                ],
                'status' => [
                    'label' => 'Status',
                    'buildValue' => function (&$row, $val, $listBuilder, $key, $format) {
                        return $this->formatRowValue($listBuilder->buildStatusLabel($val), [], $format);
                    },
                ],
            ];
        }
        else {
            $this->rowDescr['birthdate']['buildValue']
                = $this->rowDescr['deathdate']['buildValue']
                = function (&$row, $val, $listBuilder, $key, $format) {
                    // year only
                    return preg_match('/^(\d+)\-/', $val, $matches)
                        && $matches[1] > 0 ? $matches[1] : '';
                    // return \AppBundle\Utils\Formatter::dateIncomplete($val);
                };
        }

        $this->rowDescr['person']['buildValue'] = function (&$row, $val, $listBuilder, $key, $format) {
            return $listBuilder->buildLinkedValue($val, 'person', [ 'id' => $row['id'] ], $format);
        };

        $this->rowDescr['birthplace']['buildValue']
            = $this->rowDescr['deathplace']['buildValue']
            = function (&$row, $val, $listBuilder, $key, $format) {
                $key_tgn = $key . '_tgn';

                if (empty($row[$key_tgn])) {
                    return false;
                }

                return $listBuilder->buildLinkedValue($val, 'place-by-tgn', [ 'tgn' => $row[$key_tgn] ], $format);
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

                        $routeParams['filter']['person']['person'] = [ $row['person_id'] ];

                        return sprintf('<a href="%s">%s</a>',
                                       $this->urlGenerator->generate('search-index', $routeParams),
                                       $this->formatRowValue($val, [], $format));
                    };

                $routeParams = [
                    'entity' => 'ItemExhibition',
                    'filter' => $this->getQueryFilters(true),
                ];

                $this->rowDescr['count_itemexhibition']['buildValue']
                    = function (&$row, $val, $listBuilder, $key, $format) use ($routeParams) {
                        if ('html' != $format) {
                            return false;
                        }

                        $routeParams['filter']['person']['person'] = [ $row['person_id'] ];

                        return sprintf('<a href="%s">%s</a>',
                                       $this->urlGenerator->generate('search-index', $routeParams),
                                       $this->formatRowValue($val, [], $format));
                    };
            }
        }
    }

    protected function setSelect($queryBuilder)
    {
        if ($this->joinLatLong) {
            $queryBuilder->select([
                'SQL_CALC_FOUND_ROWS P.id',
                "CONCAT(P.lastname, ', ', IFNULL(P.firstname, '')) AS person",
                'P.id AS person_id',
                "DATE(P.birthdate) AS birthdate",
                "P.birthplace AS birthplace",
                "P.birthplace_tgn AS birthplace_tgn",
                "DATE(P.deathdate) AS deathdate",
                "P.deathplace AS deathplace",
                "P.deathplace_tgn AS deathplace_tgn",
                "P.ulan AS ulan",
                "P.pnd AS gnd",
                "P.wikidata AS wikidata",
                'P.sex AS gender',
                'P.country AS nationality',
                'P.status AS status',
                'PB.latitude AS birthplace_latitude',
                'PB.longitude AS birthplace_longitude',
                'PD.latitude AS deathplace_latitude',
                'PD.longitude AS deathplace_longitude',
            ]);

            return $this;
        }

        if ('stats-nationality' == $this->mode) {
            $queryBuilder->select([
                'P.country AS nationality',
                'COUNT(DISTINCT P.id) AS how_many',
            ]);

            return $this;
        }

        if (in_array($this->mode, [ 'stats-by-year-birth', 'stats-by-year-death' ])) {
            $queryBuilder->select([
                'YEAR(P.' . ( 'stats-by-year-death' == $this->mode ? 'death' : 'birth' ) . 'date) AS year',
                'COUNT(DISTINCT P.id) AS how_many',
            ]);

            return $this;
        }

        if ('stats-exhibition-distribution' == $this->mode) {
            $queryBuilder->select([
                'P.id AS person_id',
                'COUNT(DISTINCT IE.id_exhibition) AS how_many',
            ]);

            return $this;
        }

        if ('stats-popularity' == $this->mode) {
            $queryBuilder->select([
                "CONCAT(P.lastname, ', ', IFNULL(P.firstname, '')) AS person",
                'P.id AS person_id',
                "DATE(P.birthdate) AS birthdate",
                "P.birthplace AS birthplace",
                "P.birthplace_tgn AS birthplace_tgn",
                "DATE(P.deathdate) AS deathdate",
                "P.deathplace AS deathplace",
                "P.deathplace_tgn AS deathplace_tgn",
                "P.ulan AS ulan",
                "P.pnd AS gnd",
                "P.wikidata AS wikidata",
                'P.sex AS gender',
                'P.country AS nationality',
                'P.status AS status',
                'COUNT(DISTINCT IE.id_exhibition) AS count_exhibition',
                'P.additional AS additional',
            ]);

            return $this;
        }

        $queryBuilder->select([
            'SQL_CALC_FOUND_ROWS P.id',
            "CONCAT(P.lastname, ', ', IFNULL(P.firstname, '')) AS person",
            'P.id AS person_id',
            "DATE(P.birthdate) AS birthdate",
            "P.birthplace AS birthplace",
            "P.birthplace_tgn AS birthplace_tgn",
            "DATE(P.deathdate) AS deathdate",
            "P.deathplace AS deathplace",
            "P.deathplace_tgn AS deathplace_tgn",
            "P.ulan AS ulan",
            "P.pnd AS gnd",
            "P.wikidata AS wikidata",
            'P.sex AS gender',
            'P.country AS nationality',
            'P.status AS status',
            'COUNT(DISTINCT IE.id_exhibition) AS count_exhibition',
            'COUNT(DISTINCT IE.id) AS count_itemexhibition',
        ]);

        return $this;
    }

    protected function setFrom($queryBuilder)
    {
        $queryBuilder->from('Person', 'P');

        return $this;
    }

    protected function setJoin($queryBuilder)
    {
        if ('stats-nationality' == $this->mode) {
            $queryBuilder->groupBy('nationality');
        }
        else if (in_array($this->mode, [ 'stats-by-year-birth', 'stats-by-year-death' ])) {
            $queryBuilder->groupBy('year');
        }
        else {
            $queryBuilder->groupBy('P.id');
        }

        $queryBuilder->leftJoin('P',
                                '(SELECT ItemExhibition.* FROM ItemExhibition'
                                . ' INNER JOIN Exhibition ON ItemExhibition.id_exhibition=Exhibition.id AND ' . $this->buildExhibitionVisibleCondition('Exhibition') . ')', 'IE',
                                'IE.id_person=P.id AND (IE.title IS NOT NULL OR IE.id_item IS NULL)');

        if (array_key_exists('exhibition', $this->queryFilters)
            || array_key_exists('location', $this->queryFilters)
            || array_key_exists('organizer', $this->queryFilters))
        {
            // so we can filter on E.*
            $queryBuilder->leftJoin('IE',
                                    'Exhibition', 'E',
                                    'E.id=IE.id_exhibition AND ' . $this->buildExhibitionVisibleCondition('E')
                                    . ' AND (IE.title IS NOT NULL OR IE.id_item IS NULL)');

            if (array_key_exists('location', $this->queryFilters)) {
                // so we can filter on L.*
                $queryBuilder->leftJoin('E',
                                        'Location', 'L',
                                        'E.id_location=L.id AND L.status <> -1');
                $queryBuilder->leftJoin('L',
                                        'Geoname', 'PL',
                                        'L.place_tgn=PL.tgn');
            }

            if (array_key_exists('organizer', $this->queryFilters)) {
                // so we can filter on O.*
                $queryBuilder->innerJoin('E',
                                        'ExhibitionLocation', 'EL',
                                        'EL.id_exhibition=E.id AND ' . $this->buildExhibitionVisibleCondition('E'));
                $queryBuilder->innerJoin('EL',
                                        'Location', 'O',
                                        'EL.id_location=O.id AND EL.role = 0');

                /*
                $queryBuilder->leftJoin('O',
                                        'Geoname', 'PO',
                                        'L.place_tgn=P=.tgn');
                */
            }
        }

        if ($this->joinLatLong) {
            $queryBuilder->leftJoin('P',
                                    'Geoname', 'PB',
                                    'P.birthplace_tgn=PB.tgn');
            $queryBuilder->leftJoin('P',
                                    'Geoname', 'PD',
                                    'P.deathplace_tgn=PD.tgn');
        }

        return $this;
    }

    protected function setFilter($queryBuilder)
    {
        // don't show deleted
        $queryBuilder->andWhere('P.status <> -1');

        if ('stats-popularity' == $this->mode) {
            $queryBuilder->andWhere('P.wikidata IS NOT NULL');
        }
        else if (in_array($this->mode, [ 'stats-by-year-birth', 'stats-by-year-death' ])) {
            $queryBuilder->andWhere('P.' . ( 'stats-by-year-death' == $this->mode ? 'death' : 'birth' ) . 'date IS NOT NULL');
        }
        else if ('stats-exhibition-distribution' == $this->mode) {
            $queryBuilder->having('how_many >= 1');
        }

        $this->addSearchFilters($queryBuilder, [
            'P.lastname',
            'P.firstname',
            'P.pnd',
            'P.ulan',
            'P.name_variant',
            'P.name_variant_ulan',
            'P.birthplace',
            'P.deathplace',
        ]);

        $this->addQueryFilters($queryBuilder);

        return $this;
    }
}
