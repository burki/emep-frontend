<?php

namespace AppBundle\Utils;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ExhibitionListBuilder
extends SearchListBuilder
{
    protected $entity = 'Exhibition';
    var $mode = '';

    var $rowDescr = [
        'startdate' => [
            'label' => 'Date',
        ],
        'exhibition' => [
            'label' => 'Title',
        ],
        'place' => [
            'label' => 'City',
        ],
        'location' => [
            'label' => 'Venue',
        ],
        'type' => [
            'label' => 'Type',
        ],
        'organizers' => [
            'label' => 'Org. Body',
        ],
        'count_itemexhibition' => [
            'label' => '# of Cat. Entries',
        ],
        'count_person' => [
            'label' => '# of Artists',
        ],
    ];

    var $orders = [
        'startdate' => [
            'asc' => [
                'E.startdate',
                'E.title',
                'E.id',
            ],
            'desc' => [
                'E.startdate DESC',
                'E.title DESC',
                'E.id DESC',
            ],
        ],
        'exhibition' => [
            'asc' => [
                'E.title',
                'E.startdate',
                'E.id',
            ],
            'desc' => [
                'E.title DESC',
                'E.startdate DESC',
                'E.id DESC',
            ],
        ],
        'location' => [
            'asc' => [
                'L.name',
                'L.place',
                'L.id',
                'E.startdate',
                'E.title',
                'E.id',
            ],
            'desc' => [
                'L.name DESC',
                'L.place DESC',
                'L.id DESC',
                'E.startdate',
                'E.title',
                'E.id',
            ],
        ],
        'place' => [
            'asc' => [
                'L.place',
                'L.name',
                'L.id',
                'E.startdate',
                'E.title',
            ],
            'desc' => [
                'L.place DESC',
                'L.name DESC',
                'L.id DESC',
                'E.startdate DESC',
                'E.title DESC',
            ],
        ],
        'type' => [
            'asc' => [
                'E.type IS NOT NULL DESC',
                'E.type',
                'E.startdate',
                'E.title',
                'E.id',
            ],
            'desc' => [
                'E.type IS NOT NULL',
                'E.type DESC',
                'E.startdate DESC',
                'E.title DESC',
                'E.id DESC',
            ],
        ],
        'count_person' => [
            'desc' => [
                'count_person DESC',
                'E.startdate',
                'E.title',
                'E.id',
            ],
            'asc' => [
                'count_person',
                'E.startdate',
                'E.title',
                'E.id',
            ],
        ],
        'count_itemexhibition' => [
            'desc' => [
                'count_itemexhibition DESC',
                'E.startdate',
                'E.title',
                'E.id',
            ],
            'asc' => [
                'count_itemexhibition',
                'E.startdate',
                'E.title',
                'E.id',
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

        if ('stats-nationality' == $this->mode) {
            $this->orders = [ 'default' => [ 'asc' => [ 'countryCode DESC' ] ] ];
        }
        else if ('stats-gender' == $this->mode) {
            $this->orders = [ 'default' => [ 'asc' => [ 'person_gender DESC' ] ] ];
        }
        else if ('stats-by-month' == $this->mode) {
            $this->orders = [ 'default' => [ 'asc' => [ 'start_year', 'start_month' ] ] ];
        }
        else if ('stats-place' == $this->mode) {
            $this->orders = [ 'default' => [ 'asc' => [ 'how_many DESC' ] ] ];
        }
        else if ('stats-organizer-type' == $this->mode) {
            $this->orders = [ 'default' => [ 'asc' => [ 'how_many DESC' ] ] ];
        }
        else if ('extended' == $this->mode) {
            $this->rowDescr = [
                'exhibition_id' => [
                    'label' => 'ID',
                ],
                'startdate' => [
                    'label' => 'Start Date',
                ],
                'enddate' => [
                    'label' => 'End Date',
                ],
                'displaydate' => [
                    'label' => 'Display Date',
                ],
                'exhibition' => [
                    'label' => 'Title',
                ],
                'place' => [
                    'label' => 'City',
                ],
                'location' => [
                    'label' => 'Venue',
                ],
                'type' => [
                    'label' => 'Type',
                ],
                'organizers' => [
                    'label' => 'Org. Body',
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
        else {
            $this->rowDescr['startdate']['buildValue'] = function (&$row, $val, $listBuilder, $key, $format) {
                if (!empty($row['displaydate'])) {
                    return $this->formatRowValue($row['displaydate'], [], $format);
                }

                return $this->formatRowValue(\AppBundle\Utils\Formatter::daterangeIncomplete($row['startdate'], $row['enddate']), [], $format);
            };
        }

        $this->rowDescr['exhibition']['buildValue'] = function (&$row, $val, $listBuilder, $key, $format) {
            return $listBuilder->buildLinkedExhibition($row, $val, $format);
        };

        $this->rowDescr['place']['buildValue'] = function (&$row, $val, $listBuilder, $key, $format) {
            $key_tgn = $key . '_tgn';

            if (empty($row[$key_tgn])) {
                return false;
            }

            return $listBuilder->buildLinkedValue($val, 'place-by-tgn', [ 'tgn' => $row[$key_tgn] ], $format);
        };

        $this->rowDescr['location']['buildValue'] = function (&$row, $val, $listBuilder, $key, $format) {
            return $listBuilder->buildLinkedLocation($row, $val, $format);
        };

        if (empty($mode)) {
            $routeParams = [
                'entity' => 'ItemExhibition',
                'filter' => $this->getQueryFilters(true),
            ];

            if (empty($routeParams['filter']['search'])) {
                $this->rowDescr['count_itemexhibition']['buildValue']
                    = function (&$row, $val, $listBuilder, $key, $format) use ($routeParams) {
                        if ('html' != $format) {
                            return false;
                        }

                        $routeParams['filter']['exhibition']['exhibition'] = [ $row['exhibition_id'] ];

                        return sprintf('<a href="%s">%s</a>',
                                       $this->urlGenerator->generate('search-index', $routeParams),
                                       $this->formatRowValue($val, [], $format));
                    };

                $routeParams = [
                    'entity' => 'Person',
                    'filter' => $this->getQueryFilters(true),
                ];

                $this->rowDescr['count_person']['buildValue']
                    = function (&$row, $val, $listBuilder, $key, $format) use ($routeParams) {
                        if ('html' != $format) {
                            return false;
                        }

                        $routeParams['filter'] = $listBuilder->getQueryFilters(true);

                        $routeParams['filter']['exhibition']['exhibition'] = [ $row['exhibition_id'] ];

                        return sprintf('<a href="%s">%s</a>',
                                       $this->urlGenerator->generate('search-index', $routeParams),
                                       $this->formatRowValue($val, [], $format));
                    };
            }
        }
    }

    protected function setSelect($queryBuilder)
    {
        if ('stats-gender' == $this->mode) {
            $queryBuilder->select([
                'P.sex as person_gender',
                'COUNT( DISTINCT P.id) AS how_many',
            ]);

            return $this;
        }

        if ('stats-nationality' == $this->mode) {
            $queryBuilder->select([
                'PL.country_code AS countryCode',
                'P.country AS nationality',
                'COUNT(DISTINCT IE.id) AS numEntries',
            ]);

            return $this;
        }

        if ('stats-by-month' == $this->mode) {
            $queryBuilder->select([
                'YEAR(E.startdate) AS start_year',
                'MONTH(E.startdate) AS start_month',
                'COUNT(DISTINCT E.id) AS how_many',
            ]);

            return $this;
        }

        if ('stats-age' == $this->mode) {
            $queryBuilder->select([
                'DISTINCT E.startdate AS startdate',
                'E.id AS exhibition_id',
                'P.id AS person_id',
                'P.birthdate AS birthdate',
                'P.deathdate AS deathdate',
            ]);

            return $this;
        }

        if ('stats-place' == $this->mode) {
            $queryBuilder->select([
                'L.place AS place',
                'COUNT(DISTINCT E.id) AS how_many',
            ]);

            return $this;
        }

        if ('stats-organizer-type' == $this->mode) {
            $queryBuilder->select([
                'E.organizer_type AS organizer_type',
                'COUNT(DISTINCT E.id) AS how_many',
            ]);

            return $this;
        }

        $queryBuilder->select([
            'SQL_CALC_FOUND_ROWS E.id',
            'E.id AS exhibition_id',
            'E.title AS exhibition',
            'E.title_alternate AS exhibition_alternate',
            'E.title_translit AS exhibition_translit',
            'E.type AS type',
            'DATE(E.startdate) AS startdate',
            'DATE(E.enddate) AS enddate',
            'E.displaydate AS displaydate',
            'L.place AS place',
            'L.place_tgn AS place_tgn',
            'L.name AS location',
            'L.name_alternate AS location_alternte',
            'L.name_translit AS location_translit',
            'L.id AS location_id',
            'L.place_geo',
            'PL.latitude', 'PL.longitude',
            'E.status AS status',
            "GROUP_CONCAT(DISTINCT(O.name) ORDER BY EL.ord SEPARATOR '; ') AS organizers",
            'COUNT(DISTINCT IE.id) AS count_itemexhibition',
            'COUNT(DISTINCT IE.id_person) AS count_person',
        ]);

        return $this;
    }

    protected function setFrom($queryBuilder)
    {
        $queryBuilder->from('Exhibition', 'E');

        return $this;
    }

    protected function setJoin($queryBuilder)
    {
        if ('stats-nationality' == $this->mode) {
            $queryBuilder->groupBy('countryCode, nationality');
        }
        else if ('stats-gender' == $this->mode) {
            $queryBuilder->groupBy('P.sex');
        }
        else if ('stats-by-month' == $this->mode) {
            $queryBuilder->groupBy('start_month, start_year');
        }
        else if ('stats-age' == $this->mode) {
            $queryBuilder->groupBy('E.id, P.id');
        }
        else if ('stats-place' == $this->mode) {
            $queryBuilder->groupBy('L.place');
        }
        else if ('stats-organizer-type' == $this->mode) {
            $queryBuilder->groupBy('organizer_type');
        }
        else {
            $queryBuilder->groupBy('E.id');
        }

        $queryBuilder->leftJoin('E',
                                'ItemExhibition', 'IE',
                                'E.id=IE.id_exhibition AND (IE.title IS NOT NULL OR IE.id_item IS NULL)');
        $queryBuilder->leftJoin('E',
                                'Location', 'L',
                                'E.id_location=L.id AND L.status <> -1');
        $queryBuilder->leftJoin('L',
                                'Geoname', 'PL',
                                'L.place_tgn=PL.tgn');
        $queryBuilder->leftJoin('E',
                                'ExhibitionLocation', 'EL',
                                'E.id=EL.id_exhibition AND EL.role = 0');
        $queryBuilder->leftJoin('EL',
                                'Location', 'O',
                                'O.id=EL.id_location');

        if (array_key_exists('person', $this->queryFilters)
            || array_key_exists('search', $this->queryFilters)
            || in_array($this->mode, [ 'stats-nationality', 'stats-age', 'stats-gender' ]))
        {
            // so we can filter on P.*
            $queryBuilder->leftJoin('IE',
                                    'Person', 'P',
                                    'P.id=IE.id_person AND P.status <> -1');
        }

        return $this;
    }

    protected function setFilter($queryBuilder)
    {
        $queryBuilder->andWhere($this->buildExhibitionVisibleCondition('E'));

        if ('stats-by-month' == $this->mode) {
            $queryBuilder->andWhere('MONTH(E.startdate) <> 0');
        }

        $this->addSearchFilters($queryBuilder, [
            'E.title',
            'E.title_short',
            'E.title_translit',
            'E.title_alternate',
            'E.subtitle',
            'E.subtitle_alternate',
            'E.organizing_committee',
            'E.preface',
            'E.description',
            'L.name',
            'L.name_translit',
            'L.name_alternate',
            'L.place',
            'P.lastname',
            'P.firstname',
            'P.name_variant',
            'P.name_variant_ulan',
        ]);

        $this->addQueryFilters($queryBuilder);

        return $this;
    }
}
