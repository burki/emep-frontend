<?php

namespace AppBundle\Utils;

class SearchListAdapter implements \Pagerfanta\Adapter\AdapterInterface
{
    var $listPaginationResult = null;

    function __construct($listPaginationResult)
    {
        $this->listPaginationResult = $listPaginationResult;
    }

    /**
     * {@inheritdoc}
     */
    public function getNbResults(): int
    {
        return $this->listPaginationResult['total'];
    }

    /**
     * {@inheritdoc}
     */
    public function getSlice(int $offset, int $length): iterable
    {
        return $this->listPaginationResult['items'];
    }
}
