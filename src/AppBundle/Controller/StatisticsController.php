<?php

namespace AppBundle\Controller;

use \Symfony\Component\HttpFoundation\Request;
use \Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

/**
 *
 */
class StatisticsController
extends Controller
{
    static $countryMap = [ 'UA' => 'RU' ]; // don't count Ukrania seperately

    /**
     * @Route("/exhibition/by-month", name="exhibition-by-month")
     */
    public function exhibitionByMonthAction()
    {
        $em = $this->getDoctrine()->getEntityManager();

        $dbconn = $em->getConnection();
        $querystr = "SELECT YEAR(startdate) AS start_year, MONTH(startdate) AS start_month"
                  . ", COUNT(*) AS how_many FROM Exhibition"
                  . " WHERE status <> -1 AND MONTH(startdate) <> 0"
                  . " GROUP BY YEAR(startdate), MONTH(startdate)"
                  . " ORDER BY start_year, start_month"
                  ;
        $stmt = $dbconn->query($querystr);
        $frequency_count = [];
        $min_year = -1;
        while ($row = $stmt->fetch()) {
            if ($min_year < 0) {
                $min_year = (int)$row['start_year'];
            }
            $key = $row['start_year'] . sprintf('%02d', $row['start_month']);
            $how_many = (int)$row['how_many'];
            $frequency_count[$key] = $how_many;
        }

        $data = $scatter_data = $scatter_categories = [];

        $keys = array_keys($frequency_count);
        $i = $min = $keys[0];
        $max = $keys[count($keys) - 1];
        $sum = 0;
        while ($i <= $max) {
            $key = $i;
            $categories[] = sprintf('%04d-%02d', $year = intval($i / 100), $month = $i % 100);
            $count = array_key_exists($key, $frequency_count) ? $frequency_count[$key] : 0;
            $sum += $count;
            $data[] = $count;
            if ($count > 0) {
                if (!in_array($year, $scatter_categories)) {
                    $scatter_categories[] = $year;
                }
                $scatter_data[] = [
                    'y' => $year - $min_year,
                    'x' => $month - 1,
                    'count' => $count, 'year' => $year,
                    'marker' => [ 'radius' => intval(2 * sqrt($count) + 0.5) ]
                ];
            }
            // $sum += $count;
            if ($i % 100 < 12) {
                ++$i;
            }
            else {
                $i = $i + (100 - $i % 100) + 1;
            }
        }
        $data_avg = round(1.0 * $sum / count($data), 1);

        // display the static content
        return $this->render('Statistics/exhibition-by-month.html.twig', [
            'data_avg' => $data_avg,
            'categories' => json_encode($categories),
            'data' => json_encode($data),
            'scatter_data' => json_encode($scatter_data),
            'scatter_categories' => json_encode($scatter_categories),
        ]);
    }

    /**
     * @Route("/person/by-year", name="person-by-year")
     */
    public function personByYearActionAction()
    {
        // display the artists by birth-year, the catalog-entries by exhibition-year
        $em = $this->getDoctrine()->getEntityManager();

        $dbconn = $em->getConnection();
        $querystr = "SELECT 'active' AS type, COUNT(*) AS how_many FROM Person"
                  . " WHERE status >= 0 AND birthdate IS NOT NULL"
                  // . "  AND sex IS NOT NULL"
                  ;
        $querystr .= " UNION SELECT 'total' AS type, COUNT(*) AS how_many"
                   . " FROM Person WHERE status >= 0";
        $stmt = $dbconn->query($querystr);
        $subtitle_parts = [];
        while ($row = $stmt->fetch()) {
          if ('active' == $row['type']) {
            $total_active = $row['how_many'];
          }
          $subtitle_parts[] = $row['how_many'];
        }
        $subtitle = implode(' out of ', $subtitle_parts) . ' persons';

        $data = [];
        $max_year = $min_year = 0;
        foreach ([ 'birth', 'death' ] as $key) {
            $date_field = $key . 'date';
            $querystr = 'SELECT YEAR(' . $date_field . ') AS year'
                      // . ', sex'
                      . ', COUNT(*) AS how_many'
                      . ' FROM Person WHERE status >= 0 AND ' . $date_field . ' IS NOT NULL'
                      // . ' AND sex IS NOT NULL'
                      . ' GROUP BY YEAR(' . $date_field. ')'
                      // . ', sex'
                      . ' ORDER BY YEAR(' . $date_field . ')'
                      //. ', sex'
                      ;
            $stmt = $dbconn->query($querystr);

            while ($row = $stmt->fetch()) {
                if (0 == $min_year || $row['year'] < $min_year) {
                    $min_year = $row['year'];
                }
                if ($row['year'] > $max_year) {
                    $max_year = $row['year'];
                }
                if (!isset($data[$row['year']])) {
                    $data[$row['year']] = [];
                }
                $data[$row['year']][$key] = $row['how_many'];
            }
        }

        if ($min_year < 1820) {
            $min_year = 1820;
        }
        if ($max_year > 2000) {
            $max_year = 2000;
        }

        /*
        $total_works = 0;

        $querystr = 'SELECT PublicationPerson.publication_ord AS year, Publication.complete_works = 0 AS base, COUNT(DISTINCT Publication.id) AS how_many FROM Person LEFT OUTER JOIN PublicationPerson ON PublicationPerson.person_id=Person.id LEFT OUTER JOIN Publication ON Publication.id=PublicationPerson.publication_id AND Publication.status >= 0 WHERE Person.status >= 0 AND PublicationPerson.publication_ord IS NOT NULL'
                  // . ' AND sex IS NOT NULL'
                  . ' GROUP BY PublicationPerson.publication_ord, Publication.complete_works = 0'
                  . ' ORDER BY PublicationPerson.publication_ord, Publication.complete_works = 0';
        $stmt = $dbconn->query($querystr);
        while ($row = $stmt->fetch()) {
            $total_works += $row['how_many'];
            $key = $row['base'] ? 'works_issued_base' : 'works_issued_extended';
            $data[$row['year']][$key] = $row['how_many'];
        }
        */

        $categories = [];
        for ($year = $min_year; $year <= $max_year; $year++) {
            $categories[] = 0 == $year % 5 ? $year : '';
            foreach (['birth', 'death',
                           // 'works',
                      'works_issued_base', 'works_issued_extended']
                     as $key)
            {
                $total[$key][$year] = [
                    'name' => $year,
                    'y' => isset($data[$year][$key])
                        ? intval($data[$year][$key]) : 0,
                ];
            }
        }

        return $this->render('Statistics/person-by-year.html.twig', [
            'subtitle' => json_encode($subtitle),
            'categories' => json_encode($categories),
            'person_birth' => json_encode(array_values($total['birth'])),
            'person_death' => json_encode(array_values($total['death'])),
            /*
            'works_base' => json_encode(array_values($total['works_issued_base'])),
            'works_extended' => json_encode(array_values($total['works_issued_extended'])),
            */
        ]);
    }

    public static function itemExhibitionTypeDistribution($em, $exhibitionId = null)
    {
        $dbconn = $em->getConnection();

        $where = !is_null($exhibitionId)
            ? sprintf('WHERE ItemExhibition.id_exhibition=%d', intval($exhibitionId))
            : '';

        $querystr = <<<EOT
SELECT TypeTerm.id, TypeTerm.name, TypeTerm.aat, COUNT(*) AS how_many
FROM ItemExhibition
LEFT OUTER JOIN Term TypeTerm ON ItemExhibition.type=TypeTerm.id
{$where}
GROUP BY TypeTerm.id, TypeTerm.name
ORDER BY TypeTerm.name
EOT;

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

    public static function exhibitionAgeDistribution($em, $exhibitionId = null)
    {
        $dbconn = $em->getConnection();

        $where = !is_null($exhibitionId)
            ? sprintf('WHERE Exhibition.id=%d', intval($exhibitionId))
            : '';

        $querystr = <<<EOT
SELECT COUNT(*) AS how_many,
YEAR(EB.startdate) - YEAR(EB.birthdate) AS age,
IF (EB.deathdate IS NOT NULL AND YEAR(EB.deathdate) < YEAR(EB.startdate), 'deceased', 'living') AS state
FROM
(SELECT DISTINCT Exhibition.startdate AS startdate, Exhibition.id AS id_exhibition, Person.id AS id_person, Person.birthdate AS birthdate, Person.deathdate AS deathdate
FROM Exhibition
INNER JOIN ItemExhibition ON ItemExhibition.id_exhibition=Exhibition.id
INNER JOIN Person ON ItemExhibition.id_person=Person.id AND Person.birthdate IS NOT NULL
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
            ->where('IE.title IS NOT NULL')
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
        ];
    }

    /**
     * @Route("/person/exhibition-age", name="person-exhibition-age")
     */
    public function personExhibitionAgeAction()
    {
        // display the artists by birth-year, the catalog-entries by exhibition-year
        $stats = self::exhibitionAgeDistribution($em = $this->getDoctrine()->getEntityManager());
        $ageCount = & $stats['age_count'];

        $categories = $total = [];
        for ($age = $stats['min_age']; $age <= $stats['max_age'] && $age < 120; $age++) {
            $categories[] = $age; // 0 == $age % 5 ? $year : '';

            foreach ([ 'living', 'deceased' ] as $cat) {
                $total['age_' . $cat][$age] = [
                    'name' => $age,
                    'y' => isset($ageCount[$age]) && isset($ageCount[$age][$cat])
                        ? intval($ageCount[$age][$cat]) : 0,
                ];
            }
        }

        $template = $this->get('twig')->loadTemplate('Statistics/person-exhibition-age.html.twig');
        $chart = $template->renderBlock('chart', [
            'container' => 'container-age',
            'categories' => json_encode($categories),
            'age_at_exhibition_living' => json_encode(array_values($total['age_living'])),
            'age_at_exhibition_deceased' => json_encode(array_values($total['age_deceased'])),
        ]);

        // display the static content
        return $this->render('Statistics/person-exhibition-age.html.twig', [
            'container' => 'container-age',
            'chart' => $chart
        ]);
    }

    /**
     * @Route("/person/birth-death", name="person-birth-death")
     */
    public function d3jsPlaceAction(Request $request)
    {
        $em = $this->getDoctrine()->getEntityManager();

        $dbconn = $em->getConnection();

        $querystr = "SELECT Geoname.tgn AS tgn, COALESCE(Geoname.name_alternate, Geoname.name) AS name, country_code"
                  . ' FROM Person INNER JOIN Geoname ON Person.deathplace_tgn=Geoname.tgn'
                  . ' WHERE Person.status <> -1'
        //          . " AND country_code IN ('FR')"
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
     * @Route("/exhibition/distribution", name="exhibition-distribution")
     */
    public function itemPersonPerExhibitionAction(Request $request)
    {
        $em = $this->getDoctrine()->getEntityManager();

        $dbconn = $em->getConnection();

        $data = [ 'person' => [], 'item' => [] ];
        $data_median = [];

        foreach ([ 'person', 'item' ] as $type) {
            $what = 'item' == $type ? '*' : 'DISTINCT id_person';

            $querystr = "SELECT id_exhibition, COUNT({$what}) AS how_many"
                      . " FROM ItemExhibition INNER JOIN Exhibition ON Exhibition.id=ItemExhibition.id_exhibition AND Exhibition.status <> -1 AND 0 = (Exhibition.flags & 0x100)"
                      . " WHERE ItemExhibition.title IS NOT NULL"
                      . " GROUP BY id_exhibition"
                      . " HAVING how_many >= 1"
                      ;
            $stmt = $dbconn->query($querystr);
            $frequency_count = [];
            while ($row = $stmt->fetch()) {
                $how_many = (int)$row['how_many'];
                if (!array_key_exists($how_many, $frequency_count)) {
                    $frequency_count[$how_many] = 0;
                }
                ++$frequency_count[$how_many];
            }
            ksort($frequency_count);
            $keys = array_keys($frequency_count);
            $min = $keys[0]; $max = $keys[count($keys) - 1];

            $sum = 0;
            for ($i = $min; $i <= $max; $i++) {
                $count = array_key_exists($i, $frequency_count) ? $frequency_count[$i] : 0;
                $data[$type][] = $count;
                $sum += $count;
            }

            // find the index for which we reach half the sum
            $sum_half = $sum / 2.0;
            $sum = 0;
            for ($i = $min; $i <= $max; $i++) {
                $count = array_key_exists($i, $frequency_count) ? $frequency_count[$i] : 0;
                if ($sum + $count >= $sum_half) {
                    $delta_left = $sum_half - $sum;
                    $delta_right = $sum + $count - $sum_half;
                    $data_median[$type] = $delta_left < $delta_right ? $i - 1 : $i;
                    break;
                }

                $sum += $count;
            }
        }

        // display the static content
        return $this->render('Statistics/exhibition-distribution.html.twig', [
            'data' => json_encode($data['item']),
            'data_median' => $data_median['item'],
        ]);
    }

    /**
     * @Route("/person/distribution", name="person-distribution")
     */
    public function exhibitionPerPerson(Request $request)
    {
        $em = $this->getDoctrine()->getEntityManager();

        $dbconn = $em->getConnection();

        $data = [ 'exhibition' => [], 'item' => [] ];
        $data_median = [];

        foreach ([ 'exhibition', 'item' ] as $type) {
            $what = 'item' == $type ? '*' : 'DISTINCT id_exhibition';

            $querystr = "SELECT id_person, COUNT({$what}) AS how_many"
                      . " FROM ItemExhibition INNER JOIN Exhibition ON Exhibition.id=ItemExhibition.id_exhibition AND Exhibition.status <> -1 AND 0 = (Exhibition.flags & 0x100)"
                      . " WHERE ItemExhibition.title IS NOT NULL"
                      . " GROUP BY id_person"
                      . " HAVING how_many >= 1"
                      ;
            $stmt = $dbconn->query($querystr);
            $frequency_count = [];
            while ($row = $stmt->fetch()) {
                $how_many = (int)$row['how_many'];
                if (!array_key_exists($how_many, $frequency_count)) {
                    $frequency_count[$how_many] = 0;
                }
                ++$frequency_count[$how_many];
            }
            ksort($frequency_count);
            $keys = array_keys($frequency_count);
            $min = $keys[0]; $max = $keys[count($keys) - 1];

            $sum = 0;
            for ($i = $min; $i <= $max; $i++) {
                $count = array_key_exists($i, $frequency_count) ? $frequency_count[$i] : 0;
                $data[$type][] = $count;
                $sum += $count;
            }

            // find the index for which we reach half the sum
            $sum_half = $sum / 2.0;
            $sum = 0;
            for ($i = $min; $i <= $max; $i++) {
                $count = array_key_exists($i, $frequency_count) ? $frequency_count[$i] : 0;
                if ($sum + $count >= $sum_half) {
                    $delta_left = $sum_half - $sum;
                    $delta_right = $sum + $count - $sum_half;
                    $data_median[$type] = $delta_left < $delta_right ? $i - 1 : $i;
                    break;
                }

                $sum += $count;
            }
        }

        // display the static content
        return $this->render('Statistics/person-distribution.html.twig', [
            'data' => json_encode($data['exhibition']),
            'data_median' => $data_median['exhibition'],
        ]);
    }

    /**
     * @Route("/person/popularity", name="person-popularity")
     */
    public function personsWikipediaAction(Request $request)
    {
        $em = $this->getDoctrine()->getEntityManager();

        $lang = in_array($request->get('lang'), ['en', 'de', 'fr'])
            ? $request->get('lang') : 'en';

        $qb = $this->getDoctrine()
                ->getManager()
                ->createQueryBuilder();

        $qb->select([
                'P',
                'COUNT(DISTINCT E.id) AS numExhibitionSort',
            ])
            ->from('AppBundle:Person', 'P')
            ->leftJoin('P.exhibitions', 'E')
            ->leftJoin('P.catalogueEntries', 'IE')
            ->where('P.status <> -1 AND P.wikidata IS NOT NULL')
            ->groupBy('P.id') // for Count
            ;


        // Create the query
        $results = $qb->getQuery()->getResult();
        $data = [];
        foreach ($results as $result) {
            $person = $result[0];
            $how_many = $result['numExhibitionSort'];
            $additional = $person->getAdditional();
            if (array_key_exists('wikistats', $additional)
                && array_key_exists($lang, $additional['wikistats']))
            {
                $single_data = array(
                    'name' => $person->getFullname(), // person
                    'num' => (int)$how_many,
                    'id' => $person->getId(),
                    'x' => (int)$how_many + 0.3 * rand(-1, 1), // num-reports
                    'y' => (int)$additional['wikistats'][$lang], // num hits
                );
                $data[] = $single_data;
            }
        }

        usort($data, function($a, $b) {
            return $a['y'] == $b['y'] ? 0 : ($a['y'] > $b['y'] ? -1 : 1);
        });

        return $this->render('Statistics/person-wikipedia.html.twig', [
            'lang' => $lang,
            'data' => json_encode($data),
            'persons' => $data,
        ]);
    }

    /**
     * TODO: rename since we added cities as well
     *
     * @Route("/work/by-person", name="item-by-person")
     */
    public function itemByPersonAction()
    {
        // display the number of works / exhibited works by artist
        $em = $this->getDoctrine()->getEntityManager();

        $dbconn = $em->getConnection();
        $querystr = "SELECT 'items' AS type, COUNT(*) AS how_many FROM Item"
                  . " WHERE status <> -1"
                  ;
        $querystr .= " UNION SELECT 'total' AS type, COUNT(ItemExhibition.id) AS how_many"
                   . " FROM Item INNER JOIN ItemExhibition ON Item.id=ItemExhibition.id_item WHERE Item.status <> -1";
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
        foreach (['works', 'works_exhibited', 'exhibitions' ] as $key) {
            if ('works_exhibited' == $key) {
                $querystr = "SELECT COUNT(ItemExhibition.id) AS how_many, Person.lastname, Person.firstname"
                          . ' FROM Person'
                          . ' INNER JOIN ItemPerson ON Person.id=ItemPerson.id_person'
                          . ' INNER JOIN Item ON ItemPerson.id_item = Item.id'
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
                          . ' INNER JOIN Item ON ItemPerson.id_item = Item.id'
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
                          . ' INNER JOIN Item ON ItemPerson.id_item = Item.id'
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
            foreach (['works', 'works_exhibited','exhibitions']
                     as $key) {
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
        foreach (['works', 'works_exhibited', 'exhibitions' ] as $key) {
            if ('works_exhibited' == $key) {
                $querystr = 'SELECT COUNT(ItemExhibition.id) AS how_many, COALESCE(Geoname.name_alternate, Geoname.name) AS place'
                            . " FROM Exhibition"
                            . " INNER JOIN Location ON Location.id=Exhibition.id_location"
                            . " INNER JOIN Geoname ON Geoname.tgn=Location.place_tgn"
                            . ' INNER JOIN ItemExhibition ON ItemExhibition.id_exhibition=Exhibition.id'
                            . ' INNER JOIN Item ON Item.id=ItemExhibition.id_item AND Item.id <> -1'
                            . " WHERE"
                            . " Exhibition.status <> -1"
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
                            . ' INNER JOIN Item ON Item.id=ItemExhibition.id_item AND Item.id <> -1'
                            . " WHERE"
                            . " Exhibition.status <> -1"
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
                            . ' INNER JOIN Item ON Item.id=ItemExhibition.id_item AND Item.id <> -1'
                            . " WHERE"
                            . " Exhibition.status <> -1"
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
            foreach (['works', 'works_exhibited','exhibitions']
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
                    . " WHERE Exhibition.status <> -1"
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
                $persons_by_place[$place_key][$fullname]['Figurative']
                  = $persons_by_place[$place_key][$fullname]['Abstracted']
                  = $persons_by_place[$place_key][$fullname]['Abstract']
                  = 0;
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
        ]);
    }

    /**
     * @Route("/exhibition/nationality", name="exhibition-nationality")
     */
    function exhibitionNationalityAction(Request $request)
    {
        $qb = $this->getDoctrine()
                ->getManager()
                ->createQueryBuilder();

        $qb->select([
                'P.id',
                'P.nationality',
                'Pl.countryCode',
                'COUNT(DISTINCT IE.id) AS numEntries',
                'C.name'
            ])
            ->from('AppBundle:Location', 'L')
            ->leftJoin('L.place', 'Pl')
            ->leftJoin('Pl.country', 'C')
            ->leftJoin('AppBundle:Exhibition', 'E',
                       \Doctrine\ORM\Query\Expr\Join::WITH,
                       'E.location = L AND E.status <> -1')
            ->leftJoin('AppBundle:ItemExhibition', 'IE',
                       \Doctrine\ORM\Query\Expr\Join::WITH,
                       'IE.exhibition = E AND IE.title IS NOT NULL')
            ->innerJoin('IE.person', 'P')
            ->where('L.status <> -1')
            // ->andWhere("Pl.countryCode IN('CH', 'NL')") // test
            ->groupBy('P.id')
            ->orderBy('C.name', 'DESC')
            ;

        $lastCountry = '';
        $result = $qb->getQuery()->getResult();

        $statsByCountry = [];
        $statsByNationality = [];
        foreach ($result as $row) {
            $cc = $row['countryCode'];
            if (array_key_exists($cc, self::$countryMap)) {
                $cc = self::$countryMap[$cc];
            }

            if (!array_key_exists($cc, $statsByCountry)) {
                $statsByCountry[$cc] = [
                    'name' => $row['name'],
                    'countByNationality' => [],
                    'totalArtists' => 0,
                    'totalItemExhibition' => 0,
                ];
            }

            $nationality = empty($row['nationality'])
                ? 'XX' : $row['nationality'];
            if (array_key_exists($nationality, self::$countryMap)) {
                $nationality = self::$countryMap[$nationality];
            }

            if (!array_key_exists($nationality, $statsByNationality)) {
                $statsByNationality[$nationality] = [
                    // 'name' => $row['name'],
                    'countArtists' => 0,
                    'countItemExhibition' => 0,
                ];
            }
            if (!array_key_exists($nationality, $statsByCountry[$cc]['countByNationality'])) {
                $statsByCountry[$cc]['countByNationality'][$nationality] = [
                    'countArtists' => 0,
                    'countItemExhibition' => 0,
                ];
            }
            ++$statsByCountry[$cc]['countByNationality'][$nationality]['countArtists'];
            ++$statsByCountry[$cc]['totalArtists'];
            ++$statsByNationality[$nationality]['countArtists'];

            $statsByCountry[$cc]['countByNationality'][$nationality]['countItemExhibition'] += $row['numEntries'];
            $statsByCountry[$cc]['totalItemExhibition'] += $row['numEntries'];
            $statsByNationality[$nationality]['countItemExhibition'] += $row['numEntries'];
        }

        $key = 'countItemExhibition'; // alternative: 'countArtists'

        $nationalities = [];
        foreach ($statsByNationality as $nationality => $stats) {
            $nationalities[$nationality] = $stats[$key];
        }

        $countries = array_keys($statsByCountry);

        uksort($nationalities, function ($idxA, $idxB) use ($countries, $nationalities) {
            if ('XX' == $idxA) {
                $a = 0;
            }
            else {
                $countryIdx = array_search($idxA, $countries);
                $a = false !== $countryIdx ? $countryIdx + 100000 : $nationalities[$idxA];
            }

            if ('XX' == $idxB) {
                $b = 0;
            }
            else {
                $countryIdx = array_search($idxB, $countries);
                $b = false !== $countryIdx ? $countryIdx  + 100000 : $nationalities[$idxB];
            }

            if ($a == $b) {
                return 0;
            }

            return ($a < $b) ? 1 : -1;
        });

        $maxNationality = 16;
        $xCategories = array_keys($nationalities);
        if (count($xCategories) > $maxNationality) {
            $xCategories = array_merge(array_slice($xCategories, 0, $maxNationality - 1 ),
                                       [ 'unknown', 'other' ]);
        }
        // exit;

        $valuesFinal = [];
        $y = 0;
        foreach ($statsByCountry as $cc => $stats) {
            $values = [];
            foreach ($stats['countByNationality'] as $nationality => $counts) {
                $x = array_search('XX' === $nationality ? 'unknown' : $nationality, $xCategories);
                if (false === $x) {
                    $x = array_search('other', $xCategories);
                }
                if (false !== $x) {
                    $percentage = 100.0 * $counts[$key] / $stats['totalItemExhibition'];
                    $valuesFinal[] = [
                        'x' => $x,
                        'y' => $y,
                        'value' => $percentage,
                        'total' => $counts[$key],
                    ];
                }
                // $values[$nationality] = $counts[$key];
            }
            /*
            arsort($values);

            $valuesFinal[$cc] = array_map(function ($idx) use ($values) {
                                        return [
                                            'name' => $idx,
                                            'y' => $values[$idx],
                                        ];
                                     },
                                     array_keys($values));
            */
            $y++;
        }
        // var_dump($valuesFinal);

        return $this->render('Statistics/exhibition-nationality.html.twig', [
            'countries' => $countries,
            'nationalities' => $xCategories,
            'data' => $valuesFinal,
        ]);
    }
}
