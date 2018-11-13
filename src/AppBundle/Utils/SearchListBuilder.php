<?php

namespace AppBundle\Utils;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use Ifedko\DoctrineDbalPagination\ListBuilder;

abstract class SearchListBuilder
extends ListBuilder
{
    const STATUS_DELETED = -1;
    const STATUS_EDIT = 0;
    const STATUS_PUBLISHED = 1;

    const STATUS_PENDING = -10;
    const STATUS_COMPLETED = -3;
    const STATUS_PROOFREAD = -2;
    const STATUS_PENDINGIMG = -5;

    static $STATUS_LABELS = [
        self::STATUS_PENDING => 'pending',
        self::STATUS_EDIT => 'in progress',
        self::STATUS_COMPLETED => 'completed',
        self::STATUS_PROOFREAD => 'proof read',
        self::STATUS_PENDINGIMG => 'pictures pending',
        self::STATUS_PUBLISHED => 'published',
    ];

    /**
     * Remove any elements where the callback returns true
     *
     * @param  array    $array    the array to walk
     * @param  callable $callback callback takes ($value, $key, $userdata)
     * @param  mixed    $userdata additional data passed to the callback.
     * @return array
     */
    static function array_walk_recursive_delete(array &$array, callable $callback, $userdata = null)
    {
        foreach ($array as $key => &$value) {
            if (is_array($value)) {
                $value = self::array_walk_recursive_delete($value, $callback, $userdata);
            }
            if ($callback($value, $key, $userdata)) {
                unset($array[$key]);
            }
        }

        return $array;
    }

    var $request = null;
    var $urlGenerator = null;
    var $orders = [];
    var $queryFilters = [];

    public function __construct(\Doctrine\DBAL\Connection $connection,
                                Request $request,
                                UrlGeneratorInterface $urlGenerator,
                                $queryFilters = null)
    {
        parent::__construct($connection);

        $this->request = $request;
        $this->urlGenerator = $urlGenerator;

        if (is_null($queryFilters)) {
            $queryFilters = $this->request->get('filter');
        }

        $this->setQueryFilters($queryFilters);
    }

	protected function baseQuery()
	{
    	$queryBuilder = $this->getQueryBuilder();

        $this
            ->setSelect($queryBuilder)
            ->setFrom($queryBuilder)
            ->setJoin($queryBuilder)
            ->setFilter($queryBuilder)
            ->setOrder($queryBuilder);

        return $queryBuilder;
	}

    protected function determineSortOrder()
    {
        $sortKeys = array_keys($this->orders);

        $sort = $this->request->get('sort');

        if (!in_array($sort, $sortKeys)) {
            $sort = $sortKeys[0];
        }

        $sortOrders = array_keys($this->orders[$sort]);
        $order = $this->request->get('order');
        if (!in_array($order, $sortOrders)) {
            $order = $sortOrders[0];
        }

        return [ $sort, $order ];
    }

    public function getSortInfo($key)
    {
        $info = [];

        if (!array_key_exists($key, $this->orders)) {
            return $info;
        }

        $route = $this->request->get('_route');
        $params = $this->request->query->all();

        unset($params['page']);

        $sortKeys = array_keys($this->orders);
        if ($key == $sortKeys[0]) {
            unset($params['sort']);
        }
        else {
            $params['sort'] = $key;
        }

        unset($params['order']);

        list($sort, $order) = $this->determineSortOrder();
        if ($sort == $key) {
            $info['active'] = $order;
            // determine next order
            $orders = array_keys($this->orders[$key]);
            $pos = array_search($order, $orders);
            if ($pos != count($orders) - 1) {
                $params['order'] = $orders[$pos + 1];
            }
            $info['action'] = $this->urlGenerator->generate($route, $params);
        }
        else {
            $info['action'] = $this->urlGenerator->generate($route, $params);
        }

        return $info;
    }

    protected function setOrder($queryBuilder)
    {
        list($sort, $order) = $this->determineSortOrder();

        foreach ($this->orders[$sort][$order] as $orderBy) {
            $dir = 'ASC';
            if (preg_match('/(.+)\s+(asc|desc)\s*$/i', $orderBy, $matches)) {
                $orderBy = $matches[1];
                $dir = $matches[2];
            }

            $queryBuilder->addOrderBy($orderBy, $dir);
        }

        return $this;
    }

    public function getEntity()
    {
        return $this->entity;
    }

    public function setQueryFilters($queryFilters)
    {
        if (!empty($queryFilters) && is_array($queryFilters)) {
            // $form->getData() gets 'choices' of subforms we don't care about
            foreach (array_keys($queryFilters) as $key) {
                if ('choices' == $key) {
                    unset($queryFilters[$key]);
                }
                else if (is_array($queryFilters[$key]) && array_key_exists('choices', $queryFilters[$key])) {
                    unset($queryFilters[$key]['choices']);
                }
            }

            // remove empty options
            self::array_walk_recursive_delete($queryFilters, function ($val) {
                if (is_null($val)) {
                    return true;
                }

                if (is_array($val)) {
                    return empty($val);
                }
                else if (is_string($val)) {
                    return '' === trim($val);
                }

                return false;
            });
        }

        $this->queryFilters = $queryFilters;

        return $this;
    }

    public function getQueryFilters()
    {
        return $this->queryFilters;
    }

    public function buildLikeCondition($search, $fields, $basename = 'search')
    {
        $parts = preg_split('/\s+/', $search);

        $andParts = [];
        if (count($parts) == 0) {
            return $andParts;
        }

        $bind = [];
        for ($i = 0; $i < count($parts); $i++) {
            $term = trim($parts[$i]);
            if ('' === $term) {
                continue;
            }

            $key = $basename . $i;
            $bind[$key] = '%' . $term . '%';

            $orParts = [];
            for ($j = 0; $j < count($fields); $j++) {
                $orParts[] = $fields[$j] . " LIKE :" . $key;
            }
            $andParts[] = '(' . implode(' OR ', $orParts) . ')';
        }

        if (empty($andParts)) {
            return $andParts;
        }

        return [
            'andWhere' => $andParts,
            'parameters' => $bind,
        ];
    }

    protected function addSearchFilters($queryBuilder, $fields)
    {
        if (!empty($this->queryFilters['search'])) {
            $condition = $this->buildLikeCondition($this->queryFilters['search'], $fields);

            if (!empty($condition)) {
                foreach ($condition['parameters'] as $name => $value) {
                    $queryBuilder->setParameter($name, $value);
                }
                foreach ($condition['andWhere'] as $andWhere) {
                    $queryBuilder->andWhere($andWhere);
                }
            }
        }
    }

    protected function addQueryFilters($queryBuilder)
    {
        if (array_key_exists('person', $this->queryFilters)) {
            $personFilters = & $this->queryFilters['person'];
            foreach ([ 'gender' => 'P.sex', 'nationality' => 'P.country' ] as $key => $field) {
                if (!empty($personFilters[$key])) {
                    $queryBuilder->andWhere(sprintf('%s = %s',
                                                    $field, ':' . $key))
                        ->setParameter($key, $personFilters[$key]);
                }
            }

            foreach ([ 'birthdate' => 'P.birthdate', 'deathdate' => 'P.deathdate' ] as $key => $field) {
                if (!empty($personFilters[$key]) && is_array($personFilters[$key])) {
                    foreach ([ 'from', 'until'] as $part) {
                        if (array_key_exists($part, $personFilters[$key])) {
                            $paramName = $key . '_' . $part;
                            $queryBuilder->andWhere(sprintf('YEAR(%s) %s %s',
                                                            $field,
                                                            'from' == $part ? '>=' : '<',
                                                            ':' . $paramName))
                                ->setParameter($paramName,
                                               intval($personFilters[$key][$part])
                                               + ('until' == $part ? 1 : 0));
                        }
                    }
                }
            }
        }

        if (array_key_exists('location', $this->queryFilters)) {
            $locationFilters = & $this->queryFilters['location'];
            foreach ([ 'type' => 'L.type' ] as $key => $field) {
                if (!empty($locationFilters[$key])) {
                    $queryBuilder->andWhere(sprintf('%s = %s',
                                                    $field, ':' . $key))
                        ->setParameter($key, $locationFilters[$key]);
                }
            }

            // geoname can be cc:XY or tgn:12345
            if (!empty($locationFilters[$key = 'geoname'])) {
                $typeValue = explode(':', $locationFilters[$key], 2);
                if ('cc' == $typeValue[0]) {
                    $field = 'PL.country_code';
                }
                else {
                    $field = 'L.place_tgn';
                }
                $queryBuilder->andWhere(sprintf('%s = %s',
                                                $field, ':' . $key))
                    ->setParameter($key, $typeValue[1]);
            }
        }

        if (array_key_exists('exhibition', $this->queryFilters)) {
            $exhibitionFilters = & $this->queryFilters['exhibition'];
            foreach ([ 'type' => 'E.type', 'organizer_type' => 'E.organizer_type'  ] as $key => $field) {
                if (!empty($exhibitionFilters[$key])) {
                    $queryBuilder->andWhere(sprintf('%s = %s',
                                                    $field, ':' . $key))
                        ->setParameter($key, $exhibitionFilters[$key]);
                }
            }

            foreach ([ 'date' => 'E.startdate' ] as $key => $field) {
                if (!empty($exhibitionFilters[$key]) && is_array($exhibitionFilters[$key])) {
                    foreach ([ 'from', 'until' ] as $part) {
                        if (array_key_exists($part, $exhibitionFilters[$key])) {
                            $paramName = $key . '_' . $part;
                            $queryBuilder->andWhere(sprintf('YEAR(%s) %s %s',
                                                            $field,
                                                            'from' == $part ? '>=' : '<',
                                                            ':' . $paramName))
                                ->setParameter($paramName,
                                               intval($exhibitionFilters[$key][$part])
                                               + ('until' == $part ? 1 : 0));
                        }
                    }
                }
            }
        }

        if (array_key_exists('catentry', $this->queryFilters)) {
            $itemExhibitionFilters = & $this->queryFilters['catentry'];
            foreach ([ 'type' => 'IE.type', 'forsale' => 'IE.forsale' ] as $key => $field) {
                if (!empty($itemExhibitionFilters[$key])) {
                    $queryBuilder->andWhere(sprintf('%s = %s',
                                                    $field, ':' . $key))
                        ->setParameter($key, $itemExhibitionFilters[$key]);
                }
            }

            if (!empty($itemExhibitionFilters['price_available'])) {
                $queryBuilder->andWhere('IE.price IS NOT NULL');
            }
        }
    }

    protected function buildStatusLabel($status)
    {
        return array_key_exists($status, self::$STATUS_LABELS)
            ? self::$STATUS_LABELS[$status] : $status;
    }

    protected function buildLinkedValue($val, $route, $routeParams, $format)
    {
        if ('html' != $format) {
            return false;
        }

        return sprintf('<a href="%s">%s</a>',
                       $this->urlGenerator->generate($route, $routeParams),
                       $this->formatRowValue($val, [], $format));
    }

    public function buildHeaderRow()
    {
        $ret = [];

        foreach ($this->rowDescr as $key => $descr) {
            $ret[$key] = array_key_exists('label', $descr)
                ? $descr['label'] : '';
        }

        return $ret;
    }

    protected function formatRowValue($val, $descr, $format)
    {
        if (is_null($val)) {
            return '';
        }

        if ('plain' == $format) {
            return $val;
        }

        return htmlspecialchars($val, ENT_COMPAT, 'utf-8');
    }

    protected function formatPrice($val, $currency)
    {
        static $currencies = null;

        if (empty($val) || empty($currency)) {
            return $val;
        }

        if (is_null($currencies)) {
            $currencies = [];

            $fpath = realpath(__DIR__ . '/../Resources/currencies.txt');
            if (false !== $fpath) {
                $lines = file($fpath);
                $col_name = 2;

                foreach ($lines as $line) {
                    $line = chop($line);
                    $parts = preg_split('/\t/', $line);
                    if (!empty($parts[0])) {
                        $currencies[$parts[0]] = $parts[1];
                    }
                }
            }
        }

        $symbol = array_key_exists($currency, $currencies)
            ? $currencies[$currency] : $currency;

        return $symbol . "\xC2\xA0" . $val;
    }

    public function buildRow($row, $format = 'plain')
    {
        $ret = [];
        foreach ($this->rowDescr as $key => $descr) {
            $val = null;

            if (array_key_exists('buildValue', $descr)) {
                $val = $descr['buildValue']($row, array_key_exists($key, $row) ? $row[$key] : $val,
                                            $this, $key, $format);
                if (false === $val && array_key_exists($key, $row)) {
                    // fall back to default
                    $val = $this->formatRowValue($row[$key], $descr, $format);
                }
            }
            else if (array_key_exists($key, $row)) {
                $val = $this->formatRowValue($row[$key], $descr, $format);
            }

            $ret[] = $val;
        }

        return $ret;
    }
}
