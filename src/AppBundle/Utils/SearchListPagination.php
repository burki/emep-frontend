<?php

namespace AppBundle\Utils;

use Ifedko\DoctrineDbalPagination\ListBuilder;

class SearchListPagination
{
    const DEFAULT_LIMIT = 20;
    const DEFAULT_OFFSET = 0;

    /**
     * @var \Ifedko\DoctrineDbalPagination\ListBuilder
     */
    private $listQueryBuilder;

    /**
     * @var callable|null
     */
    private $pageItemsMapCallback;

    /**
     * @param \Ifedko\DoctrineDbalPagination\ListBuilder $listQueryBuilder
     */
    public function __construct(ListBuilder $listQueryBuilder)
    {
        $this->listQueryBuilder = $listQueryBuilder;
    }

    /**
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function get($limit, $offset = 0)
    {
        $limit = (intval($limit) > 0) ? intval($limit) : self::DEFAULT_LIMIT;
        $offset = (intval($offset) >= 0) ? $offset : self::DEFAULT_OFFSET;

        $query = $this->listQueryBuilder->query();
        /*
        echo($query
            ->setMaxResults($limit)->setFirstResult($offset)->getSQL());
        */
        $pageItems = $query
            ->setMaxResults($limit)->setFirstResult($offset)->execute()->fetchAll();


        $conn = $query->getConnection();
        $stmt = $conn->query("SELECT FOUND_ROWS() AS found_rows");
        $totalResult = $stmt->fetchAll();

        return [
            'limit' => $limit,
            'offset' => $offset,
            'total' => $totalResult[0]['found_rows'],

            'items' => is_null($this->pageItemsMapCallback) ?
                $pageItems : array_map($this->pageItemsMapCallback, $pageItems),

            'sorting' => $this->listQueryBuilder->sortingParameters(),
        ];
    }

    public function getTotal()
    {
        // TODO: build a separate count method without all the joins
        // for now just a single entry without order to access total
        $query = $this->listQueryBuilder->query();
        $query->setMaxResults(1)->setFirstResult(0);

        // dirty hack to remove order
        $query->orderBy('', 'ASC');
        $sql = $query->getSQL();
        $sql = preg_replace('/ORDER BY \s*ASC\s* (LIMIT 1)/', '\1', $sql);

        $conn = $query->getConnection();
        $conn->query($sql)->fetchAll();

        $stmt = $conn->query("SELECT FOUND_ROWS() AS found_rows");
        $totalResult = $stmt->fetchAll();

        return $totalResult[0]['found_rows'];
    }

    public function definePageItemsMapCallback($callback)
    {
        $this->pageItemsMapCallback = $callback;
    }
}
