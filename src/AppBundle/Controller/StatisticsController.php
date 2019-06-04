<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Intl\Intl;
use Symfony\Component\Routing\Annotation\Route;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

/**
 *
 */
class StatisticsController
extends Controller
{
    use StatisticsBuilderTrait;

    static $countryMap = [ 'UA' => 'RU' ]; // don't count Ukrania seperately

    protected function exhibitionGenderDistribution($ids = null)
    {
        $stats = StatisticsController::itemexhibitionGenderDistribution($this->getDoctrine()->getManager(), $ids);

        $data = [];

        foreach ($stats['stats'] as $type => $count) {
            $percentage = 100.0 * $count / $stats['total'];
            $dataEntry = [
                'name' => $type,
                'y' => (int)$count,
            ];
            if ($percentage < 5) {
                $dataEntry['dataLabels'] = [ 'enabled' => false ];
            }
            $data[] = $dataEntry;
        }

        return $this->render('Statistics/exhibition-gender-index.html.twig', [
            'container' => 'container-gender',
            'total' => $stats['total'],
            'data' => json_encode($data),
            'exhibitionId' => 0
        ]);
    }

    public static function itemExhibitionTypeDistribution($em, $exhibitionId)
    {
        $dbconn = $em->getConnection();


        $where = sprintf(' WHERE ItemExhibition.id_exhibition=%d',
                         intval($exhibitionId));

        $querystr = "SELECT TypeTerm.id, TypeTerm.name, TypeTerm.aat, COUNT(*) AS how_many"
                  . " FROM ItemExhibition"
                  . " LEFT OUTER JOIN Term TypeTerm ON ItemExhibition.type=TypeTerm.id"
                  . $where
                  . " GROUP BY TypeTerm.id, TypeTerm.name"
                  . " ORDER BY TypeTerm.name";

        $stmt = $dbconn->query($querystr);
        $total = 0;
        $stats = [];
        while ($row = $stmt->fetch()) {
            $label = preg_match('/unknown/', $row['name'])
                ? 'unknown'
                : $row['name'];
            $stats[$label] = $row['how_many'];
            $total += $row['how_many'];
        }

        return [
            'total' => $total,
            'types' => $stats,
        ];
    }

    public static function exhibitionAgePersonIds($em, $age, $exhibitionId = null)
    {
        $dbconn = $em->getConnection();

        $where = !is_null($exhibitionId) && intval($exhibitionId) > 0
            ? sprintf('WHERE Exhibition.id=%d', intval($exhibitionId))
            : '';

        $querystr = <<<EOT
SELECT id,
IF (EB.deathdate IS NOT NULL AND YEAR(EB.deathdate) < YEAR(EB.startdate), 'deceased', 'living') AS state
FROM
(SELECT DISTINCT Person.id AS id, Exhibition.startdate AS startdate, Exhibition.id AS id_exhibition, Person.id AS id_person, Person.birthdate AS birthdate, Person.deathdate AS deathdate
FROM Exhibition
INNER JOIN ItemExhibition ON ItemExhibition.id_exhibition=Exhibition.id
INNER JOIN Person ON ItemExhibition.id_person=Person.id AND Person.birthdate IS NOT NULL
$where
GROUP BY Exhibition.id, Person.id) AS EB
WHERE YEAR(EB.startdate) - YEAR(EB.birthdate) = :age
EOT;

        $stmt = $stmt = $em->getConnection()->prepare($querystr);
        $stmt->bindValue(':age', $age, \PDO::PARAM_INT);
        $stmt->execute();
        $ids = [];
        while ($row = $stmt->fetch()) {
            if (!array_key_exists($row['state'], $ids)) {
                $ids[$row['state']] = [];
            }

            $ids[$row['state']][] = $row['id'];
        }

        return $ids;
    }

    public static function exhibitionAgeDistribution($em, $exhibitionId)
    {
        $dbconn = $em->getConnection();

        $andConditions = [ sprintf('Exhibition.id=%d', intval($exhibitionId)) ];

        $where = ' WHERE ' . implode(' AND ', $andConditions);

        $querystr = <<<EOT
SELECT COUNT(*) AS how_many,
YEAR(EB.startdate) - YEAR(EB.birthdate) AS age,
IF (EB.deathdate IS NOT NULL AND YEAR(EB.deathdate) < YEAR(EB.startdate), 'deceased', 'living') AS state
FROM
(SELECT DISTINCT Exhibition.startdate AS startdate, Exhibition.id AS id_exhibition, Person.id AS id_person, Person.birthdate AS birthdate, Person.deathdate AS deathdate
FROM Exhibition
INNER JOIN ItemExhibition ON ItemExhibition.id_exhibition=Exhibition.id
INNER JOIN Person ON ItemExhibition.id_person=Person.id AND Person.status <> -1 AND Person.birthdate IS NOT NULL
$where
GROUP BY Exhibition.id, Person.id) AS EB
GROUP BY age, state
ORDER BY age, state, how_many
EOT;

        $min_age = $max_age = 0;

        $stmt = $dbconn->query($querystr);
        $ageCount = [];
        while ($row = $stmt->fetch()) {
            if (0 == $min_age) {
                $min_age = (int)$row['age'];
            }
            $max_age = $age = (int)$row['age'];
            if (!array_key_exists($age, $ageCount)) {
                $ageCount[$age] = [];
            }
            $ageCount[$age][$row['state']] = $row['how_many'];
        }

        return [
            'min_age' => $min_age,
            'max_age' => $max_age,
            'age_count' => $ageCount,
        ];
    }

    public static function itemexhibitionGenderDistribution($em, $currIds = [])
    {
        $andConditions = [ \AppBundle\Utils\SearchListBuilder::exhibitionVisibleCondition('Exhibition') ];
        if (!empty($currIds)) {
            $andConditions[] = sprintf('Exhibition.ids IN (%s)',
                                       join(', ', $currIds));
        }

        $where = join(' AND ', $andConditions);

        $querystr = <<< EOT
SELECT COUNT(*) AS how_many, Person.sex
FROM Exhibition
INNER JOIN ItemExhibition ON ItemExhibition.id_exhibition=Exhibition.id
INNER JOIN Person ON ItemExhibition.id_person=Person.id AND Person.status <> -1
WHERE $where
GROUP BY Person.sex
EOT;

        $dbconn = $em->getConnection();
        $stmt = $dbconn->query($querystr);
        $total = 0;

        $stats = [ 'male' => 0, 'female' => 0 ];
        while ($row = $stmt->fetch()) {
            if ($row['sex'] === 'M') {
               $stats['male'] = (int)$row['how_many'];
            }
            else if ($row['sex'] === 'F') {
               $stats['female'] = (int)$row['how_many'];
            }

            $total += $row['how_many'];
        }

        return [
            'stats' => [ 'male' => $male, 'female' => $female ],
            'total' => $total
        ];
    }

    public static function itemExhibitionNationalityDistribution($em, $exhibitionId = null)
    {
        $qb = $em->createQueryBuilder();

        $qb->select([
                'P.id',
                'P.nationality',
                'COUNT(DISTINCT IE.id) AS numEntries'
            ])
            ->from('AppBundle:ItemExhibition', 'IE')
            ->innerJoin('IE.person', 'P')
            ->where('IE.title IS NOT NULL OR IE.item IS NULL')
            ->groupBy('P.id')
            ->orderBy('P.nationality')
            ;

        if (!is_null($exhibitionId)) {
            $qb->innerJoin('AppBundle:Exhibition', 'E',
                       \Doctrine\ORM\Query\Expr\Join::WITH,
                       'IE.exhibition = E AND E.id = :exhibitionId')
                ->setParameter('exhibitionId', $exhibitionId);
        }

        $statsByNationality = [];
        $totalArtists = 0;
        $totalItemExhibition = 0;
        $result = $qb->getQuery()->getResult();
        foreach ($result as $row) {
            $nationality = empty($row['nationality'])
                ? 'XX' : $row['nationality'];
            if (array_key_exists($nationality, self::$countryMap)) {
                $nationality = self::$countryMap[$nationality];
            }

            if (!array_key_exists($nationality, $statsByNationality)) {
                $statsByNationality[$nationality] = [
                    'label' => 'XX' == $nationality
                        ? $nationality : Intl::getRegionBundle()->getCountryName($nationality),
                    'countArtists' => 0,
                    'countItemExhibition' => 0,
                ];
            }

            ++$totalArtists;
            ++$statsByNationality[$nationality]['countArtists'];
            $statsByNationality[$nationality]['countItemExhibition'] += $row['numEntries'];
            $totalItemExhibition += $row['numEntries'];
        }

        return [
            'totalArtists' => $totalArtists,
            'totalItemExhibition' => $totalItemExhibition,
            'nationalities' => $statsByNationality,
            'exhId' => $exhibitionId
        ];
    }

    /**
     * @Route("/person/birth-death", name="person-birth-death")
     */
    public function d3jsPlaceAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $dbconn = $em->getConnection();

        $querystr = "SELECT Geoname.tgn AS tgn, COALESCE(Geoname.name_alternate, Geoname.name) AS name, country_code"
                  . ' FROM Person INNER JOIN Geoname ON Person.deathplace_tgn=Geoname.tgn'
                  . ' WHERE Person.status <> -1'
                  . ' GROUP BY country_code, name'
                  . ' ORDER BY country_code, name'
                  ;

        $stmt = $dbconn->query($querystr);
        $deathplaces_by_country = [];
        while ($row = $stmt->fetch()) {
            $deathplaces_by_country[$row['country_code']][$row['tgn']] = $row['name'];
        }

        $missingplaces_by_country = [];

        $dependencies = [];
        foreach ($deathplaces_by_country as $country_code => $places) {
            foreach ($places as $tgn => $place) {
                // find all birth-places as dependencies
                $querystr = "SELECT pb.tgn AS tgn, COALESCE(pb.name_alternate, pb.name) AS name, country_code, COUNT(*) AS how_many"
                          . ' FROM Person'
                          . ' INNER JOIN Geoname pb ON Person.birthplace_tgn=pb.tgn'
                          . " WHERE Person.deathplace_tgn='" . $tgn. "' AND Person.status <> -1"
                          . ' GROUP BY country_code, name';
                $stmt = $dbconn->query($querystr);
                $dependencies_by_place = [];
                while ($row = $stmt->fetch()) {
                    // add to $missingplaces_by_country if not already in $death_by_country
                    if (!isset($deathplaces_by_country[$row['country_code']])
                        || !isset($deathplaces_by_country[$row['country_code']][$row['tgn']]))
                    {
                        $missingplaces_by_country[$row['country_code']][$row['tgn']] = $row['name'];
                    }
                    $place_key = 'place.' . $row['country_code'] . '.' . $row['tgn'];
                    $dependencies_by_place[] = $place_key;
                }

                $place_key = 'place.' . $country_code . '.' . $tgn;
                $entry = [
                    'name' => $place_key,
                    'label' => $place,
                    'size' => 1,
                    'imports' => [],
                ];
                if (!empty($dependencies_by_place)) {
                    $entry['imports'] = $dependencies_by_place;
                }

                $dependencies[] = $entry;
            }
        }

        foreach ($missingplaces_by_country as $country_code => $places) {
            arsort($places);
            foreach ($places as $tgn => $place) {
                $place_key = $country_code . '.' . $tgn;
                $entry = [
                    'name' => 'place.' . $place_key,
                    'label' => $place,
                    'size' => 1,
                    'imports' => [],
                ];
                $dependencies[] = $entry;
            }
        }

        // display the static content
        return $this->render('Statistics/birth-death.html.twig', [
            'dependencies' => $dependencies,
        ]);
    }

    /**
     * TODO: share with ItemController
     */
    protected function buildCollections()
    {
        $em = $this->getDoctrine()
                ->getManager();

        $result = $em->createQuery("SELECT C.id, C.name FROM AppBundle:Collection C"
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
            $collectionCondition = sprintf(' AND Item.collection=%d',
                                           intval($collection));
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
            foreach ([ 'works', 'works_exhibited', 'exhibitions' ]
                     as $key)
            {
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
            foreach (['works', 'works_exhibited', 'exhibitions']
                     as $key) {
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

    function getArrayQueryString($modelString, $modelSubcode, $queryArray, $fallbackString)
    {
        $modelQueryString = '';
        $counterQueryArray = 0;

        if ($queryArray === '' or $queryArray === 'any') {
            $modelQueryString = $fallbackString;
        }
        else {
            if (is_array($queryArray)) {
                foreach ($queryArray as $queryElement) {
                    if ($counterQueryArray > 0) {
                        $modelQueryString .= ", ";
                    }
                    $modelQueryString .= "'" . $queryElement . "'";
                    $counterQueryArray++;
                }
            }
            else {
                $modelQueryString = "'" . $queryArray . "'";
            }

            // form the right query with the params
            $modelQueryString = $modelString . "." . $modelSubcode . " IN(" . $modelQueryString . ")";
        }

        return $modelQueryString;
    }

    // creates additonal query for filter actions
    function getPersonQueryString($personModelString, $fallbackString, $nationalityCode , $nationalityArray)
    {
        $personQueryString = '';
        $counterNationalityArray = 0;

        if ($nationalityArray === '' or $nationalityArray === 'any') {
            $personQueryString = $fallbackString;
        } else {
            // if nationality is set check if larger than one or only on value
            if (is_array($nationalityArray)) {
                foreach ($nationalityArray as $nationality) {
                    if ($counterNationalityArray > 0) {
                        $personQueryString .= ", ";
                    }
                    $personQueryString .= "'" . $nationality . "'";
                    $counterNationalityArray++;
                }
            }
            else {
                $personQueryString = "'" . $nationalityArray . "'";
            }

            // form the right query with the params
            $personQueryString = $personModelString . "." . $nationalityCode . " IN(" . $personQueryString . ")";
        }

        return $personQueryString;
    }
}
