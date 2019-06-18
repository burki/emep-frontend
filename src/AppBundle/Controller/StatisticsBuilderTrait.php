<?php

/**
 *
 * Shared methods to build up statistics
 *
 */

namespace AppBundle\Controller;

trait StatisticsBuilderTrait
{
    static $countryMap = [ 'UA' => 'RU' ];

    function processExhibitionNationality($stmt)
    {
        $statsByCountry = [];
        $statsByNationality = [];
        while ($row = $stmt->fetch()) {
            $cc = $row['countryCode'];
            if (is_null($cc)) {
                continue;
            }

            if (array_key_exists($cc, self::$countryMap)) {
                $cc = self::$countryMap[$cc];
            }

            if (!array_key_exists($cc, $statsByCountry)) {
                $statsByCountry[$cc] = [
                    'name' => $row['countryCode'],
                    'countByNationality' => [],
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
                    'countItemExhibition' => 0,
                ];
            }

            if (!array_key_exists($nationality, $statsByCountry[$cc]['countByNationality'])) {
                $statsByCountry[$cc]['countByNationality'][$nationality] = [
                    'countItemExhibition' => 0,
                ];
            }

            $statsByCountry[$cc]['countByNationality'][$nationality]['countItemExhibition'] += $row['numEntries'];
            $statsByCountry[$cc]['totalItemExhibition'] += $row['numEntries'];
            $statsByNationality[$nationality]['countItemExhibition'] += $row['numEntries'];
        }

        $key = 'countItemExhibition';

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
            $xCategories = array_merge(array_slice($xCategories, 0, $maxNationality - 1),
                                       [ 'unknown', 'other' ]);
        }

        $valuesFinal = [];
        $y = 0;
        foreach ($statsByCountry as $cc => $stats) {
            if (0 == $stats['totalItemExhibition']) {
                continue;
            }

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

        return [
            'countries' => $countries,
            'nationalities' => $xCategories,
            'data' => $valuesFinal,
        ];
    }

    function processExhibitionGender($stmt)
    {
        $total = 0;
        $stats = [];
        $frequency_count = [];

        while ($row = $stmt->fetch()) {
            $gender = '[unknown]';

            if ($row['person_gender'] == 'M') {
                $gender = 'male';
            }
            else if ($row['person_gender'] == 'F') {
                $gender = 'female';
            }

            $key = $gender;
            $how_many = (int)$row['how_many'];
            $stats[$key] = $how_many;
            $total += $row['how_many'];
        }

        $data = [];

        foreach ($stats as $gender => $count) {
            // $percentage = 100.0 * $count / $total;
            $dataEntry = [
                'name' => $gender,
                'y' => (int)$count,
            ];
            $data[] = $dataEntry;
        }

        return [
            'container' => 'container-gender',
            'total' => $total,
            'data' => json_encode($data),
        ];

    }

    function processExhibitionByMonth($stmt)
    {
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
        $max_year = $min_year;

        $data = $data_yearly = $categories = $scatter_data = $scatter_categories = [];

        $keys = array_keys($frequency_count);
        if (empty($keys)) {
            return [];
        }

        $i = $min = $keys[0];
        $max = $keys[count($keys) - 1];
        $sum = 0;

        while ($i <= $max) {
            $key = $i;
            $categories[] = sprintf('%04d-%02d',
                                    $year = intval($i / 100), $month = $i % 100);

            if (!array_key_exists($year, $data_yearly)) {
                $data_yearly[$year] = 0;
            }

            $count = array_key_exists($key, $frequency_count) ? $frequency_count[$key] : 0;
            $sum += $count;
            $data[] = $count;
            $data_yearly[$year] += $count;

            if ($count > 0) {
                $max_year = $year;

                $scatter_data[] = [
                    'y' => $year - $min_year,
                    'x' => $month - 1,
                    'count' => $count, 'year' => $year,
                    'marker' => [ 'radius' => intval(2 * sqrt($count) + 0.5) ]
                ];
            }

            if ($i % 100 < 12) {
                ++$i;
            }
            else {
                $i = $i + (100 - $i % 100) + 1;
            }
        }


        $scatter_categories = range($min_year, $max_year);

        $data_avg = round(1.0 * $sum / count($data), 1);
        $data_avg_yearly = round(1.0 * $sum / count(array_keys($data_yearly)), 1);

        return [
            'data_avg' => $data_avg,
            'categories' => json_encode($categories),
            'data' => json_encode($data),
            'data_avg_yearly' => $data_avg_yearly,
            'categories_yearly' => json_encode(array_keys($data_yearly)),
            'data_yearly' => json_encode(array_values($data_yearly)),
            'scatter_data' => json_encode($scatter_data),
            'scatter_categories' => json_encode($scatter_categories),
        ];
    }

    function processExhibitionAge($stmt)
    {
        $min_age = $max_age = 0;

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

        $stats = [
            'min_age' => $min_age,
            'max_age' => $max_age,
            'age_count' => $ageCount,
        ];

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

        return [
            'container' => 'container-age',
            'categories' => json_encode($categories),
            'age_at_exhibition_living' => json_encode(array_values($total['age_living'])),
            'age_at_exhibition_deceased' => json_encode(array_values($total['age_deceased'])),
        ];
    }

    function processExhibitionPlace($stmt)
    {
        $total = 0;
        $stats = [];
        while ($row = $stmt->fetch()) {
            $stats[$row['place']] = $row['how_many'];
            $total += $row['how_many'];
        }

        $data = [];

        foreach ($stats as $place => $count) {
            $percentage = 100.0 * $count / $total;
            $dataEntry = [
                'name' => $place,
                'y' => (int)$count,
            ];
            if ($percentage < 5) {
                $dataEntry['dataLabels'] = [ 'enabled' => false ];
            }
            $data[] = $dataEntry;
        }

        return [
            'container' => 'container-location',
            'total' => $total,
            'data' => json_encode($data),
        ];
    }

    function processExhibitionOrganizerType($stmt)
    {
        $total = 0;
        $stats = [];
        while ($row = $stmt->fetch()) {
            $stats[$row['organizer_type']] = $row['how_many'];
            $total += $row['how_many'];
        }

        $data = [];

        foreach ($stats as $type => $count) {
            if (empty($type)) {
                $type = '[not set]';
            }

            $percentage = 100.0 * $count / $total;
            $dataEntry = [
                'name' => $type,
                'y' => (int)$count,
            ];

            if ($percentage < 5) {
                $dataEntry['dataLabels'] = [ 'enabled' => false ];
            }

            $data[] = $dataEntry;
        }

        return [
            'container' => 'container-organizer',
            'total' => $total,
            'data' => json_encode($data),
        ];
    }

    function processPersonNationality($stmt)
    {
        $data = [];
        while ($row = $stmt->fetch()) {
            $data[] = [
                'name' => empty($row['nationality']) ? 'unknown' : $row['nationality'],
                'y' => (int)$row['how_many'],
            ];
        }

        return [
            'data' => json_encode($data),
        ];
    }

    function processPersonBirthDeath($queries)
    {
        $subtitle = '';
        $data = [];
        $max_year = $min_year = 0;
        foreach ([ 'birth', 'death' ] as $key) {
            $stmt = $queries[$key]->execute();

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

        $categories = [];
        for ($year = $min_year; $year <= $max_year; $year++) {
            $categories[] = 0 == $year % 5 ? $year : '';
            foreach ([ 'birth', 'death' ] as $key) {
                $total[$key][$year] = [
                    'name' => $year,
                    'y' => isset($data[$year][$key])
                        ? intval($data[$year][$key]) : 0,
                ];
            }
        }

        return [
            'subtitle' => json_encode($subtitle),
            'categories' => json_encode($categories),
            'person_birth' => json_encode(array_values($total['birth'])),
            'person_death' => json_encode(array_values($total['death'])),
        ];
    }

    function processPersonDistribution($queries)
    {
        $data = [];
        $data_median = [];

        foreach ($queries as $type => $query) {
            $data[$type] = [];

            $stmt = $query->execute();
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
        return [
            'data' => json_encode($data['exhibition']),
            'min' => $min,
            'max' => $max,
            'data_median' => $data_median['exhibition'],
        ];
    }

    function processPersonPopularity($stmt, $lang)
    {
        $data = [];
        while ($row = $stmt->fetch()) {
            if (empty($row['additional'])) {
                continue;
            }

            $how_many = $row['count_exhibition'];
            $additional = json_decode($row['additional'], true);
            if (array_key_exists('wikistats', $additional)
                && array_key_exists($lang, $additional['wikistats']))
            {
                $single_data = [
                    'name' => $row['person'], // person
                    'num' => (int)$how_many,
                    'id' => $row['person_id'],
                    'x' => (int)$how_many + 0.3 * rand(-1, 1), //
                    'y' => (int)$additional['wikistats'][$lang], // num hits
                ];
                $data[] = $single_data;
            }
        }

        /*
        // already sorted
        usort($data, function($a, $b) {
            return $a['y'] == $b['y'] ? 0 : ($a['y'] > $b['y'] ? -1 : 1);
        });
        */

        return [
            'lang' => $lang,
            'data' => json_encode($data),
            'persons' => $data,
        ];
    }

    function processItemExhibitionType($stmt)
    {
        $data = [];
        while ($row = $stmt->fetch()) {
            if (empty($row['type'])) {
                $name = '[not set]';
            }
            else if ('0_unknown' == $row['type']) {
                $name = 'unknown';
            }
            else {
                $name = $row['type'];
            }

            $data[] = [
                'name' => $name,
                'y' => (int)$row['how_many'],
            ];
        }

        return [
            'container' => 'itemexhibition-type',
            'data' => json_encode($data),
        ];
    }

    function processLocationType($stmt)
    {
        $data = [];
        while ($row = $stmt->fetch()) {
            $data[] = [
                'name' => empty($row['type']) ? '[not set]' : $row['type'],
                'y' => (int)$row['how_many'],
            ];
        }

        return [
            'data' => json_encode($data),
        ];
    }

    function processLocationCountry($stmt)
    {
        $data = [];
        while ($row = $stmt->fetch()) {
            $data[] = [
                'name' => empty($row['country_code']) ? '[not set]' : $row['country_code'],
                'y' => (int)$row['how_many'],
            ];
        }

        return [
            'data' => json_encode($data),
        ];
    }

    function buildExhibitionCharts($request, $urlGenerator, $listBuilder)
    {
        $charts = [];

        // exhibition gender artists
        $listBuilder = $this->instantiateListBuilder($request, $urlGenerator, 'stats-gender', $listBuilder->getEntity());
        $query = $listBuilder->query();
        // echo $query->getSQL();

        $stmt = $query->execute();
        $renderParams = $this->processExhibitionGender($stmt);

        if (!empty($renderParams)) {
            $template = $this->get('twig')->loadTemplate('Statistics/exhibition-gender-artists-index.html.twig');

            $charts[] = $template->render($renderParams);
        }

        // exhibition country-nationality matrix
        $listBuilder = $this->instantiateListBuilder($request, $urlGenerator, 'stats-nationality', $listBuilder->getEntity());
        $query = $listBuilder->query();
        // echo $query->getSQL();

        $stmt = $query->execute();
        $renderParams = $this->processExhibitionNationality($stmt);
        if (!empty($renderParams)) {
            $template = $this->get('twig')->loadTemplate('Statistics/exhibition-nationality-index.html.twig');

            $charts[] = $template->render($renderParams);
        }

        // by month
        $listBuilder = $this->instantiateListBuilder($request, $urlGenerator, 'stats-by-month', $listBuilder->getEntity());

        $query = $listBuilder->query();
        // echo $query->getSQL();

        $stmt = $query->execute();
        $renderParams = $this->processExhibitionByMonth($stmt);
        if (!empty($renderParams)) {
            $template = $this->get('twig')->loadTemplate('Statistics/exhibition-by-month-index.html.twig');

            $charts[] = $template->render($renderParams);
        }

        // exhibition age
        $listBuilder = $this->instantiateListBuilder($request, $urlGenerator, 'stats-age', $listBuilder->getEntity());

        $query = $listBuilder->query();
        $innerSql = $query->getSQL();

        $sql = <<<EOT
SELECT COUNT(*) AS how_many,
YEAR(EB.startdate) - YEAR(EB.birthdate) AS age,
IF (EB.deathdate IS NOT NULL AND YEAR(EB.deathdate) < YEAR(EB.startdate), 'deceased', 'living') AS state
FROM
({$innerSql}) AS EB
GROUP BY age, state
ORDER BY age, state, how_many
EOT;

        $params = $query->getParameters();
        $connection = $query->getConnection();
        foreach ($params as $key => $values) {
            if (is_array($values)) {
                $sql = str_replace(':' . $key,
                                   join(', ', array_map(function ($val) use ($connection)  { return is_int($val) ? $val : $connection->quote($val); }, $values)),
                                   $sql);
            }
        }

        $stmt = $connection->executeQuery($sql, $params);
        $renderParams = $this->processExhibitionAge($stmt);
        if (!empty($renderParams)) {
            $template = $this->get('twig')->loadTemplate('Statistics/person-exhibition-age-index.html.twig');

            $charts[] = $template->render($renderParams);
        }

        // place
        $listBuilder = $this->instantiateListBuilder($request, $urlGenerator, 'stats-place', $listBuilder->getEntity());
        $query = $listBuilder->query();
        // echo $query->getSQL();

        $stmt = $query->execute();
        $renderParams = $this->processExhibitionPlace($stmt);
        if (!empty($renderParams)) {
            $template = $this->get('twig')->loadTemplate('Statistics/exhibition-city-index.html.twig');

            $charts[] = $template->render($renderParams);
        }

        // type of organizer
        $listBuilder = $this->instantiateListBuilder($request, $urlGenerator, 'stats-organizer-type', $listBuilder->getEntity());
        $query = $listBuilder->query();
        // echo $query->getSQL();

        $stmt = $query->execute();
        $renderParams = $this->processExhibitionOrganizerType($stmt);
        if (!empty($renderParams)) {
            $template = $this->get('twig')->loadTemplate('Statistics/exhibition-organizer-index.html.twig');

            $charts[] = $template->render($renderParams);
        }

        return $charts;
    }

    function buildPersonCharts($request, $urlGenerator, $listBuilder)
    {
        $charts = [];

        // nationality
        $listBuilder = $this->instantiateListBuilder($request, $urlGenerator, 'stats-nationality', $listBuilder->getEntity());
        $query = $listBuilder->query();
        // echo $query->getSQL();

        $stmt = $query->execute();
        $renderParams = $this->processPersonNationality($stmt);
        if (!empty($renderParams)) {
            $template = $this->get('twig')->loadTemplate('Statistics/person-nationality-index.html.twig');

            $charts[] = $template->render($renderParams);
        }

        // birth/death
        $listBuilderBirth = $this->instantiateListBuilder($request, $urlGenerator, 'stats-by-year-birth', $listBuilder->getEntity());
        // $query = $listBuilderBirth->query();
        // echo $query->getSQL()

        $listBuilderDeath = $this->instantiateListBuilder($request, $urlGenerator, 'stats-by-year-death', $listBuilder->getEntity());
        // $query = $listBuilderDeath->query();
        //  echo $query->getSQL());

        $renderParams = $this->processPersonBirthDeath([
            'birth' => $listBuilderBirth->query(),
            'death' => $listBuilderDeath->query(),
        ]);

        if (!empty($renderParams)) {
            $template = $this->get('twig')->loadTemplate('Statistics/person-by-year-index.html.twig');

            $charts[] = $template->render($renderParams);
        }

        // exhibition-distribution
        $listBuilder = $this->instantiateListBuilder($request, $urlGenerator, 'stats-exhibition-distribution', $listBuilder->getEntity());
        $query = $listBuilder->query();
        // echo $query->getSQL();

        $stmt = $query->execute();
        $renderParams = $this->processPersonDistribution([ 'exhibition' => $query ]);
        if (!empty($renderParams)) {
            $template = $this->get('twig')->loadTemplate('Statistics/person-distribution-index.html.twig');

            $charts[] = $template->render($renderParams);
        }

        // wikipedia
        $lang = in_array($request->get('lang'), [ 'en', 'de', 'fr' ])
            ? $request->get('lang') : 'en';

        $listBuilder = $this->instantiateListBuilder($request, $urlGenerator, 'stats-popularity', $listBuilder->getEntity());
        $query = $listBuilder->query();
        // echo $query->getSQL();

        $stmt = $query->execute();
        $renderParams = $this->processPersonPopularity($stmt, $lang);
        if (!empty($renderParams)) {
            $template = $this->get('twig')->loadTemplate('Statistics/person-wikipedia-index.html.twig');

            $charts[] = $template->render($renderParams);
        }

        return $charts;
    }

    function buildLocationCharts($request, $urlGenerator, $listBuilder)
    {
        $prefix = 'Organizer' ==  $listBuilder->getEntity() ? 'organizer' : 'location';

        $charts = [];

        // type
        $listBuilder = $this->instantiateListBuilder($request, $urlGenerator, 'stats-type', $listBuilder->getEntity());
        $query = $listBuilder->query();
        // echo $query->getSQL();

        $stmt = $query->execute();
        $renderParams = $this->processLocationType($stmt);
        if (!empty($renderParams)) {
            $template = $this->get('twig')->loadTemplate('Statistics/' . $prefix . '-type-index.html.twig');

            $charts[] = $template->render($renderParams);
        }

        // country
        $listBuilder = $this->instantiateListBuilder($request, $urlGenerator, 'stats-country', $listBuilder->getEntity());
        $query = $listBuilder->query();
        // echo $query->getSQL();

        $stmt = $query->execute();
        $renderParams = $this->processLocationCountry($stmt);
        if (!empty($renderParams)) {
            $template = $this->get('twig')->loadTemplate('Statistics/' . $prefix . '-country-index.html.twig');

            $charts[] = $template->render($renderParams);
        }

        return $charts;
    }
}
