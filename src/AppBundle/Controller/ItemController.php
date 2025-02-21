<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Various functions for work-related analysis, currently not tested in public front-end
 */
class ItemController extends BaseController
{
    use SharingBuilderTrait;

    protected function buildCollections()
    {
        $em = $this->getDoctrine()
                ->getManager();

        $result = $em->createQuery("SELECT C.id, C.name FROM AppBundle\Entity\Collection C"
                                   . " WHERE C.status <> -1"
                                   . " ORDER BY C.name")
                ->getResult();

        $collections = [];

        foreach ($result as $row) {
            $collections[$row['id']] = $row['name'];
        }

        return $collections;
    }

    /**
     * @Route("/work", name="item-index")
     * @Route("/work/by-exhibition", name="item-by-exhibition")
     * @Route("/work/by-style", name="item-by-style")
     */
    public function indexAction(
        Request $request,
        TranslatorInterface $translator
    ) {
        $route = $request->get('_route');

        $qb = $this->getDoctrine()
                ->getManager()
                ->createQueryBuilder();

        $qbPerson = $this->getDoctrine()
                ->getManager()
                ->createQueryBuilder();

        $qbPerson->select('P')
            ->distinct()
            ->from('AppBundle\Entity\Person', 'P')
            ->innerJoin('AppBundle\Entity\ItemPerson', 'IP', 'WITH', 'IP.person=P')
            ->innerJoin('AppBundle\Entity\Item', 'I', 'WITH', 'IP.item=I')
            ->where('I.status <> -1')
            ->orderBy('P.familyName');

        $templateAppend = '';
        if ('item-by-exhibition' == $route) {
            $templateAppend = '-by-exhibition';
            $qb->select([
                'E',
                "P.familyName HIDDEN nameSort",
                "COALESCE(I.earliestdate, I.creatordate) HIDDEN dateSort",
                "I.catalogueId HIDDEN catSort",
            ])
                ->from('AppBundle\Entity\Exhibition', 'E')
                ->innerJoin('E.items', 'I')
                ->leftJoin('I.creators', 'P')
                ->leftJoin('I.media', 'M')
                ->where('E.status <> -1 AND I.status <> -1')
                ->orderBy('E.startdate, E.id')
                // , nameSort, dateSort, catSort')
            ;

            $qbPerson
                ->innerJoin('I.exhibitions', 'E');
        }
        else if ('item-by-style' == $route) {
            $templateAppend = '-by-style';
            $qb->select([
                'I',
                'T.name HIDDEN styleSort',
                "COALESCE(I.earliestdate, I.creatordate) HIDDEN dateSort",
                "P.familyName HIDDEN nameSort",
                "I.catalogueId HIDDEN catSort",
            ])
                ->from('AppBundle\Entity\Item', 'I')
                ->leftJoin('I.creators', 'P')
                ->leftJoin('I.style', 'T')
                ->where('I.status <> -1 AND P.status <> -1')
                ->orderBy('styleSort DESC, dateSort, nameSort, catSort')
            ;
            $qbPerson
                ->innerJoin('I.style', 'T');
        }
        else {
            $qb->select([
                'I',
                "P.familyName HIDDEN nameSort",
                "COALESCE(I.earliestdate, I.creatordate) HIDDEN dateSort",
                "I.catalogueId HIDDEN catSort",
            ])
                ->from('AppBundle\Entity\Item', 'I')
                ->leftJoin('I.creators', 'P')
                ->where('I.status <> -1 AND P.status <> -1')
                ->orderBy('nameSort, dateSort, catSort')
            ;
            unset($qbPerson);
        }

        $persons = null;
        if (isset($qbPerson)) {
            $person = $request->get('person');
            if (!empty($person) && intval($person) > 0) {
                $qb->andWhere(sprintf('P.id=%d', intval($person)));
            }

            $persons = $qbPerson->getQuery()->getResult();
        }

        $collections = $this->buildCollections();
        $collection = $request->get('collection');

        if (!empty($collection) && array_key_exists($collection, $collections)) {
            $qb->innerJoin('I.collection', 'C');
            $qb->andWhere(sprintf('C.id=%d', intval($collection)));
        }

        $results = $qb->getQuery()
            // ->setMaxResults(10) // for testing
            ->getResult();

        return $this->render('Item/index'
                             . $templateAppend
                             . '.html.twig', [
                                 'pageTitle' => $translator->trans('Works'),
                                 'results' => $results,
                                 'persons' => $persons,
                                 'collections' => $collections,
                                 'collection' => $collection,
                             ]);
    }

    /**
     * @Route("/work/{id}", requirements={"id" = "\d+"}, name="item")
     */
    public function detailAction(Request $request, $id = null)
    {
        $routeName = $request->get('_route');
        $routeParams = [];

        $repo = $this->getDoctrine()
                ->getRepository('AppBundle\Entity\Item');

        if (!empty($id)) {
            $routeParams = [ 'id' => $id ];
            $item = $repo->findOneById($id);
        }

        if (!isset($item) || $item->getStatus() == -1) {
            return $this->redirectToRoute('item-index');
        }

        $locale = $request->getLocale();
        if (in_array($request->get('_route'), [ 'item-jsonld' ])) {
            return new JsonLdResponse($item->jsonLdSerialize($locale));
        }

        return $this->render('Item/detail.html.twig', [
            'pageTitle' => $item->title, // TODO: dates in brackets
            'item' => $item,
            'pageMeta' => [
                /*
                'jsonLd' => $item->jsonLdSerialize($locale),
                'og' => $this->buildOg($request, $item, $routeName, $routeParams),
                'twitter' => $this->buildTwitter($item, $routeName, $routeParams),
                */
            ],
        ]);
    }

    /**
     * TODO: rename since we added cities as well
     *
     * @Route("/work/by-person", name="item-by-person")
     */
    public function itemByPersonAction(Request $request)
    {
        $collections = $this->buildCollections();
        $collection = $request->get('collection');

        $collectionCondition = '';
        if (!empty($collection) && array_key_exists($collection, $collections)) {
            $collectionCondition = sprintf(
                ' AND Item.collection=%d',
                intval($collection)
            );
        }

        // display the number of works / exhibited works by artist
        $em = $this->getDoctrine()->getManager();

        $dbconn = $em->getConnection();

        $querystr = "SELECT 'items' AS type, COUNT(*) AS how_many FROM Item"
                  . " WHERE status <> -1"
                  . $collectionCondition
        ;

        $querystr .= " UNION SELECT 'total' AS type, COUNT(ItemExhibition.id) AS how_many"
                   . " FROM Item INNER JOIN ItemExhibition ON Item.id=ItemExhibition.id_item"
                   . " WHERE Item.status <> -1" . $collectionCondition;

        $stmt = $dbconn->query($querystr);
        $subtitle_parts = [];
        while ($row = $stmt->fetch()) {
            if ('active' == $row['type']) {
                $total_active = $row['how_many'];
            }

            $subtitle_parts[] = $row['how_many'];
        }

        $subtitle = implode(' out of ', $subtitle_parts) . ' persons';

        // by person
        $data = [];
        $styles = [];
        foreach ([ 'works', 'works_exhibited', 'exhibitions' ] as $key) {
            if ('works_exhibited' == $key) {
                $querystr = "SELECT COUNT(ItemExhibition.id) AS how_many, Person.lastname, Person.firstname"
                          . ' FROM Person'
                          . ' INNER JOIN ItemPerson ON Person.id=ItemPerson.id_person'
                          . ' INNER JOIN Item ON ItemPerson.id_item = Item.id' . $collectionCondition
                          . ' INNER JOIN ItemExhibition ON ItemExhibition.id_item=Item.id'
                          . ' WHERE Person.status <> -1 AND Item.status <> -1'
                          . ' GROUP BY Person.id'
                          . ' ORDER BY Person.lastname, Person.firstname, Person.id'
                ;
            }
            else if ('exhibitions' == $key) {
                $querystr = 'SELECT COUNT(DISTINCT ItemExhibition.id_exhibition) AS how_many, Person.lastname, Person.firstname'
                          . ' FROM Person'
                          . ' INNER JOIN ItemPerson ON Person.id=ItemPerson.id_person'
                          . ' INNER JOIN Item ON ItemPerson.id_item = Item.id' . $collectionCondition
                          . ' INNER JOIN ItemExhibition ON ItemExhibition.id_item=Item.id'
                          . ' WHERE Person.status <> -1 AND Item.status <> -1'
                          . ' GROUP BY Person.id'
                          . ' ORDER BY Person.lastname, Person.firstname, Person.id'
                ;
            }
            else {
                $querystr = "SELECT COUNT(Item.id) AS how_many, Person.lastname, Person.firstname, IFNULL(Term.name, 'unknown') AS style"
                          . ' FROM Person'
                          . ' INNER JOIN ItemPerson ON Person.id=ItemPerson.id_person'
                          . ' INNER JOIN Item ON ItemPerson.id_item = Item.id' . $collectionCondition
                          . ' LEFT OUTER JOIN Term ON Item.style=Term.id'
                          . ' WHERE Person.status <> -1 AND Item.status <> -1'
                          . ' GROUP BY Person.id, style'
                          . ' ORDER BY Person.lastname, Person.firstname, Person.id, style'
                ;
            }

            $stmt = $dbconn->query($querystr);

            while ($row = $stmt->fetch()) {
                $fullname = $row['lastname'] . ', ' . $row['firstname'];

                if ('works' == $key) {
                    $style = $row['style'];
                    if (!in_array($style, $styles)) {
                        $styles[] = $style;
                    }
                    $data[$fullname][$key][$style] = $row['how_many'];
                }
                else {
                    $data[$fullname][$key] = $row['how_many'];
                }
            }
        }

        $total = [];
        $categories = array_keys($data);
        for ($i = 0; $i < count($categories); $i++) {
            $category = $categories[$i];
            foreach ([ 'works', 'works_exhibited', 'exhibitions' ] as $key) {
                if ('works' == $key) {
                    foreach ($styles as $style) {
                        $total[$key][$style][] = [
                            'name' => $category,
                            'y' => isset($data[$category][$key]) && isset($data[$category][$key][$style])
                                ? intval($data[$category][$key][$style]) : 0,
                        ];
                    }
                }
                else {
                    $total[$key][$category] = [
                        'name' => $category,
                        'y' => isset($data[$category][$key])
                            ? intval($data[$category][$key]) : 0,
                    ];
                }
            }
        }

        // by place
        $place_data = [];
        foreach ([ 'works', 'works_exhibited', 'exhibitions' ] as $key) {
            if ('works_exhibited' == $key) {
                $querystr = 'SELECT COUNT(ItemExhibition.id) AS how_many, COALESCE(Geoname.name_alternate, Geoname.name) AS place'
                            . " FROM Exhibition"
                            . " INNER JOIN Location ON Location.id=Exhibition.id_location"
                            . " INNER JOIN Geoname ON Geoname.tgn=Location.place_tgn"
                            . ' INNER JOIN ItemExhibition ON ItemExhibition.id_exhibition=Exhibition.id'
                            . ' INNER JOIN Item ON Item.id=ItemExhibition.id_item AND Item.id <> -1' . $collectionCondition
                            . " WHERE"
                            . " " . \AppBundle\Utils\SearchListBuilder::exhibitionVisibleCondition('Exhibition')
                          . ' GROUP BY Geoname.tgn'
                          . ' ORDER BY Geoname.country_code, place'
                ;
            }
            else if ('exhibitions' == $key) {
                $querystr = 'SELECT COUNT(DISTINCT Exhibition.id) AS how_many, COALESCE(Geoname.name_alternate, Geoname.name) AS place'
                            . " FROM Exhibition"
                            . " INNER JOIN Location ON Location.id=Exhibition.id_location"
                            . " INNER JOIN Geoname ON Geoname.tgn=Location.place_tgn"
                            . ' INNER JOIN ItemExhibition ON ItemExhibition.id_exhibition=Exhibition.id'
                            . ' INNER JOIN Item ON Item.id=ItemExhibition.id_item AND Item.id <> -1' . $collectionCondition
                            . " WHERE"
                            . " " . \AppBundle\Utils\SearchListBuilder::exhibitionVisibleCondition('Exhibition')
                          . ' GROUP BY Geoname.tgn'
                          . ' ORDER BY Geoname.country_code, place'
                ;
            }
            else {
                $querystr = 'SELECT COUNT(DISTINCT Item.id) AS how_many, COALESCE(Geoname.name_alternate, Geoname.name) AS place'
                            . " FROM Exhibition"
                            . " INNER JOIN Location ON Location.id=Exhibition.id_location"
                            . " INNER JOIN Geoname ON Geoname.tgn=Location.place_tgn"
                            . ' INNER JOIN ItemExhibition ON ItemExhibition.id_exhibition=Exhibition.id'
                            . ' INNER JOIN Item ON Item.id=ItemExhibition.id_item AND Item.id <> -1' . $collectionCondition
                            . " WHERE"
                            . " " . \AppBundle\Utils\SearchListBuilder::exhibitionVisibleCondition('Exhibition')
                          . ' GROUP BY Geoname.tgn'
                          . ' ORDER BY Geoname.country_code, place'
                ;
            }

            $stmt = $dbconn->query($querystr);

            while ($row = $stmt->fetch()) {
                $fullname = $row['place'];
                $place_data[$fullname][$key] = $row['how_many'];
            }
        }

        $place_total = [];
        $place_categories = array_keys($place_data);
        for ($i = 0; $i < count($place_categories); $i++) {
            $category = $place_categories[$i];
            foreach (['works', 'works_exhibited', 'exhibitions'] as $key) {
                $place_total[$key][$category] = [
                    'name' => $category,
                    'y' => isset($place_data[$category][$key])
                        ? intval($place_data[$category][$key]) : 0,
                ];
            }
        }

        // for table
        $querystr = "SELECT Exhibition.id AS exhibition_id, Item.id AS item_id, COALESCE(Geoname.name_alternate, Geoname.name) AS place, Geoname.country_code AS cc, Person.lastname, Person.firstname, IFNULL(Term.name, 'unknown') AS style"
                    . " FROM Exhibition"
                    . " INNER JOIN Location ON Location.id=Exhibition.id_location"
                    . " INNER JOIN Geoname ON Geoname.tgn=Location.place_tgn"
                    . ' INNER JOIN ItemExhibition ON ItemExhibition.id_exhibition=Exhibition.id'
                    . ' INNER JOIN Item ON Item.id=ItemExhibition.id_item AND Item.id <> -1'
                    . ' LEFT OUTER JOIN Term ON Item.style=Term.id'
                    . ' INNER JOIN ItemPerson ON Item.id=ItemPerson.id_item'
                    . ' INNER JOIN Person ON ItemPerson.id_person=Person.id AND Person.status <> -1'
                    . " WHERE " . \AppBundle\Utils\SearchListBuilder::exhibitionVisibleCondition('Exhibition')
                  . ' ORDER BY Geoname.country_code, place, place_tgn, Person.id, exhibition_id'
        ;

        $stmt = $dbconn->query($querystr);

        $persons_by_place = [];
        while ($row = $stmt->fetch()) {
            $place_key = $row['place'] . ' (' . $row['cc'] . ')';

            if (!array_key_exists($place_key, $persons_by_place)) {
                // new place
                $persons_by_place[$place_key] = [];
            }

            $fullname = $row['lastname'] . ', ' . $row['firstname'];

            if (!array_key_exists($fullname, $persons_by_place[$place_key])) {
                // new person in this place
                $persons_by_place[$place_key][$fullname] = $row;
                $persons_by_place[$place_key][$fullname]['total_item']
                    = $persons_by_place[$place_key][$fullname]['total_exhibition']
                    = 0;
                $persons_by_place[$place_key][$fullname]['exhibition_ids']
                    = $persons_by_place[$place_key][$fullname]['item_ids']
                    = [];
            }

            if (!array_key_exists($row['style'], $persons_by_place[$place_key][$fullname])) {
                $persons_by_place[$place_key][$fullname][$row['style']] = 0;
            }

            if (!in_array($row['exhibition_id'], $persons_by_place[$place_key][$fullname]['exhibition_ids'])) {
                $persons_by_place[$place_key][$fullname]['exhibition_ids'][] = $row['exhibition_id'];
                $persons_by_place[$place_key][$fullname]['total_exhibition'] += 1;
            }

            if (!in_array($row['item_id'], $persons_by_place[$place_key][$fullname]['item_ids'])) {
                $persons_by_place[$place_key][$fullname]['item_ids'][] = $row['item_id'];
                $persons_by_place[$place_key][$fullname]['total_item'] += 1;
                $persons_by_place[$place_key][$fullname][$row['style']] += 1;
            }
        }

        // for table
        $querystr = "SELECT Exhibition.id AS exhibition_id, Item.id AS item_id, YEAR(Exhibition.startdate) AS year, Person.lastname, Person.firstname, IFNULL(Term.name, 'unknown') AS style"
                    . " FROM Exhibition"
                    . ' INNER JOIN ItemExhibition ON ItemExhibition.id_exhibition=Exhibition.id'
                    . ' INNER JOIN Item ON Item.id=ItemExhibition.id_item AND Item.id <> -1'
                    . ' LEFT OUTER JOIN Term ON Item.style=Term.id'
                    . ' INNER JOIN ItemPerson ON Item.id=ItemPerson.id_item'
                    . ' INNER JOIN Person ON ItemPerson.id_person=Person.id AND Person.status <> -1'
                    . " WHERE " . \AppBundle\Utils\SearchListBuilder::exhibitionVisibleCondition('Exhibition')
                  . ' ORDER BY year, Person.id, exhibition_id'
        ;

        $stmt = $dbconn->query($querystr);

        $persons_by_year = [];
        while ($row = $stmt->fetch()) {
            $year_key = $row['year'];

            if (!array_key_exists($year_key, $persons_by_year)) {
                // new year
                $persons_by_year[$year_key] = [];
            }

            $fullname = $row['lastname'] . ', ' . $row['firstname'];

            if (!array_key_exists($fullname, $persons_by_year[$year_key])) {
                // new person in this year
                $persons_by_year[$year_key][$fullname] = $row;
                $persons_by_year[$year_key][$fullname]['total_item']
                    = $persons_by_year[$year_key][$fullname]['total_exhibition']
                    = 0;
                $persons_by_year[$year_key][$fullname]['exhibition_ids']
                    = $persons_by_year[$year_key][$fullname]['item_ids']
                    = [];
            }

            if (!array_key_exists($row['style'], $persons_by_year[$year_key][$fullname])) {
                $persons_by_year[$year_key][$fullname][$row['style']] = 0;
            }

            if (!in_array($row['exhibition_id'], $persons_by_year[$year_key][$fullname]['exhibition_ids'])) {
                $persons_by_year[$year_key][$fullname]['exhibition_ids'][] = $row['exhibition_id'];
                $persons_by_year[$year_key][$fullname]['total_exhibition'] += 1;
            }

            if (!in_array($row['item_id'], $persons_by_year[$year_key][$fullname]['item_ids'])) {
                $persons_by_year[$year_key][$fullname]['item_ids'][] = $row['item_id'];
                $persons_by_year[$year_key][$fullname]['total_item'] += 1;
                $persons_by_year[$year_key][$fullname][$row['style']] += 1;
            }
        }

        return $this->render('Statistics/item-by-person.html.twig', [
            'subtitle' => json_encode($subtitle = 'TODO'),

            'person_categories' => json_encode($categories),
            'works' => $total['works'], 'styles' => $styles,
            'works_exhibited' => json_encode(array_values($total['works_exhibited'])),
            'exhibitions' => json_encode(array_values($total['exhibitions'])),

            'place_categories' => json_encode($place_categories),
            'place_works' => json_encode(array_values($place_total['works'])),
            'place_works_exhibited' => json_encode(array_values($place_total['works_exhibited'])),
            'place_exhibitions' => json_encode(array_values($place_total['exhibitions'])),

            'persons_by_place_persons' => $categories,
            'persons_by_place' => $persons_by_place,
            'persons_by_year' => $persons_by_year,

            'collections' => $collections,
            'collection' => $collection,
        ]);
    }
}
