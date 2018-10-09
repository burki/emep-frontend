<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Ifedko\DoctrineDbalPagination\ListBuilder;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

use Pagerfanta\Pagerfanta;

/**
 *
 */
class SearchController
extends Controller
{
    static $entities = [ 'ItemExhibition' ];
    const PAGE_SIZE = 50;

    /**
     * @Route("/search", name="search")
     */
    public function searchAction(Request $request)
    {
        $entity = $request->get('entity');
        if (!in_array($entity, self::$entities)) {
            $entity = self::$entities[0];
        }

        $listBuilder = $this->instantiateListBuilder();

        $listPagination = new SearchListPagination($listBuilder);

        $page = $request->get('page', 1);
        $listPage = $listPagination->get(self::PAGE_SIZE, ($page - 1) * self::PAGE_SIZE);

        return $this->renderResult($listPage, $entity);
    }

    protected function instantiateListBuilder()
    {
        return new SearchListBuilder($this->getDoctrine()->getEntityManager()->getConnection());
    }

    protected function renderResult($listPage, $entity)
    {
        $adapter = new SearchListAdapter($listPage);
        $pager = new Pagerfanta($adapter);
        $pager->setMaxPerPage($listPage['limit']);
        $pager->setCurrentPage(intval($listPage['offset'] / $listPage['limit']) + 1);

        switch ($entity) {
            case 'ItemExhibition':
            default:
                return $this->render('Search/itemexhibition.html.twig', [
                    'pageTitle' => $this->get('translator')->trans('Search'),
                    'pager' => $pager,
                    // 'form' => $form->createView(),
                ]);
        }
    }
}

class SearchListBuilder
extends ListBuilder
{
    var $entity = 'ItemExhibition';

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

    protected function setSelect($queryBuilder)
    {
        switch ($this->entity) {
            case 'ItemExhibition':
                $queryBuilder->select([
                    'SQL_CALC_FOUND_ROWS IE.id',
                    'IE.id AS id',
                    "CONCAT(P.lastname, ', ', IFNULL(P.firstname, '')) AS person",
                    "CONCAT(IFNULL(YEAR(birthdate), ''), IF(deathdate IS NOT NULL, CONCAT('-', YEAR(deathdate)), '')) AS lifespan",
                    'P.sex AS gender',
                    'P.country AS country',
                    'IE.catalogueId AS catalogueId',
                    'IE.title AS title',
                    'TypeTerm.name AS type',
                    'IE.displaydate AS displaydate',
                    'IE.owner AS owner',
                    'IE.forsale AS forsale',
                    'IE.price AS price',
                    "E.title AS exhibition_title",
                    'DATE(E.startdate) AS startdate',
                    "E.type AS exhibition_type",
                    'L.name AS location',
                    'L.place AS place',
                    'L.place_tgn AS place_tgn',
                    'E.organizer_type AS organizer_type',
                    "GROUP_CONCAT(O.name ORDER BY EL.ord SEPARATOR '; ')",
                    "1 AS status",
                ]);
                break;
        }

        return $this;
    }

    protected function setFrom($queryBuilder)
    {
        switch ($this->entity) {
            case 'ItemExhibition':
                $queryBuilder->from('ItemExhibition', 'IE');
                break;
        }

        return $this;
    }

    protected function setJoin($queryBuilder)
    {
        switch ($this->entity) {
            case 'ItemExhibition':
                $queryBuilder->groupBy('IE.id');

                $queryBuilder->leftJoin('IE',
                                        'Person', 'P',
                                        'P.id=IE.id_person AND P.status <> -1');
                $queryBuilder->join('IE',
                                        'Exhibition', 'E',
                                        'E.id=IE.id_exhibition AND E.status <> -1');
                $queryBuilder->leftJoin('IE',
                                        'Term', 'TypeTerm',
                                        'IE.type=TypeTerm.id');
                $queryBuilder->leftJoin('E',
                                        'Location', 'L',
                                        'E.id_location=L.id AND L.status <> -1');
                $queryBuilder->leftJoin('E',
                                        'ExhibitionLocation', 'EL',
                                        'E.id=EL.id AND EL.role = 0');
                $queryBuilder->leftJoin('EL',
                                        'Location', 'O',
                                        'O.id=EL.id_location');
        }

        return $this;
    }

    protected function setFilter($queryBuilder)
    {
        switch ($this->entity) {
            case 'ItemExhibition':
                // default is not to show other media
                $queryBuilder->andWhere('IE.title IS NOT NULL');
                break;
        }

        return $this;
    }

    protected function setOrder($queryBuilder) {
        $orders = [];

        switch ($this->entity) {
            case 'ItemExhibition':
                $orders = [
                   'E.startdate',
                   'E.id',
                   'IE.catalogue_section',
                   'CAST(IE.catalogueId AS unsigned)',
                   'IE.catalogueId',
                   'person',
                   'title',
                   'IE.id',
                ];
        }

        foreach ($orders as $orderBy) {
            $queryBuilder->addOrderBy($orderBy);
        }

        return $this;
    }
}

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
    public function get($limit, $offset)
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

            'sorting' => $this->listQueryBuilder->sortingParameters()
        ];
    }

    public function definePageItemsMapCallback($callback)
    {
        $this->pageItemsMapCallback = $callback;
    }
}

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