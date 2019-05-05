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

    public function getStringQueryForExhibitions($query, $shortOrLongQuery)
    {
        if ($query !== 'any') {
            if ($shortOrLongQuery === 'long') {
                return " AND (Exhibition.title LIKE '%" . $query . "%' OR Location.name LIKE '%" . $query . "%' OR Location.place LIKE '%" . $query . "%')";
            }

            return " AND (E.title LIKE '%" . $query . "%' OR L.name LIKE '%" . $query . "%' OR L.placeLabel LIKE '%" . $query . "%')";
        }

        if ($shortOrLongQuery === 'long') {
            return " AND " . \AppBundle\Utils\SearchListBuilder::exhibitionVisibleCondition('Exhibition');
        }

        return " AND " . \AppBundle\Utils\SearchListBuilder::exhibitionVisibleCondition('E');
    }

    public function getStringQueryForLocations($query, $shortOrLongQuery)
    {
        if ($query !== 'any') {
            if ($shortOrLongQuery === 'long') {
                return " AND (Location.name LIKE '%" . $query . "%' OR Location.place LIKE '%" . $query . "%')";
            }

            return " AND (L.name LIKE '%" . $query . "%' OR L.place LIKE '%" . $query . "%')";
        }

        if ($shortOrLongQuery === 'long') {
            return " AND Location.status <> -1 ";
        }

        return " AND L.status <> -1";
    }

    public function getStringQueryForPersonsInExhibitions($queryArray, $querySubject, $shortOrLongQuery)
    {
        if (!empty($queryArray)) {
            if ($shortOrLongQuery === 'long') {
                $query = $query = "Person." . $querySubject. " = '" . join("' OR Person." . $querySubject. " = '", $queryArray) . "'";

                return " AND ( " . $query . " )";
            }

            $query = $query = "P." . $querySubject. " = '" . join("' OR P." . $querySubject. " = '", $queryArray) . "'";

            return " AND ( " . $query . " )";
        }

        if ($shortOrLongQuery === 'long') {
            return " ";
        }

        return " ";
    }

    public function getStringQueryForExhibitionsInArtist($queryArray, $querySubject, $shortOrLongQuery)
    {
        if (!empty($queryArray)) {
            if ($shortOrLongQuery === 'long') {
                $query = $query = "Exhibition." . $querySubject. " = '" . join("' OR Exhibition." . $querySubject. " = '", $queryArray) . "'";
                return " AND ( " . $query . " )";
            }

            $query = $query = "E." . $querySubject. " = '" . join("' OR E." . $querySubject. " = '", $queryArray) . "'";
            return " AND ( " . $query . " )";
        }

        if ($shortOrLongQuery === 'long') {
            return " ";
        }

        return " ";
    }


    public function getStringQueryForLocationInArtist($queryArray, $querySubject, $shortOrLongQuery)
    {
        if (!empty($queryArray)) {
            if ($shortOrLongQuery === 'long') {
                $query = $query = "Location." . $querySubject. " = '" . join("' OR Location." . $querySubject. " = '", $queryArray) . "'";
                return " AND ( " . $query . " )";
            }

            $query = $query = "L." . $querySubject. " = '" . join("' OR L." . $querySubject. " = '", $queryArray) . "'";
            return " AND ( " . $query . " )";
        }

        if ($shortOrLongQuery === 'long') {
            return " ";
        }

        return " ";
    }


    public function getStringQueryExhibitionsStartdate($startdate, $enddate, $shortOrLongQuery)
    {
        if ($shortOrLongQuery === 'long') {
            return " AND Exhibition.startdate BETWEEN '". $startdate . "' AND '". $enddate . "' ";
        }

        return " AND E.startdate BETWEEN '". $startdate . "' AND '". $enddate . "' ";
    }

    public function getStringQueryArtistsBirthAndDeathDate($startdate, $enddate, $birthOrDeath, $shortOrLongQuery)
    {
        if ($shortOrLongQuery === 'long') {
            return " AND Person.". $birthOrDeath ." BETWEEN '". $startdate . "' AND '". $enddate . "' ";
        }

        return " AND P.". $birthOrDeath ." BETWEEN '". $startdate . "' AND '". $enddate . "' ";
    }

    public function getStringQueryForPersonIds($queryArray, $shortOrLongQuery)
    {
        if ($queryArray !== 'any') {
            if ($shortOrLongQuery === 'long') {

                $query = "Person.id = " . join(" OR Person.id = ", $queryArray);
                // print $query;

                return " AND ( " . $query . " )";
            }

            $query = "P.id = " . join(" OR P.id = ", $queryArray);

            return " AND (" . $query . ")";
        }

        if ($shortOrLongQuery === 'long') {
            return " AND Person.status <> -1 ";
        }

        return " AND P.status <> -1";
    }

    public function getStringQueryForLocationIds($queryArray, $shortOrLongQuery)
    {
        if ($queryArray !== 'any') {
            if ($shortOrLongQuery === 'long') {
                $query = "Location.id = " . join(" OR Location.id = ", $queryArray);

                return " AND ( " . $query . " )";
            }

            $query = "L.id = " . join(" OR L.id = ", $queryArray);
            return " AND (" . $query . ")";
        }

        if ($shortOrLongQuery === 'long') {
            return " AND Location.status <> -1 ";
        }

        return " AND L.status <> -1";
    }

    public function getStringQueryForExhibitionIds($queryArray, $shortOrLongQuery)
    {
        if ($queryArray !== 'any') {
            if ($shortOrLongQuery === 'long') {
                $query = "Exhibition.id = " . join(" OR Exhibition.id = ", $queryArray);

                return " AND ( " . $query . " )";
            }

            $query = "E.id = " . join(" OR E.id = ", $queryArray);

            return " AND (" . $query . ")";
        }

        if ($shortOrLongQuery === 'long') {
            return " AND " . \AppBundle\Utils\SearchListBuilder::exhibitionVisibleCondition('Exhibition');
        }

        return " AND " . \AppBundle\Utils\SearchListBuilder::exhibitionVisibleCondition('E');
    }


    public function getStringQueryForItemExhibitionIds($queryArray, $shortOrLongQuery)
    {
        if ($queryArray !== 'any') {
            if ($shortOrLongQuery === 'long') {
                $query = "ItemExhibition.id = " . join(" OR ItemExhibition.id = ", $queryArray);

                return " AND ( " . $query . " )";
            }

            $query = "IE.id = " . join(" OR IE.id = ", $queryArray);

            return " AND (" . $query . ")";
        }

        if ($shortOrLongQuery === 'long') {
            return " AND ItemExhibition.status <> -1 ";
        }

        return " AND IE.status <> -1";
    }

    public function getStringQueryForArtists($query, $shortOrLongQuery)
    {
        if ($query !== 'any') {
            if ($shortOrLongQuery === 'long') {
                // return " AND (Person.lastname LIKE '%" . $query . "%' OR Person.firstname LIKE '%" . $query . "%' )";
                return " AND ( CONCAT(Person.name_variant, ' ', Person.firstname, ' ', Person.lastname)  LIKE '%" . $query . "%' OR CONCAT(Person.name_variant, ' ', Person.lastname, ' ', Person.firstname)  LIKE '%" . $query . "%' OR  CONCAT(Person.lastname, '', Person.firstname) LIKE '%" . $query . "%' OR CONCAT(Person.firstname, '', Person.lastname) LIKE '%" . $query . "%' )";
            }

            if ($shortOrLongQuery === 'fullname') {
                // return " AND THEfullname LIKE '%" . $query . "%' ";
                return " AND ( CONCAT(Person.name_variant, ' ', Person.firstname, ' ', Person.lastname)  LIKE '%" . $query . "%' OR CONCAT(Person.name_variant, ' ', Person.lastname, ' ', Person.firstname)  LIKE '%" . $query . "%' OR  CONCAT(Person.lastname, ' ', Person.firstname) LIKE '%" . $query . "%'  OR CONCAT(Person.firstname, ' ', Person.lastname) LIKE '%" . $query . "%'   )";
            }

            return " AND ( CONCAT(P.variantName, ' ', P.familyName, ' ', P.sortName) LIKE '%" . $query . "%' OR CONCAT(P.variantName, ' ', P.sortName, ' ', P.familyName) LIKE '%" . $query . "%' OR CONCAT(P.familyName, '', P.sortName) LIKE '%" . $query . "%' OR CONCAT(P.sortName, '', P.familyName) LIKE '%" . $query . "%' )";
        }

        return " ";
    }

    public function exhibitionAgeDistributionIndex($ids = null)
    {
        $ids !== null ? array_push($ids, 'true') :  $ids = [];

        $repo = $this->getDoctrine()
            ->getRepository('AppBundle:Exhibition');


        // display the artists by birth-year
        $stats = StatisticsController::exhibitionAgeDistribution($em = $this->getDoctrine()->getEntityManager(), null, null, null, null, $ids);
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

        return $this->render('Statistics/person-exhibition-age-index-view.html.twig', [
            'container' => 'container-age',
            'categories' => json_encode($categories),
            'age_at_exhibition_living' => json_encode(array_values($total['age_living'])),
            'age_at_exhibition_deceased' => json_encode(array_values($total['age_deceased'])),
            'exhibition_id' => 0,
        ]);
    }

    protected function exhibitionGenderDistribution($ids = null)
    {
        $ids !== null ? array_push($ids, 'true') : $ids = [];

        // type of work
        $stats = StatisticsController::itemexhibitionGenderDistribution($this->getDoctrine()->getEntityManager(), $ids);

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


    public static function buildExhibitionOrganizerDistribution($em, $currIds = [])
    {
        $dbconn = $em->getConnection();

        $where = '';

        if (in_array("true", $currIds)) {
            // remove true statement from ids
            $pos = array_search('true', $currIds);
            unset($currIds[$pos]);

            if ($where === '') {
                $where = 'WHERE ' . \AppBundle\Utils\SearchListBuilder::exhibitionVisibleCondition('Exhibition');
            }

            $where .= StatisticsController::getStringQueryForExhibitionIds($currIds, 'long');
        }


        $querystr = "SELECT COUNT(*) AS how_many, Exhibition.organizer_type FROM Exhibition
        " . $where . "
        GROUP BY Exhibition.organizer_type
        ORDER BY how_many DESC";



        $stmt = $dbconn->query($querystr);
        $total = 0;
        $stats = [];
        while ($row = $stmt->fetch()) {
            $city = $row['organizer_type'];
            $stats[$city] = $row['how_many'];
            $total += $row['how_many'];
        }

        return [
            'total' => $total,
            'types' => $stats,
        ];
    }

    public static function exhibitionLocationDistribution($em, $currIds = [])
    {
        $dbconn = $em->getConnection();

        $where = '';

        if (in_array("true", $currIds)) {
            // remove true statement from ids
            $pos = array_search('true', $currIds);
            unset($currIds[$pos]);

            if ($where === '') {
                $where = 'WHERE ' . \AppBundle\Utils\SearchListBuilder::exhibitionVisibleCondition('Exhibition');
            }

            $where .= StatisticsController::getStringQueryForExhibitionIds($currIds, 'long');
        }


        $querystr = "
        SELECT COUNT(*) AS how_many, Location.place FROM Exhibition
        INNER JOIN Location ON Location.id = Exhibition.id_location
        " . $where . "
        GROUP BY Location.place
        ORDER BY how_many DESC
        ";

        $stmt = $dbconn->query($querystr);
        $total = 0;
        $stats = [];
        while ($row = $stmt->fetch()) {
            $city = $row['place'];
            $stats[$city] = $row['how_many'];
            $total += $row['how_many'];
        }

        return [
            'total' => $total,
            'types' => $stats,
        ];
    }

    // TODO doesn't work without any filters
    public static function itemExhibitionTypeDistribution($em, $exhibitionId = null, $currIds = [])
    {
        $dbconn = $em->getConnection();


        $where = '';

        $where = !is_null($exhibitionId)
            ? sprintf('WHERE ItemExhibition.id_exhibition=%d', intval($exhibitionId))
            : 'WHERE TypeTerm.id > 0 ';

        if (in_array("true", $currIds)) {
            // remove true statement from ids
            $pos = array_search('true', $currIds);
            unset($currIds[$pos]);

            if ($where === '') {
                $where = 'WHERE TypeTerm.id > 0 ';
            }

            $where .= StatisticsController::getStringQueryForItemExhibitionIds($currIds, 'long');
        }

        $querystr = "
        SELECT TypeTerm.id, TypeTerm.name, TypeTerm.aat, COUNT(*) AS how_many
        FROM ItemExhibition
        LEFT OUTER JOIN Term TypeTerm ON ItemExhibition.type=TypeTerm.id
        " . $where . "
        GROUP BY TypeTerm.id, TypeTerm.name
        ORDER BY TypeTerm.name";

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


    public static function itemExhibitionTypeDistributionFull($em, $labelTerm, $exhibitionId = null)
    {
        $dbconn = $em->getConnection();

        $where = !is_null($exhibitionId)
            ? sprintf('WHERE ItemExhibition.id_exhibition=%d', intval($exhibitionId))
            : '';


        $labelTerm = $labelTerm === 'unknown'
            ? '0_unknown'
            : $labelTerm;

        $andWhere = !is_null($labelTerm)
            ? sprintf('AND TypeTerm.name="%s"', $labelTerm)
            : '';

        $querystr = <<<EOT
SELECT ItemExhibition.id, TypeTerm.name, TypeTerm.aat
FROM ItemExhibition
LEFT OUTER JOIN Term TypeTerm ON ItemExhibition.type=TypeTerm.id
{$where} {$andWhere}
ORDER BY TypeTerm.name
EOT;

        $stmt = $dbconn->query($querystr);
        $total = 0;
        $ids = [];
        while ($row = $stmt->fetch()) {
            array_push($ids,$row['id']);
        }

        return $ids;
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


    // CONTINUE HERE
    public static function exhibitionNationalityPersonIds($em, $nationality, $exhibitionId = null)
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
            ->where("P.nationality = '" . $nationality . "'")
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
        $artistIds = [];
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

            array_push($artistIds, $row['id']);
        }

        return [ 'artists' => $artistIds ];
    }

    public static function exhibitionAgeDistribution($em, $exhibitionId = null, $gender = null, $countryQuery = null, $stringQuery = null, $currIds = [], $exhibitionCountries = [], $organizerTypesQuery = [], $artistBirthDateLeft = 'any' , $artistBirthDateRight= 'any', $artistDeathDateLeft = 'any' , $artistDeathDateRight =  'any', $currIdsExh = [])
    {
        $dbconn = $em->getConnection();

        $conditionCounter = 0;

        $where = '';

        // build where query for exhibitionID
        if (!is_null($exhibitionId)) {
            $where = sprintf('WHERE Exhibition.id=%d',
                intval($exhibitionId));
            $conditionCounter++;
        }

        // build where query for nationality
        if (!is_null($countryQuery) and $countryQuery !== 'any') {
            if ($conditionCounter === 0) {
                $where = 'WHERE ';
            } else {
                $where .= ' AND ';
            }

            $where .= StatisticsController::getPersonQueryString('Person', 'Person.status >= 0', 'country', $countryQuery);
            $where .= " AND ". StatisticsController::getArrayQueryString('Person', 'sex', $gender, 'Person.status <> -1 ');
            $where .= StatisticsController::getStringQueryForArtists($stringQuery, 'fullname');

            if ($artistBirthDateLeft !== 'any' && $artistBirthDateRight !== 'any') {
                $where .= StatisticsController::getStringQueryArtistsBirthAndDeathDate($artistBirthDateLeft, $artistBirthDateRight, 'birthdate', 'long');
            }

            if ($artistDeathDateLeft !== 'any' && $artistDeathDateRight !== 'any') {
                $where .= StatisticsController::getStringQueryArtistsBirthAndDeathDate($artistDeathDateLeft, $artistDeathDateRight, 'deathdate', 'long');
            }


            if (!empty($organizerTypesQuery)) {
                $where .= StatisticsController::getStringQueryForExhibitionsInArtist($organizerTypesQuery, 'organizer_type', 'long');
            }

            $where .=  StatisticsController::getStringQueryForLocationInArtist($exhibitionCountries, 'country', 'long');

        }

        if (in_array("true", $currIds)) {
            // remove true statement from ids
            $pos = array_search('true', $currIds);
            unset($currIds[$pos]);

            if ($where === '') {
                $where = 'WHERE ' . \AppBundle\Utils\SearchListBuilder::exhibitionVisibleCondition('Exhibition');
            }

            $where .= StatisticsController::getStringQueryForPersonIds($currIds, 'long');
        }


        if (in_array("true", $currIdsExh)) {
            // remove true statement from ids
            $pos = array_search('true', $currIdsExh);
            unset($currIdsExh[$pos]);

            if ($where === '') {
                $where = 'WHERE ' . \AppBundle\Utils\SearchListBuilder::exhibitionVisibleCondition('Exhibition');
            }

            $where .= StatisticsController::getStringQueryForExhibitionIds($currIdsExh, 'long');
        }

        $querystr = <<<EOT
SELECT COUNT(*) AS how_many,
YEAR(EB.startdate) - YEAR(EB.birthdate) AS age,
IF (EB.deathdate IS NOT NULL AND YEAR(EB.deathdate) < YEAR(EB.startdate), 'deceased', 'living') AS state
FROM
(SELECT DISTINCT Exhibition.startdate AS startdate, Exhibition.id AS id_exhibition, Person.id AS id_person, Person.birthdate AS birthdate, Person.deathdate AS deathdate
FROM Exhibition
INNER JOIN ItemExhibition ON ItemExhibition.id_exhibition=Exhibition.id
INNER JOIN Person ON ItemExhibition.id_person=Person.id AND Person.birthdate IS NOT NULL
LEFT JOIN Location ON Location.id = Exhibition.id_location
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
        $dbconn = $em->getConnection();

        $conditionCounter = 0;

        $where = '';

        if (in_array("true", $currIds)) {
            // remove true statement from ids
            $pos = array_search('true', $currIds);
            unset($currIds[$pos]);

            if ($where === '') {
                $where = 'WHERE ' . \AppBundle\Utils\SearchListBuilder::exhibitionVisibleCondition('Exhibition');
            }

            $where .= StatisticsController::getStringQueryForExhibitionIds($currIds, 'long');
        }

        $querystr = <<<EOT
SELECT COUNT(*) AS how_many, Person.sex
FROM Exhibition
INNER JOIN ItemExhibition ON ItemExhibition.id_exhibition=Exhibition.id
INNER JOIN Person ON ItemExhibition.id_person=Person.id AND Person.birthdate IS NOT NULL
LEFT JOIN Location ON Location.id = Exhibition.id_location
$where
GROUP BY Person.sex
EOT;


        $stmt = $dbconn->query($querystr);
        $total = 0;
        $male = 0;
        $female = 0;
        while ($row = $stmt->fetch()) {
            if ($row['sex'] === 'M') {
                $male = $row['how_many'];
            }
            else if ($row['sex'] === 'F') {
                $female = $row['how_many'];
            }

            $total += $row['how_many'];
        }

        return [
            'stats' => ['male' => $male, 'female' => $female ],
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
        $em = $this->getDoctrine()->getEntityManager();

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

    function getCountryQueryString($countryModelString, $fallbackModel, $countryCode, $countriesQuery)
    {
        $countryQueryString = '';
        $counterCountry = 0;

        if (is_array($countriesQuery)) {
            foreach($countriesQuery as $country) {
                if ($counterCountry > 0) {
                    $countryQueryString .= ", ";
                }
                $countryQueryString .= "'" . addslashes($country) . "'";
                $counterCountry++;
            }
        }else {
            $countryQueryString = "'". $countriesQuery ."'";
        }

        if ($countriesQuery === 'any') {
            $countryQueryString = $fallbackModel. ".status <> -1";
        }
        else {
            $countryQueryString = $countryModelString . "." . $countryCode . " IN(" . $countryQueryString . ")";
        }

        return $countryQueryString;
    }



    function getArrayQueryString($modelString, $modelSubcode, $queryArray, $fallbackString)
    {
        $modelQueryString = '';
        $counterQueryArray = 0;


        if ($queryArray === '' or $queryArray === 'any') {
            $modelQueryString = $fallbackString;
        } else {
            if (is_array($queryArray)) {
                foreach ($queryArray as $queryElement) {
                    if ($counterQueryArray > 0) {
                        $modelQueryString .= ", ";
                    }
                    $modelQueryString .= "'" . $queryElement . "'";
                    $counterQueryArray++;
                }
            } else {
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
            } else {
                $personQueryString = "'" . $nationalityArray . "'";
            }

            // form the right query with the params
            $personQueryString = $personModelString . "." . $nationalityCode . " IN(" . $personQueryString . ")";
        }

        return $personQueryString;
    }



    // controller is called by /exhibition route to load async the stats

    /**
     * @param $countriesQuery
     * @param $organizerTypeQuery
     * @param $stringQuery
     * @param array $currIds
     * @param string $artistGender
     * @param array $artistNationalities
     * @param string $exhibitionStartDateLeft
     * @param string $exhibitionStartDateRight
     * @return \Symfony\Component\HttpFoundation\Response
     */
    function exhibitionNationalityIndex($countriesQuery, $organizerTypeQuery, $stringQuery, $currIds = [], $artistGender = '', $artistNationalities = [], $exhibitionStartDateLeft = 'any', $exhibitionStartDateRight = 'any')
    {
        $countryQueryString = $this->getCountryQueryString('Pl', 'L', 'countryCode', $countriesQuery);
        $countryQueryString .= " AND ". $this->getArrayQueryString('E', 'organizerType', $organizerTypeQuery, 'L.status <> -1');
        $countryQueryString .= $this->getStringQueryForExhibitions($stringQuery, 'short');


        // $queryStringArtistCountry = $this->getStringQueryForPersonsInExhibitions($artistNationalities, 'nationality', 'short');


        if (!empty($artistGender)) {
            $countryQueryString .= $this->getStringQueryForPersonsInExhibitions($artistGender, 'gender', 'short');
        }

        if ($exhibitionStartDateLeft !== 'any' && $exhibitionStartDateRight !== 'any') {
            $countryQueryString .= $this->getStringQueryExhibitionsStartdate($exhibitionStartDateLeft, $exhibitionStartDateRight, 'short');
        }


        if (in_array("true", $currIds)) {
            // remove true statement from ids
            $pos = array_search('true', $currIds);
            unset($currIds[$pos]);
            $countryQueryString .= $this->getStringQueryForExhibitionIds($currIds, 'short');
        }


        $qb = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();


        // >where("u.created_date BETWEEN '${fromdateaccounts}'


        // inner query to get exhibitions where artists from given countries are exhibiting
        $qbInner = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();

        $qbInner->select([
            'E.id as exhId'
            ])
            ->from('AppBundle:Exhibition', 'E')
            ->leftJoin('AppBundle:ItemExhibition', 'IE',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                "IE.exhibition = E")
            ->leftJoin('AppBundle:Person', 'P',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'P = IE.person')
            ->where(" ( P.nationality = 'BE' OR P.nationality = 'CZ' ) ")
            ->groupBy('E.id');


        $resultInner = $qbInner->getQuery()->getResult();
        // print_r(count($resultInner));


        $qb->select([
            'P.id',
            'P.nationality',
            'Pl.countryCode',
            'COUNT(DISTINCT IE.id) AS numEntries',
            'C.name'
        ])
            ->from('AppBundle:Exhibition', 'E')
            ->leftJoin('E.location', 'L')
            ->leftJoin('L.place', 'Pl')
            ->leftJoin('Pl.country', 'C')
            //->leftJoin('L.place', 'Person')
            // ->leftJoin('E.artists', 'A')
            ->leftJoin('AppBundle:ItemExhibition', 'IE',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'IE.exhibition = E AND IE.title IS NOT NULL')
            ->leftJoin('AppBundle:Person', 'P',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'P = IE.person')
            ->where(\AppBundle\Utils\SearchListBuilder::exhibitionVisibleCondition('E'))
            ->andWhere('L.status <> -1')
            ->andWhere($countryQueryString)
            ->groupBy('P.id,Pl.countryCode')
            ->orderBy('C.name', 'DESC')
        ;

        // TODO SQL QUERY SHOULD NOT FILTER ALL ARTISTS BUT THE EXHIBITIONS WHERE ARTSIST FROM THIS COUNTRIES ARE


        $result = $qb->getQuery()->getResult();

        $statsByCountry = [];
        $statsByNationality = [];
        $lastPersonId = -1;
        foreach ($result as $row) {
            if (0 == $row['numEntries']) {
                continue;
            }

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

            if ($lastPersonId != $row['id']) {
                ++$statsByCountry[$cc]['countByNationality'][$nationality]['countArtists'];
                ++$statsByCountry[$cc]['totalArtists'];
                ++$statsByNationality[$nationality]['countArtists'];

                $lastPersonId = $row['id'];
            }

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
            $doAnyOfTheArtistCountriesExist = 1; // if 0 --> this dataset will be jumped

            // checking if any of the nationalites exist
            if (!empty($artistNationalities)) {

                // reseting the filtering
                $doAnyOfTheArtistCountriesExist = 0;
                foreach ($artistNationalities as $nationality) {
                    // assignment missing?
                    in_array($nationality, array_keys($stats['countByNationality']) )
                        ? $doAnyOfTheArtistCountriesExist = 1 : '';
                }
            }


            if ($doAnyOfTheArtistCountriesExist) {
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
                }

                $y++;
            }
        }

        return $this->render('Statistics/exhibition-nationality-index.html.twig', [
            'countries' => $countries,
            'nationalities' => $xCategories,
            'data' => $valuesFinal,
        ]);
    }
}
