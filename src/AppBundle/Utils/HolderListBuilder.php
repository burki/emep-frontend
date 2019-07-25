<?php

namespace AppBundle\Utils;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class HolderListBuilder
extends SearchListBuilder
{
    protected $entity = 'Holder';
    protected $alias = 'H';

    var $rowDescr = [
        'holder' => [
            'label' => 'Name',
            'class' => 'title',
        ],
        'count_bibitem' => [
            'label' => '# of Catalogues',
        ],
    ];

    var $orders = [
        'holder' => [
            'asc' => [
                'holder',
                'H.place',
                'H.id',
            ],
            'desc' => [
                'holder DESC',
                'H.place DESC',
                'H.id DESC',
            ],
        ],
        'count_bibitem' => [
            'desc' => [
                'count_bibitem DESC',
                'place',
                'H.name',
                'H.id',
            ],
            'asc' => [
                'count_bibitem',
                'place',
                'H.name',
                'H.id',
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

        if ('sitemap' == $this->mode) {
            $this->orders = [ 'default' => [ 'asc' => [ 'id' ] ] ];
        }
        else if ('extended' == $this->mode) {
            $this->rowDescr = [
                'holder_id' => [
                    'label' => 'ID',
                ],
                'name' => [
                    'label' => 'Name',
                ],
                'place' => [
                    'label' => 'Place',
                ],
                'country_code' => [
                    'label' => 'Country Code',
                ],
                'ulan' => [
                    'label' => 'ULAN',
                ],
                'gnd' => [
                    'label' => 'GND',
                ],
                'count_bibitem' => [
                    'label' => '# of Catalogues',
                ],
                'status' => [
                    'label' => 'Status',
                    'buildValue' => function (&$row, $val, $listBuilder, $key, $format) {
                        return $this->formatRowValue($listBuilder->buildStatusLabel($val), [], $format);
                    },
                ],
            ];
        }

        if (array_key_exists('holder', $this->rowDescr)) {
            $this->rowDescr['holder']['buildValue'] = function (&$row, $val, $listBuilder, $key, $format) {
                return $listBuilder->buildLinkedHolder($row, $val, $format);
            };
        }

        if (array_key_exists('place', $this->rowDescr)) {
            $this->rowDescr['place']['buildValue'] = function (&$row, $val, $listBuilder, $key, $format) {
                if (empty($row['place_tgn'])) {
                    return false;
                }

                return $listBuilder->buildLinkedValue($val, 'place-by-tgn', [ 'tgn' => $row['place_tgn'] ], $format);
            };
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
            $this->alias . '.id AS holder_id',
            "CONCAT(CASE WHEN " . $this->alias . ".name LIKE 'online:%' THEN '' ELSE CONCAT(" . $this->alias . ".town, ', ') END, " . $this->alias . ".name) AS holder",
            $this->alias . '.name AS holder_name',
            $this->alias . '.name_translit AS holder_translit',
            $this->alias . '.town AS place',
            'NULL AS place_tgn', // $this->alias . '.place_tgn AS place_tgn',
            $this->alias . '.country AS country_code', // 'P' . $this->alias . '.country_code AS country_code',
            'NULL AS foundingdate', // 'DATE(' . $this->alias . '.foundingdate) AS foundingdate',
            'NULL AS dissolutiondate', // DATE(' . $this->alias . '.dissolutiondate) AS dissolutiondate',
            $this->alias . '.gnd AS gnd',
            $this->alias . '.ulan AS ulan',
            $this->alias . '.type AS type',
            $this->alias . '.status AS status',
            'NULL AS place_geo', // $this->alias . '.place_geo',
            'P' . $this->alias . '.latitude',
            'P' . $this->alias . '.longitude',
            'COUNT(DISTINCT B.id) AS count_bibitem',
        ]);

        return $this;
    }

    protected function setFrom($queryBuilder)
    {
        $queryBuilder->from('Holder', $this->alias);

        return $this;
    }

    protected function setBibitemJoin($queryBuilder)
    {
        $queryBuilder->leftJoin($this->alias,
                                'HolderPublication', 'BH',
                                'BH.id_holder = ' . $this->alias . '.id');
        $queryBuilder->leftJoin('BH',
                                'Publication', 'B',
                                'BH.id_publication = B.id AND B.status <> -1');
        $queryBuilder->leftJoin('BH',
                       'ExhibitionPublication', 'BE',
                       'BE.id_publication = B.id AND BE.role = 1');// catalogues only
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
                                '1=0' // 'P' . $this->alias . '.tgn=' . $this->alias.'.place_tgn'
                                );

        $this->setBibitemJoin($queryBuilder);

        return $this;
    }

    protected function setFilter($queryBuilder)
    {
        $queryBuilder->andWhere($this->alias . '.status <> -1');

        $this->addSearchFilters($queryBuilder, [
            $this->alias . '.name',
            $this->alias . '.name_translit',
            // $this->alias . '.name_alternate',
            $this->alias . '.town',
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
