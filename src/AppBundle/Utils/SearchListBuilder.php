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
    const STATUS_PROOFREAD = -4;
    const STATUS_PENDINGIMG = -5;

    const STATUS_INTERNALONLY = -2;

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

    static function exhibitionVisibleCondition($alias = 'Exhibition')
    {
        return sprintf('%s.status NOT BETWEEN %d AND %d',
                       $alias,
                       min(self::STATUS_INTERNALONLY, self::STATUS_DELETED),
                       max(self::STATUS_INTERNALONLY, self::STATUS_DELETED));
    }

    static function buildExhibitionTitleListing(&$row)
    {
        if (!empty($row['exhibition_translit'])) {
            // show translit / translation in brackets instead of original
            $parts = [ $row['exhibition_translit'] ];

            if (!empty($row['exhibition_alternate'])) {
                $parts[] = $row['exhibition_alternate'];
            }

            return sprintf('[%s]',
                           join(' : ', $parts));
        }

        return $row['exhibition'];
    }

    static function buildLocationNameListing(&$row)
    {
        if (!empty($row['location_translit'])) {
            // show translit / translation in brackets instead of original
            $parts = [ $row['location_translit'] ];

            if (!empty($row['location_alternate'])) {
                $parts[] = $row['location_alternate'];
            }

            return sprintf('[%s]',
                           join(' : ', $parts));
        }

        return $row['location'];
    }

    static function buildHolderNameListing(&$row)
    {
        if (!empty($row['holder_translit'])) {
            // show translit / translation in brackets instead of original
            $parts = [ $row['holder_translit'] ];

            if (!empty($row['holder_alternate'])) {
                $parts[] = $row['holder_alternate'];
            }

            $prefix = preg_match('/^online\:/', $row['place'])
                ? ''
                : $row['place'] . ', ';

            return $prefix
                . sprintf('[%s]',
                          join(' : ', $parts));
        }

        return $row['holder'];
    }

    static function buildItemExhibitionTitleListing(&$row)
    {
        if (!empty($row['title_translit'])) {
            // show translit / translation in brackets instead of original
            $parts = [ $row['title_translit'] ];

            if (!empty($row['title_alternate'])) {
                $parts[] = $row['title_alternate'];
            }

            return sprintf('[%s]',
                           join(' : ', $parts));
        }

        return $row['title'];
    }

    var $request = null;
    var $urlGenerator = null;
    var $orders = [];
    var $queryFilters = [];
    var $mode = null;

    public function __construct(\Doctrine\DBAL\Connection $connection,
                                Request $request = null,
                                UrlGeneratorInterface $urlGenerator = null,
                                $queryFilters = null)
    {
        parent::__construct($connection);

        $this->request = $request;
        $this->urlGenerator = $urlGenerator;

        if (is_null($queryFilters)) {
            $queryFilters = !is_null($this->request) ? $this->request->get('filter') : [];
        }

        $this->setQueryFilters($queryFilters);
    }

    public function getQueryBuilder()
    {
        return parent::getQueryBuilder();
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
        $sort = null;
        if (!is_null($this->request)) {
            $sort = $this->request->get('sort');
        }

        $sortKeys = array_keys($this->orders);
        if (!in_array($sort, $sortKeys)) {
            $sort = $sortKeys[0];
        }

        $order = null;
        if (!is_null($this->request)) {
            $order = $this->request->get('order');
        }

        $sortOrders = array_keys($this->orders[$sort]);
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
        if (empty($this->orders)) {
            // certain stat-queries don't have an ORDER BY part
            return $this;
        }

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

    private function getObjectIdentifier($obj)
    {
        if ($obj instanceof \AppBundle\Entity\Place) {
            return $obj->getTgn();
        }

        return $obj->getId();
    }

    public function getQueryFilters($entityToId = false)
    {
        if (!$entityToId) {
            return $this->queryFilters;
        }

        $ret = $this->queryFilters;
        if (is_array($ret)) {
            array_walk_recursive($ret, function (&$item, $key) {
                if (is_object($item)) {
                    $itemIdentifier = $this->getObjectIdentifier($item);
                    $item = $itemIdentifier;
                }
            });
        }

        return $ret;
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

    protected function addInFilter($queryBuilder, $field, $key, $filterValues)
    {
        if (!empty($filterValues)) {
            if (is_array($filterValues) && 1 == count($filterValues)) {
                $filterValues = $filterValues[0];
                if (is_object($filterValues)) {
                    $filterValues = $this->getObjectIdentifier($filterValues);
                }
            }

            if (is_scalar($filterValues)) {
                if ($filterValues < 0) {
                    // negate
                    $queryBuilder->andWhere(sprintf('%s <> %s',
                                                    $field, ':' . $key))
                        ->setParameter($key, - $filterValues);
                }
                else {
                    $queryBuilder->andWhere(sprintf('%s = %s',
                                                    $field, ':' . $key))
                        ->setParameter($key, $filterValues);
                }
            }
            else {
                $negated = array_filter($filterValues, function ($val) {
                    $v = is_object($val) ? $val->getId() : $val;

                    return $v < 0;
                });

                $filterValues = array_filter($filterValues, function ($val) {
                    $v = is_object($val) ? $val->getId() : $val;

                    return $v >= 0;
                });

                if (count($filterValues) > 0) {
                    $queryBuilder->andWhere(sprintf('%s IN (%s)',
                                                    $field, ':' . $key))
                        ->setParameter($key,
                                       array_map(function ($val) {
                                            return is_object($val)
                                                ? $this->getObjectIdentifier($val)
                                                : $val;
                                        }, $filterValues),
                                       \Doctrine\DBAL\Connection::PARAM_STR_ARRAY);
                }

                if (count($negated) > 0) {
                    $key = 'not_' . $key;
                    $queryBuilder->andWhere(sprintf('%s NOT IN (%s)',
                                                    $field, ':' . $key))
                        ->setParameter($key,
                                       array_map(function ($val) {
                                            return - (is_object($val) ? $val->getId() : $val);
                                        }, $negated),
                                       \Doctrine\DBAL\Connection::PARAM_STR_ARRAY);
                }
            }
        }
    }

    protected function addGeonameFilter($queryBuilder, $fieldMap, $key, $filterValues)
    {
        $values = [
            'cc' => [],
            'tgn' => [],
        ];

        if (!is_array($filterValues)) {
            $filterValues = [ $filterValues ];
        }

        foreach ($filterValues as $filterValue) {
            // geoname can be cc:XY or tgn:12345
            $typeValue = explode(':', $filterValue, 2);
            if (in_array($typeValue[0], [ 'cc', 'tgn' ])) {
                $values[$typeValue[0]][] = $typeValue[1];
            }
        }

        $orParts = [];

        foreach ($values as $type => $active) {
            if (!empty($active)) {
                $field = $fieldMap[$type];
                $parameter = $key . '_' . $type;
                if (1 == count($active)) {
                    $orParts[] = sprintf('%s = %s',
                                         $field, ':' . $parameter);
                    $queryBuilder->setParameter($parameter, $active[0]);
                }
                else {
                    $orParts[] = sprintf('%s IN(%s)',
                                         $field, ':' . $parameter);
                    $queryBuilder->setParameter($parameter, $active, \Doctrine\DBAL\Connection::PARAM_STR_ARRAY);
                }
            }
        }

        if (!empty($orParts)) {
            $queryBuilder->andWhere(join(' OR ', $orParts));
        }
    }

    protected function addQueryFilters($queryBuilder)
    {
        if (array_key_exists('exhibition', $this->queryFilters)) {
            $exhibitionFilters = & $this->queryFilters['exhibition'];

            foreach ([
                'type' => 'E.type',
                // 'organizer_type' => 'E.organizer_type',
                'exhibition' => 'E.id',
                ] as $key => $field)
            {
                $this->addInFilter($queryBuilder, $field, 'exhibition_' . $key,
                                   array_key_exists($key,  $exhibitionFilters) ? $exhibitionFilters[$key] : null);
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

            if (!empty($exhibitionFilters['flags'])) {
                // we and on every flag
                foreach ($exhibitionFilters['flags'] as $i => $flag) {
                    if ('preface' == $flag) {
                        $queryBuilder->andWhere('E.preface IS NOT NULL');
                    }
                    else if ('itemexhibition-missing' == $flag) {
                        $queryBuilder->andWhere('IE.id IS NULL');
                    }
                    else if ('itemexhibition-required' == $flag) {
                        $queryBuilder->andWhere('IE.id IS NOT NULL');
                    }
                    else if (0 <> intval($flag)) {
                        $paramName = 'exhibition_flags_' . $i;
                        $queryBuilder->andWhere(sprintf('(E.flags & %s) <> 0',
                                                        ':' . $paramName))
                            ->setParameter($paramName, intval($flag));
                    }
                }
            }
        }

        if (array_key_exists('person', $this->queryFilters)) {
            $personFilters = & $this->queryFilters['person'];

            foreach ([
                'gender' => 'P.sex',
                'nationality' => 'P.country',
                'birthplace' => 'P.birthplace_tgn',
                'deathplace' => 'P.deathplace_tgn',
                'person' => 'P.id',
                ] as $key => $field)
            {
                $this->addInFilter($queryBuilder, $field, $key,
                                   array_key_exists($key,  $personFilters) ? $personFilters[$key] : null);
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

            if (!empty($personFilters['additional'])) {
                foreach ($personFilters['additional'] as $i => $key) {
                    switch ($key) {
                        case 'address_available':
                            $queryBuilder->andWhere('P.actionplace IS NOT NULL');
                            break;
                    }
                }
            }
        }

        if (array_key_exists('location', $this->queryFilters)) {
            $locationFilters = & $this->queryFilters['location'];

            foreach ([ 'type' => 'L.type', 'location' => 'L.id' ] as $key => $field) {
                $this->addInFilter($queryBuilder, $field, 'location_' . $key,
                                   array_key_exists($key,  $locationFilters) ? $locationFilters[$key] : null);
            }

            if (!empty($locationFilters[$key = 'geoname'])) {
                $this->addGeonameFilter($queryBuilder, [
                                            'cc' => 'PL.country_code',
                                            'tgn' => 'L.place_tgn',
                                        ], $key, $locationFilters[$key]);
            }
        }

        if (array_key_exists('organizer', $this->queryFilters)) {
            $organizerFilters = & $this->queryFilters['organizer'];
            foreach ([ 'type' => 'O.type', 'organizer' => 'O.id' ] as $key => $field) {
                $this->addInFilter($queryBuilder, $field, 'organizer_' . $key,
                                   array_key_exists($key,  $organizerFilters) ? $organizerFilters[$key] : null);
            }

            if (!empty($organizerFilters[$key = 'geoname'])) {
                $this->addGeonameFilter($queryBuilder, [
                                            'cc' => 'PO.country_code',
                                            'tgn' => 'O.place_tgn',
                                        ], $key . '_organizer', $organizerFilters[$key]);
            }
        }

        if (array_key_exists('holder', $this->queryFilters)) {
            $holderFilters = & $this->queryFilters['holder'];

            foreach ([ 'holder' => 'H.id' ] as $key => $field) {
                $this->addInFilter($queryBuilder, $field, 'holder_' . $key,
                                   array_key_exists($key,  $holderFilters) ? $holderFilters[$key] : null);
            }

            if (!empty($holderFilters[$key = 'geoname'])) {
                $this->addGeonameFilter($queryBuilder, [
                                            'cc' => 'H.country',
                                            'tgn' => 'H.place_tgn',
                                        ], $key, $holderFilters[$key]);
            }
        }

        if (array_key_exists('place', $this->queryFilters)) {
            $placeFilters = & $this->queryFilters['place'];

            if (!empty($placeFilters[$key = 'geoname'])) {
                $this->addGeonameFilter($queryBuilder, [
                                            'cc' => 'PL.country_code',
                                            'tgn' => 'PL.tgn',
                                        ], $key, $placeFilters[$key]);
            }
        }

        if (array_key_exists('catentry', $this->queryFilters)) {
            $itemExhibitionFilters = & $this->queryFilters['catentry'];

            foreach ([ 'type' => 'IE.type', 'forsale' => 'IE.forsale' ] as $key => $field) {
                $this->addInFilter($queryBuilder, $field, 'itemexhibition_' . $key,
                                   array_key_exists($key,  $itemExhibitionFilters) ? $itemExhibitionFilters[$key] : null);
            }

            if (!empty($itemExhibitionFilters['price_available'])) {
                $queryBuilder->andWhere('IE.price IS NOT NULL');
            }

            if (!empty($itemExhibitionFilters['owner_available'])) {
                $queryBuilder->andWhere('IE.owner IS NOT NULL');
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

    protected function buildLinkedExhibition(&$row, $val, $format)
    {
        if ('html' != $format) {
            return false;
        }

        $val = self::buildExhibitionTitleListing($row);

        return sprintf('<a href="%s">%s</a>',
                       $this->urlGenerator->generate('exhibition', [ 'id' => $row['exhibition_id'] ]),
                       $this->formatRowValue($val, [], $format));
    }

    protected function buildLinkedItemExhibition(&$row, $val, $format)
    {
        if ('html' != $format || empty($val)) {
            return false;
        }

        $val = self::buildItemExhibitionTitleListing($row);

        return sprintf('<a href="%s#%s">%s</a>',
                       $this->urlGenerator->generate('itemexhibition', [
                            'id' => $row['exhibition_id'],
                            'itemexhibitionId' => $row['id'],
                       ]),
                       $row['id'],
                       $this->formatRowValue($val, [], $format));
    }

    protected function buildLinkedLocation(&$row, $val, $format, $route_name = 'location')
    {
        if ('html' != $format) {
            return false;
        }

        $val = self::buildLocationNameListing($row);

        return sprintf('<a href="%s">%s</a>',
                       $this->urlGenerator->generate($route_name, [ 'id' => $row['location_id'] ]),
                       $this->formatRowValue($val, [], $format));
    }

    protected function buildLinkedOrganizer(&$row, $val, $format)
    {
        return $this->buildLinkedLocation($row, $val, $format /*, 'organizer' */); // currently use same route
    }

    protected function buildLinkedHolder(&$row, $val, $format)
    {
        if ('html' != $format || empty($val)) {
            return false;
        }

        $val = self::buildHolderNameListing($row);

        return sprintf('<a href="%s">%s</a>',
                       $this->urlGenerator->generate('holder', [
                            'id' => $row['holder_id'],
                       ]),
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

    public function getColumnInfo($key)
    {
        if (array_key_exists($key, $this->rowDescr)) {
            return $this->rowDescr[$key];
        }
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

    protected function buildCurrencySymbol($currency)
    {
        static $currencies = null;

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

        return $symbol;
    }

    protected function formatPrice($val, $currency)
    {
        if (empty($val) || empty($currency)) {
            return $val;
        }

        return $this->buildCurrencySymbol($currency) . "\xC2\xA0" . $val;
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

    protected function buildExhibitionVisibleCondition($alias = 'Exhibition')
    {
        return self::exhibitionVisibleCondition($alias);
    }
}
