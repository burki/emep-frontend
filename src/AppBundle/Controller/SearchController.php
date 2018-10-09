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
    const PAGE_SIZE = 50;

    static $entities = [
        'Venue',
        'ItemExhibition',
        'Exhibition',
        'Person',
    ];

    /**
     * @Route("/search", name="search")
     */
    public function searchAction(Request $request)
    {
        $entity = $request->get('entity');
        if (!in_array($entity, self::$entities)) {
            $entity = self::$entities[0];
        }

        $listBuilder = $this->instantiateListBuilder($entity);

        $listPagination = new SearchListPagination($listBuilder);

        $page = $request->get('page', 1);
        $listPage = $listPagination->get(self::PAGE_SIZE, ($page - 1) * self::PAGE_SIZE);

        return $this->renderResult($listPage, $entity);
    }

    protected function instantiateListBuilder($entity)
    {
        $connection = $this->getDoctrine()->getEntityManager()->getConnection();

        switch ($entity) {
            case 'Exhibition':
                return new ExhibitionListBuilder($connection);
                break;

            case 'Venue':
                return new VenueListBuilder($connection);
                break;

            case 'Person':
                return new PersonListBuilder($connection);
                break;

            case 'ItemExhibition':
            default:
                return new ItemExhibitionListBuilder($connection);
        }
    }

    protected function renderResult($listPage, $entity)
    {
        $adapter = new SearchListAdapter($listPage);
        $pager = new Pagerfanta($adapter);
        $pager->setMaxPerPage($listPage['limit']);
        $pager->setCurrentPage(intval($listPage['offset'] / $listPage['limit']) + 1);

        switch ($entity) {
            case 'Exhibition':
                return $this->render('Search/exhibition.html.twig', [
                    'pageTitle' => $this->get('translator')->trans('Search'),
                    'pager' => $pager,
                ]);
                break;

            case 'Venue':
                return $this->render('Search/location.html.twig', [
                    'pageTitle' => $this->get('translator')->trans('Search'),
                    'pager' => $pager,
                ]);
                break;

            case 'Person':
                return $this->render('Search/person.html.twig', [
                    'pageTitle' => $this->get('translator')->trans('Search'),
                    'pager' => $pager,
                ]);
                break;

            case 'ItemExhibition':
            default:
                return $this->render('Search/itemexhibition.html.twig', [
                    'pageTitle' => $this->get('translator')->trans('Search'),
                    'pager' => $pager,
                ]);
        }
    }
}

abstract class SearchListBuilder
extends ListBuilder
{
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
}

class ItemExhibitionListBuilder
extends SearchListBuilder
{
    var $entity = 'ItemExhibition';

    protected function setSelect($queryBuilder)
    {
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

        return $this;
    }

    protected function setFrom($queryBuilder)
    {
        $queryBuilder->from('ItemExhibition', 'IE');

        return $this;
    }

    protected function setJoin($queryBuilder)
    {
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

        return $this;
    }

    protected function setFilter($queryBuilder)
    {
        // default is not to show other media
        $queryBuilder->andWhere('IE.title IS NOT NULL');

        return $this;
    }

    protected function setOrder($queryBuilder) {
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

        foreach ($orders as $orderBy) {
            $queryBuilder->addOrderBy($orderBy);
        }

        return $this;
    }
}

class ExhibitionListBuilder
extends SearchListBuilder
{
    var $entity = 'Exhibition';

    protected function setSelect($queryBuilder)
    {
        $queryBuilder->select([
            'SQL_CALC_FOUND_ROWS E.id',
            'E.id AS exhibition_id',
            'E.title AS exhibition_title',
            'E.type AS type',
            'DATE(E.startdate) AS startdate',
            'enddate',
            'L.place AS place',
            'L.name AS location',
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
                                'E.id=IE.id_exhibition AND IE.title IS NOT NULL');
        $queryBuilder->leftJoin('E',
                                'Location', 'L',
                                'E.id_location=L.id AND L.status <> -1');
        $queryBuilder->leftJoin('E',
                                'ExhibitionLocation', 'EL',
                                'E.id=EL.id AND EL.role = 0');
        $queryBuilder->leftJoin('EL',
                                'Location', 'O',
                                'O.id=EL.id_location');
        return $this;
    }

    protected function setFilter($queryBuilder)
    {
        // default is not to show other media
        $queryBuilder->andWhere('E.status <> -1');

        return $this;
    }

    protected function setOrder($queryBuilder) {
        $orders = [
           'E.startdate',
           'E.id',
        ];

        foreach ($orders as $orderBy) {
            $queryBuilder->addOrderBy($orderBy);
        }

        return $this;
    }
}

class VenueListBuilder
extends SearchListBuilder
{
    var $entity = 'Location';

    protected function setSelect($queryBuilder)
    {
        $queryBuilder->select([
            'SQL_CALC_FOUND_ROWS L.id',
            'L.id AS location_id',
            'L.type AS type',
            'L.place AS place',
            'L.name AS location',
            'DATE(L.foundingdate) AS foundingdate',
            'DATE(L.dissolutiondate) AS dissolutiondate',
            'COUNT(DISTINCT IE.id) AS count_itemexhibition',
            'COUNT(DISTINCT IE.id_person) AS count_person',
        ]);

        return $this;
    }

    protected function setFrom($queryBuilder)
    {
        $queryBuilder->from('Location', 'L');

        return $this;
    }

    protected function setJoin($queryBuilder)
    {
        $queryBuilder->groupBy('L.id');

        $queryBuilder->leftJoin('L',
                                'Exhibition', 'E',
                                'E.id_location=L.id AND E.status <> -1');

        $queryBuilder->leftJoin('E',
                                'ItemExhibition', 'IE',
                                'E.id=IE.id_exhibition AND IE.title IS NOT NULL');

        return $this;
    }

    protected function setFilter($queryBuilder)
    {
        // default is not to show other media
        $queryBuilder->andWhere('L.status <> -1');

        return $this;
    }

    protected function setOrder($queryBuilder) {
        $orders = [
           'L.place',
           'L.name',
           'L.id',
        ];

        foreach ($orders as $orderBy) {
            $queryBuilder->addOrderBy($orderBy);
        }

        return $this;
    }
}

class PersonListBuilder
extends SearchListBuilder
{
    var $entity = 'Person';

    protected function setSelect($queryBuilder)
    {
        $queryBuilder->select([
            'SQL_CALC_FOUND_ROWS P.id',
            'P.id AS person_id',
            "CONCAT(lastname, ', ', IFNULL(firstname, '')) AS name",
            "DATE(birthdate) AS birthdate",
            "DATE(deathdate) AS deathdate",
            "CONCAT(IFNULL(P.ulan, ''), ' / ',  IFNULL(P.pnd, '')) AS pnd",
            'P.sex AS gender',
            'P.country AS country',
            'COUNT(DISTINCT IE.id) AS count_itemexhibition',
            'COUNT(DISTINCT IE.id_exhibition) AS count_exhibition',
        ]);

        return $this;
    }

    protected function setFrom($queryBuilder)
    {
        $queryBuilder->from('Person', 'P');

        return $this;
    }

    protected function setJoin($queryBuilder)
    {
        $queryBuilder->groupBy('P.id');

        $queryBuilder->leftJoin('P',
                                'ItemExhibition', 'IE',
                                'IE.id_person=P.id AND IE.title IS NOT NULL');

        return $this;
    }

    protected function setFilter($queryBuilder)
    {
        // default is not to show other media
        $queryBuilder->andWhere('P.status <> -1');

        return $this;
    }

    protected function setOrder($queryBuilder) {
        $orders = [
            'P.lastname',
            'P.firstname',
            'P.id',
        ];

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