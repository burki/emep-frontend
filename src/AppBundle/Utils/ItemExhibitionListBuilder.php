<?php

namespace AppBundle\Utils;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ItemExhibitionListBuilder
extends SearchListBuilder
{
    protected $entity = 'ItemExhibition';

    var $rowDescr = [
        'title' => [
            'label' => 'Title',
        ],
        'type' => [
            'label' => 'Type',
        ],
        'person' => [
            'label' => 'Person',
        ],
        'price' => [
            'label' => 'Price',
        ],
        'catalogueId' => [
            'label' => 'Cat.No.',
        ],
        'exhibition' => [
            'label' => 'Exhibition',
        ],
        'startdate' => [
            'label' => 'Exh. Date',
        ],
        'place' => [
            'label' => 'City',
        ],
        'location' => [
            'label' => 'Venue',
        ],
    ];

    var $orders = [
        'catalogueId' => [
            'asc' => [
                'E.startdate',
                'E.id',
                'IE.catalogue_section',
                'CAST(IE.catalogueId AS unsigned)',
                'IE.catalogueId',
                'person',
                'title',
                'IE.id',
            ],
            'desc' => [
                'E.startdate DESC',
                'E.id DESC',
                'IE.catalogue_section DESC',
                'CAST(IE.catalogueId AS unsigned) DESC',
                'IE.catalogueId DESC',
                'person DESC',
                'title DESC',
                'IE.id DESC',
            ],
        ],
        'person' => [
            'asc' => [
                'person',
                'E.startdate',
                'E.id',
                'IE.catalogue_section',
                'CAST(IE.catalogueId AS unsigned)',
                'IE.catalogueId',
                'title',
                'IE.id',
            ],
            'desc' => [
                'person DESC',
                'E.startdate DESC',
                'E.id DESC',
                'IE.catalogue_section DESC',
                'CAST(IE.catalogueId AS unsigned) DESC',
                'IE.catalogueId DESC',
                'title DESC',
                'IE.id DESC',
            ],
        ],
        'title' => [
            'asc' => [
                "IE.title = ''",
                'IE.title',
                'E.startdate',
                'E.id',
                'IE.catalogue_section',
                'CAST(IE.catalogueId AS unsigned)',
                'IE.catalogueId',
                'person',
                'IE.id',
            ],
            'desc' => [
                "IE.title = '' DESC",
                'IE.title DESC',
                'E.startdate DESC',
                'E.id DESC',
                'IE.catalogue_section DESC',
                'CAST(IE.catalogueId AS unsigned) DESC',
                'IE.catalogueId DESC',
                'person DESC',
                'IE.id DESC',
            ],
        ],
        'type' => [
            'asc' => [
                "(TypeTerm.name <> '0_unknown' AND TypeTerm.name IS NOT NULL) DESC",
                'TypeTerm.name',
                'E.startdate',
                'E.id',
                'IE.catalogue_section',
                'CAST(IE.catalogueId AS unsigned)',
                'IE.catalogueId',
                'title',
                'IE.id',
            ],
            'desc' => [
                "(TypeTerm.name <> '0_unknown' AND TypeTerm.name IS NOT NULL)",
                'TypeTerm.name DESC',
                'E.startdate DESC',
                'E.id DESC',
                'IE.catalogue_section DESC',
                'CAST(IE.catalogueId AS unsigned) DESC',
                'IE.catalogueId DESC',
                'title DESC',
                'IE.id DESC',
            ],
        ],
        'place' => [
            'asc' => [
                'L.place',
                'E.startdate',
                'E.id',
                'IE.catalogue_section',
                'CAST(IE.catalogueId AS unsigned)',
                'IE.catalogueId',
                'person',
                'title',
                'IE.id',
            ],
            'desc' => [
                'L.place DESC',
                'E.startdate DESC',
                'E.id DESC',
                'IE.catalogue_section DESC',
                'CAST(IE.catalogueId AS unsigned) DESC',
                'IE.catalogueId DESC',
                'person DESC',
                'title DESC',
                'IE.id DESC',
            ],
        ],
        'location' => [
            'asc' => [
                'L.name',
                'L.place',
                'L.id',
                'E.startdate',
                'E.id',
                'IE.catalogue_section',
                'CAST(IE.catalogueId AS unsigned)',
                'IE.catalogueId',
                'person',
                'title',
                'IE.id',
            ],
            'desc' => [
                'L.name DESC',
                'L.place DESC',
                'L.id DESC',
                'E.startdate DESC',
                'E.id DESC',
                'IE.catalogue_section DESC',
                'CAST(IE.catalogueId AS unsigned) DESC',
                'IE.catalogueId DESC',
                'person DESC',
                'title DESC',
                'IE.id DESC',
            ],
        ],
    ];

    public function __construct(\Doctrine\DBAL\Connection $connection,
                                ?Request $request = null,
                                ?UrlGeneratorInterface $urlGenerator = null,
                                $queryFilters = null,
                                $mode = '')
    {
        $this->mode = $mode;

        parent::__construct($connection, $request, $urlGenerator, $queryFilters);

        if ('stats-type' == $this->mode) {
            $this->orders = [ 'default' => [ 'asc' => [ 'type' ] ] ];
        }
        else if ('extended' == $this->mode) {
            $this->rowDescr = [
                'id' => [
                    'label' => 'ID',
                ],
                'person' => [
                    'label' => 'Person',
                ],
                'lifespan' => [
                    'label' => 'Life Span',
                ],
                'gender' => [
                    'label' => 'Gender',
                ],
                'nationality' => [
                    'label' => '(Preferred) Nationality',
                ],
                'title' => [
                    'label' => 'Title',
                ],
                'type' => [
                    'label' => 'Type',
                ],
                'displaydate' => [
                    'label' => 'Creation Date',
                ],
                'owner' => [
                    'label' => 'Owner',
                ],
                'forsale' => [
                    'label' => 'For Sale',
                ],
                'currency' => [
                    'label' => 'Currency',
                ],
                'price' => [
                    'label' => 'Price',
                ],
                'displaylocation' => [
                    'label' => 'Room',
                ],
                'catalogueId' => [
                    'label' => 'Cat.No.',
                ],
                'exhibition' => [
                    'label' => 'Exhibition',
                ],
                'startdate' => [
                    'label' => 'Exh. Date',
                ],
                'place' => [
                    'label' => 'City',
                ],
                'location' => [
                    'label' => 'Venue',
                ],
                'location_type' => [
                    'label' => 'Type of Venue',
                ],
                'organizers' => [
                    'label' => 'Organizing Body',
                ],
                'organizer_type' => [
                    'label' => 'Type of Organizing Body',
                ],
            ];
        }

        $this->rowDescr['title']['buildValue'] = function (&$row, $val, $listBuilder, $key, $format) {
            return $listBuilder->buildLinkedItemExhibition($row, $val, $format);
        };

        $this->rowDescr['startdate']['buildValue'] = function (&$row, $val, $listBuilder, $key, $format) {
            if (!empty($row['displaydate'])) {
                return $this->formatRowValue($row['displaydate'], [], $format);
            }

            return $this->formatRowValue(\AppBundle\Utils\Formatter::daterangeIncomplete($row['startdate'], $row['enddate']), [], $format);
        };

        $this->rowDescr['exhibition']['buildValue'] = function (&$row, $val, $listBuilder, $key, $format) {
            return $listBuilder->buildLinkedExhibition($row, $val, $format);
        };

        $this->rowDescr['location']['buildValue'] = function (&$row, $val, $listBuilder, $key, $format) {
            return $listBuilder->buildLinkedValue($val, 'location', [ 'id' => $row['location_id'] ], $format);
        };

        $this->rowDescr['person']['buildValue'] = function (&$row, $val, $listBuilder, $key, $format) {
            return $listBuilder->buildLinkedValue($val, 'person', [ 'id' => $row['person_id'] ], $format);
        };

        $this->rowDescr['place']['buildValue'] = function (&$row, $val, $listBuilder, $key, $format) {
            $key_tgn = $key . '_tgn';

            if (empty($row[$key_tgn])) {
                return false;
            }

            return $listBuilder->buildLinkedValue($val, 'place-by-tgn', [ 'tgn' => $row[$key_tgn] ], $format);
        };

        $this->rowDescr['type']['buildValue'] = function (&$row, $val, $listBuilder, $key, $format) {
            if ('0_unknown' == $val) {
                return '';
            }

            return $this->formatRowValue($val, [], $format);
        };

        if (array_key_exists('currency', $this->rowDescr)) {
            $this->rowDescr['currency']['buildValue'] = function (&$row, $val, $listBuilder, $key, $format) {
                if (empty($row['price'])) {
                    return '';
                }

                return $this->formatRowValue($this->buildCurrencySymbol($val), [], $format);
            };
        }

        /*
        if (array_key_exists('price', $this->rowDescr)) {
            $this->rowDescr['price']['buildValue'] = function (&$row, $val, $listBuilder, $key, $format) {
                if (!empty($val)) {
                    return $this->formatRowValue($this->formatPrice($val, $row['currency']),
                                                 [], $format);
                }
            };
        }
        */
    }

    protected function setSelect($queryBuilder)
    {
        if ('stats-type' == $this->mode) {
            $queryBuilder->select([
                'TypeTerm.name AS type',
                'COUNT(DISTINCT IE.id) AS how_many',
            ]);

            return $this;
        }

        $selectTitle = 'IE.title AS title';
        if (!is_null(\AppBundle\Entity\ItemExhibition::TYPE_OTHER_MEDIA)) {
            $selectTitle = sprintf("IF(IE.type = %d, '', IE.title) AS title",
                                   \AppBundle\Entity\ItemExhibition::TYPE_OTHER_MEDIA);
        }

        $queryBuilder->select([
            'SQL_CALC_FOUND_ROWS IE.id',
            'IE.id AS id',
            "CONCAT(P.lastname, ', ', IFNULL(P.firstname, '')) AS person",
            'P.id AS person_id',
            "CONCAT(IFNULL(YEAR(birthdate), ''), IF(deathdate IS NOT NULL, CONCAT('-', YEAR(deathdate)), '')) AS lifespan",
            'P.sex AS gender',
            'P.country AS nationality',
            'IE.catalogueId AS catalogueId',
             $selectTitle,
            'IE.title_alternate AS title_alternate',
            'IE.title_translit AS title_translit',
            'TypeTerm.name AS type',
            'IE.displaydate AS displaydate',
            'IE.owner AS owner',
            'IE.forsale AS forsale',
            'IE.price AS price',
            'IE.displaylocation AS displaylocation',
            'E.id AS exhibition_id',
            'E.title AS exhibition',
            'E.title_alternate AS exhibition_alternate',
            'E.title_translit AS exhibition_translit',
            'DATE(E.startdate) AS startdate',
            'DATE(E.enddate) AS enddate',
            'E.displaydate AS displaydate',
            'E.type AS exhibition_type',
            'E.currency AS currency',
            'L.name AS location',
            'L.id AS location_id',
            'L.type AS location_type',
            'L.place AS place',
            'L.place_tgn AS place_tgn',
            // 'E.organizer_type AS organizer_type',
            "GROUP_CONCAT(O.name ORDER BY EL.ord SEPARATOR '; ') AS organizers",

            "1 AS status",
        ]);

        return $this;
    }

    protected function setFrom($queryBuilder)
    {
        $queryBuilder->from('ItemExhibition', 'IE');

        return $this;
    }

    protected function setJoin($queryBuilder)
    {
        if ('stats-type' == $this->mode) {
            $queryBuilder->groupBy('IE.type');
        }
        else {
            $queryBuilder->groupBy('IE.id');
        }

        $queryBuilder->leftJoin('IE',
                                'Person', 'P',
                                'P.id=IE.id_person AND P.status <> -1');
        $queryBuilder->join('IE',
                                'Exhibition', 'E',
                                'E.id=IE.id_exhibition AND ' . $this->buildExhibitionVisibleCondition('E'));
        $queryBuilder->leftJoin('IE',
                                'Term', 'TypeTerm',
                                'IE.type=TypeTerm.id');
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

        return $this;
    }

    protected function setFilter($queryBuilder)
    {
        // we show other media but not anonymous links to work
        $queryBuilder->andWhere('IE.title IS NOT NULL OR IE.id_item IS NULL');

        $this->addSearchFilters($queryBuilder, [
            'IE.title',
            'IE.title_translit',
            'IE.title_alternate',
            'IE.owner',
            'IE.owner_alternate',
            'IE.displaycreator',
            'E.title',
            'E.title_short',
            'E.title_translit',
            'E.title_alternate',
            'E.subtitle',
            'E.subtitle_alternate',
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

        if (array_key_exists('organizer', $this->queryFilters)) {
            // so we can filter on PO.*
            $queryBuilder->leftJoin('O',
                                    'Geoname', 'PO',
                                    'O.place_tgn=PO.tgn');
        }

        return $this;
    }
}
