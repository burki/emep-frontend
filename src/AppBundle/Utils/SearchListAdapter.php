<?php

namespace AppBundle\Utils;

class SearchListAdapter
implements \Pagerfanta\Adapter\AdapterInterface
{
    var $listPaginationResult = null;

    function __construct($listPaginationResult)
    {
        $this->listPaginationResult = $listPaginationResult;
    }

    /**
     * {@inheritdoc}
     */
    public function getNbResults()
    {
        return $this->listPaginationResult['total'];
    }

    /**
     * {@inheritdoc}
     */
    public function getSlice($offset, $length)
    {
        return $this->listPaginationResult['items'];
    }
}
