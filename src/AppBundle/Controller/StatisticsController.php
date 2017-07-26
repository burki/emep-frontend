<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

/**
 *
 */
class StatisticsController extends Controller
{
    /**
     * @Route("/exhibition/by-month", name="exhibition-by-month")
     */
    public function reportsPerMonth()
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
    public function personByYearAction()
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
        foreach (['birth', 'death'] as $key) {
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
                     as $key) {
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

    /**
     * @Route("/work/by-person", name="item-by-person")
     */
    public function itemByPerson()
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

        $data = [];
        foreach (['works', 'works_exhibited', 'exhibitions' ] as $key) {
            if ('works_exhibited' == $key) {
                $querystr = 'SELECT COUNT(ItemExhibition.id) AS how_many, Person.lastname, Person.firstname'
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
                $querystr = 'SELECT COUNT(Item.id) AS how_many, Person.lastname, Person.firstname'
                          . ' FROM Person'
                          . ' INNER JOIN ItemPerson ON Person.id=ItemPerson.id_person'
                          . ' INNER JOIN Item ON ItemPerson.id_item = Item.id'
                          . ' WHERE Person.status <> -1 AND Item.status <> -1'
                          . ' GROUP BY Person.id'
                          . ' ORDER BY Person.lastname, Person.firstname, Person.id'
                          ;
            }
            $stmt = $dbconn->query($querystr);

            while ($row = $stmt->fetch()) {
                $fullname = $row['lastname'] . ', ' . $row['firstname'];
                $data[$fullname][$key] = $row['how_many'];
            }
        }

        $total = [];
        $categories = array_keys($data);
        for ($i = 0; $i < count($categories); $i++) {
            $category = $categories[$i];
            foreach (['works', 'works_exhibited','exhibitions']
                     as $key) {
                $total[$key][$category] = [
                    'name' => $category,
                    'y' => isset($data[$category][$key])
                        ? intval($data[$category][$key]) : 0,
                ];
            }
        }

        return $this->render('Statistics/item-by-person.html.twig', [
            'subtitle' => json_encode($subtitle = 'TODO'),
            'categories' => json_encode($categories),
            'works' => json_encode(array_values($total['works'])),
            'works_exhibited' => json_encode(array_values($total['works_exhibited'])),
            'exhibitions' => json_encode(array_values($total['exhibitions'])),
        ]);
    }

    /**
     * @Route("/work/by-person", name="item-by-person")
     */
    public function itemByPlace()
    {
    }
}
