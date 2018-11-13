<?php

namespace AppBundle\Utils;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ExhibitionListBuilder
extends SearchListBuilder
{
    protected $entity = 'Exhibition';

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
            'label' => 'Number of Cat. Entries',
        ],
        'count_person' => [
            'label' => 'Number of Artists',
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
                                $extended = false)
    {
        parent::__construct($connection, $request, $urlGenerator, $queryFilters);

        if ($extended) {
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
                    'label' => 'Number of Cat. Entries',
                ],
                'count_person' => [
                    'label' => 'Number of Artists',
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
            return $listBuilder->buildLinkedValue($val, 'exhibition', [ 'id' => $row['id'] ], $format);
        };

        $this->rowDescr['location']['buildValue'] = function (&$row, $val, $listBuilder, $key, $format) {
            return $listBuilder->buildLinkedValue($val, 'location', [ 'id' => $row['location_id'] ], $format);
        };

        $this->rowDescr['place']['buildValue'] = function (&$row, $val, $listBuilder, $key, $format) {
            $key_tgn = $key . '_tgn';

            if (empty($row[$key_tgn])) {
                return false;
            }

            return $listBuilder->buildLinkedValue($val, 'place-by-tgn', [ 'tgn' => $row[$key_tgn] ], $format);
        };
    }

    protected function setSelect($queryBuilder)
    {
        $queryBuilder->select([
            'SQL_CALC_FOUND_ROWS E.id',
            'E.id AS exhibition_id',
            'E.title AS exhibition',
            'E.type AS type',
            'DATE(E.startdate) AS startdate',
            'DATE(E.enddate) AS enddate',
            'E.displaydate AS displaydate',
            'L.place AS place',
            'L.place_tgn AS place_tgn',
            'L.name AS location',
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
        $queryBuilder->groupBy('E.id');

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
        $queryBuilder->andWhere('E.status <> -1');

        $this->addSearchFilters($queryBuilder, [
            'E.title',
            'E.title_short',
            'E.title_translit',
            'E.title_alternate',
            'E.organizing_committee',
            'L.name',
            'L.place',
        ]);

        $this->addQueryFilters($queryBuilder);

        return $this;
    }
}
