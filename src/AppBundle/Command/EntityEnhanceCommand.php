<?php

// src/AppBundle/Command/EntityEnhanceCommand.php
namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

class EntityEnhanceCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('entity:enhance')
            ->setDescription('Enhance Person/Place/Location/Bibitem Entities')
            ->addArgument(
                'type',
                InputArgument::REQUIRED,
                'which entities do you want to enhance (person / place / location / bibitem)'
            )
            ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        switch ($input->getArgument('type')) {
            case 'person':
                return $this->enhancePerson();
                break;

            case 'place':
                return $this->enhancePlace();
                break;

            case 'location':
                return $this->enhanceLocation();
                break;

            case 'country':
                return $this->enhanceCountry();
                break;

            case 'bibitem':
                return $this->enhanceBibitem();
                break;

            default:
                $output->writeln(sprintf('<error>invalid type: %s</error>',
                                         $input->getArgument('type')));
                return 1;
        }
    }

    protected function normalizeUnicode($value)
    {
        if (!class_exists('\Normalizer')) {
            return $value;
        }
        if (!\Normalizer::isNormalized($value)) {
            $normalized = \Normalizer::normalize($value);
            if (false !== $normalized) {
                $value = $normalized;
            }
        }
        return $value;
    }

    /**
     * Executes a query
     *
     * @param string $query
     * @param array|null $headers
     * @param bool|null $assoc
     *
     * @throws NoResultException
     *
     * @return json object representing the query result
     */
    protected function executeJsonQuery($url, $headers = [], $assoc = false)
    {
        if (!isset($this->client)) {
            $this->client = new \EasyRdf_Http_Client();
        }
        $this->client->setUri($url);
        $this->client->resetParameters(true); // clear headers
        foreach ($headers as $name => $val) {
            $this->client->setHeaders($name, $val);
        }
        try {
            $response = $this->client->request();
            if ($response->getStatus() < 400) {
                $content = $response->getBody();
            }
        } catch (\Exception $e) {
            $content = null;
        }
        if (!isset($content)) {
            return false;
        }
        $json = json_decode(self::normalizeUnicode($content), true);

        // API error
        if (!isset($json)) {
            return false;
        }

        return $json;
    }

    protected function loadGndBeacon($files)
    {
        $gndBeacon = [];

        foreach ($files as $key => $fname)
        {
            $info = [];

            $fnameFull = $this->getContainer()->get('kernel')->getRootDir()
                       . '/Resources/data/' . $fname;
            $lines = file($fnameFull);
            foreach ($lines as $line) {
                if (empty($line)) {
                    continue;
                }
                if (preg_match('/^\#/', $line)) {
                    if (preg_match('/^\#\s*(NAME|DESCRIPTION|PREFIX|TARGET)\s*\:\s*(.+)/', $line, $matches)) {
                        $info[strtolower($matches[1])] = trim($matches[2]);
                    }
                    continue;
                }
                $parts = explode('|', $line);
                if (count($parts) >= 3) {
                    $gnd = trim($parts[0]);
                    if (!array_key_exists($gnd, $gndBeacon)) {
                        $gndBeacon[$gnd] = [];
                    }
                    $gndBeacon[$gnd][$key] = $info + [ 'url' => trim($parts[2]) ];
                }
            }
        }

        return $gndBeacon;
    }

    /*
     * TODO: Better wikidata queries in
     * site/tool/missing_personwikidata.php
     */
    protected function enhancePerson()
    {
        $locales = [ 'en' ]; // different labels, e.g of birthPlace for different locales
        /*
        // just test the Service:
        // $wikidata = \AppBundle\Utils\BiographicalWikidata::fetchByUlan(500007835, $locales[0]);
        $wikidata = \AppBundle\Utils\BiographicalWikidata::fetchByGnd(123924464, $locales[0]);

        var_dump($wikidata);
        exit;
        */

        // currently entityfacts and wikidata
        $em = $this->getContainer()->get('doctrine')->getManager();

        // lookup people who have gnd or ulan who have birthDate/Place or deathDate/Place not set
        $qb = $em->createQueryBuilder();

        $qb->select([
                'P',
                "CONCAT(COALESCE(P.familyName,P.givenName), ' ', COALESCE(P.givenName, '')) HIDDEN nameSort"
            ])
            ->from('AppBundle:Person', 'P')
            ->where('P.status <> -1')
            ->andWhere('P.gnd IS NOT NULL OR P.ulan IS NOT NULL')
            ->andWhere('P.birthDate IS NULL OR P.birthPlaceLabel IS NULL OR P.deathDate IS NULL OR P.deathPlaceLabel IS NULL')
            ->orderBy('nameSort')
            ;

        $query = $qb->getQuery()
            /*
            ->setMaxResults(10) // for testing
            ->setFirstResult(10)
            */
            ;

        $UPDATE_PROPERTIES =  [
            'gender' => 'gender',
            'ulan' => 'ulan',
            'gnd' => 'gnd',
            'dateOfBirth' => 'birthDate',
            'placeOfBirth' => 'birthPlaceLabel',
            'dateOfDeath' => 'deathDate',
            'placeOfDeath' => 'deathPlaceLabel',
        ];

        $items = [];

        foreach ($query->getResult() as $person) {
            $persist = false;
            $gnd = $person->getGnd();
            $ulan = $person->getUlan();
            if (empty($gnd) && empty($ulan)) {
                continue;
            }
            var_dump('Query for ' . $person->getFullname());
            foreach ($locales as $locale) {
                $wikidata = [];
                if (!empty($gnd)) {
                    $wikidata = \AppBundle\Utils\BiographicalWikidata::fetchByGnd($gnd, $locale);
                }
                if (empty($wikidata->identifier) && !empty($ulan)) {
                    $wikidata = \AppBundle\Utils\BiographicalWikidata::fetchByUlan($ulan, $locale);
                }
                if (!empty($wikidata->identifier)) {
                    /*
                    if (is_null($additional)) {
                        $additional = [];
                    }
                    if (!array_key_exists('wikidata', $additional)) {
                        $additional['wikidata'] = [];
                    }
                    $additional['wikidata'][$locale] = (array)$wikidata;
                    $person->setAdditional($additional);
                    $persist = true;
                    */
                    // compile record of previously empty and now filled
                    // TODO: maybe handle changes as well
                    $updateProperties = [];
                    foreach ($UPDATE_PROPERTIES as $property => $method)
                    {
                        if (!empty($wikidata->{$property})) {
                            $getMethod = 'get' . ucfirst($method);
                            $value = $person->$getMethod();
                            if (empty($value)) {
                                $updateProperties[$method] = $wikidata->{$property};
                            }
                        }
                    }
                    if (!empty($updateProperties)) {
                        $item = $updateProperties;
                        $item['name'] = $person->getFullname();
                        $item['identifier'] = $wikidata->identifier;
                        $items[$person->getId()] = $item;
                    }
                }
            }

            $gnd = $person->getGnd();
            if (false && !empty($gnd)) {
                foreach ([ 'en' ] as $locale) {
                    $entityfacts = $person->getEntityfacts($locale, true);
                    if (is_null($entityfacts)) {
                        $url = sprintf('http://hub.culturegraph.org/entityfacts/%s', $gnd);
                        $result = $this->executeJsonQuery($url, [
                            'Accept' => 'application/json',
                            'Accept-Language' => $locale, // date-format!
                        ]);
                        if (false !== $result) {
                            $person->setEntityfacts($result, $locale);
                            $entityfacts = $person->getEntityfacts($locale, true);

                            if ('de' == $locale && !empty($result['biographicalOrHistoricalInformation'])) {
                                $description = $person->getDescription();
                                if (!array_key_exists($locale, $description)) {
                                    $description[$locale] = $result['biographicalOrHistoricalInformation'];
                                    $person->setDescription($description);
                                }
                            }
                            $persist = true;
                        }
                    }
                    if (!is_null($entityfacts)) {
                        $fullname = $person->getFullname();
                        if (empty($fullname)) {
                            // set surname - e.g. http://hub.culturegraph.org/entityfacts/118676059
                            foreach ([ 'surname' => 'givenName' ] as $src => $property) {
                                if (!empty($entityfacts[$src])) {
                                    $method = 'set' . ucfirst($property);
                                    $person->$method($entityfacts[$src]);
                                    $persist = true;
                                }
                            }
                        }

                        // try to set birth/death place
                        foreach ([ 'birth', 'death' ] as $property) {
                            if ('en' == $locale) {
                                // we use english locale because of date format

                                $key = 'dateOf' . ucfirst($property);
                                if (!empty($entityfacts[$key])) {
                                    $method = 'get' . ucfirst($property) . 'Date';
                                    $date = $person->$method();
                                    if (empty($date)) {
                                        $value = $entityfacts[$key];
                                        if (preg_match('/^\d{4}$/', $value)) {
                                            $value .= '-00-00';
                                        }
                                        else {
                                            $date = \DateTime::createFromFormat('F d, Y', $value);
                                            unset($value);
                                            if (isset($date)) {
                                                $res = \DateTime::getLastErrors();
                                                if (0 == $res['warning_count'] && 0 == $res['error_count']) {
                                                    $date_str = $date->format('Y-m-d');
                                                    if ('0000-00-00' !== $date_str) {
                                                        $value = $date_str;
                                                    }
                                                }
                                            }
                                        }
                                        if (isset($value)) {
                                            var_dump($entityfacts['preferredName']);
                                            var_dump($property);
                                            var_dump($value);
                                            $method = 'set' . ucfirst($property) . 'Date';
                                            $person->$method($value);
                                            $persist = true;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
            if ($persist) {
                $em->persist($person);
                $em->flush();
            }
        }

        if (!empty($items)) {
            // write to csv
            $headers = array_merge(['id', 'name', 'identifier'], array_values($UPDATE_PROPERTIES));

            $fname = $this->getContainer()->get('kernel')->getProjectDir()
                        . '/person-enhance.csv';
            $writer = new \Ddeboer\DataImport\Writer\CsvWriter();
            $writer->setStream(fopen($fname, 'w'));
            $writer->prepare();
            $writer->prepare();
            $writer->writeItem($headers);
            foreach ($items as $id => $item) {
                $row = [];
                foreach ($headers as $key) {
                    if ('id' == $key) {
                        $row[$key] = $id;
                    }
                    else {
                        $row[$key] = array_key_exists($key, $item)
                            ? $item[$key] : '';
                    }
                }
                $writer->writeItem($row);
            }
            $writer->finish();
        }
    }

    /*
    protected function enhancePlace()
    {
        // currently only geonames
        // TODO: maybe get outlines
        // http://www.geonames.org/servlet/geonames?&srv=780&geonameId=2921044&type=json
        $em = $this->getContainer()->get('doctrine')->getManager();
        $placeRepository = $em->getRepository('AppBundle:Place');

        foreach ([ 'nation', 'country',
                  'state', 'metropolitan area',
                  'inhabited place', 'neighborhood' ] as $type) {
            $places = $placeRepository->findBy([ 'type' => $type,
                                                 'geonames' => null]);
            foreach ($places as $place) {
                $geo = $place->getGeo();
                if (empty($geo) || false === strpos($geo, ':')) {
                    continue;
                }
                $persist = false;
                list($lat, $long) = explode(':', $geo, 2);
                $url = sprintf('http://api.geonames.org/extendedFindNearby?lat=%s&lng=%s&username=burckhardtd',
                               $lat, $long);

                $xml = simplexml_load_file($url);
                foreach ($xml->geoname as $geoname) {
                    switch ($type) {
                        case 'nation':
                            if ('PCLI' == $geoname->fcode) {
                                $geonames = (string)($geoname->geonameId);
                                var_dump($place->getName() . ': '
                                 . (string)($geoname->name) . ' - ' . $geonames );
                                $place->setGeonames($geonames);
                                $persist = true;
                            }
                            break;

                        case 'country':
                            if ('ADM1' == $geoname->fcode) {
                                $geonames = (string)($geoname->geonameId);
                                var_dump($place->getName() . ': '
                                 . (string)($geoname->name) . ' - ' . $geonames );
                                $place->setGeonames($geonames);
                                $persist = true;
                            }
                            break;

                        case 'state':
                            if ('ADM1' == $geoname->fcode) {
                                $geonames = (string)($geoname->geonameId);
                                var_dump($place->getName() . ': '
                                 . (string)($geoname->name) . ' - ' . $geonames );
                                $place->setGeonames($geonames);
                                $persist = true;
                            }
                            break;

                        case 'metropolitan area':
                            if ('ADM2' == $geoname->fcode) {
                                $geonames = (string)($geoname->geonameId);
                                var_dump($place->getName() . ': '
                                 . (string)($geoname->name) . ' - ' . $geonames );
                                $place->setGeonames($geonames);
                                $persist = true;
                            }
                            break;

                        case 'inhabited place':
                            if ('PPLA3' == $geoname->fcode || 'ADM4' == $geoname->fcode) {
                                $geonames = (string)($geoname->geonameId);
                                var_dump($place->getName() . ': '
                                 . (string)($geoname->name) . ' - ' . $geonames );
                                $place->setGeonames($geonames);
                                $persist = true;
                            }
                            break;

                        case 'neighborhood':
                            if ('PPLX' == $geoname->fcode) {
                                $geonames = (string)($geoname->geonameId);
                                var_dump($place->getName() . ': '
                                 . (string)($geoname->name) . ' - ' . $geonames );
                                $place->setGeonames($geonames);
                                $persist = true;
                            }
                            break;
                    }
                }
                if ($persist) {
                    $em->persist($place);
                    $em->flush();
                }
            }

            $places = $placeRepository->findBy([ 'type' => $type ]);
            foreach ($places as $place) {
                $persist = false;
                $additional = $place->getAdditional();
                if (!is_null($additional) && array_key_exists('bounds', $additional)) {
                    continue; // TODO: maybe option to force update
                }
                $geonames = $place->getGeonames();
                if (empty($geonames)) {
                    continue;
                }
                $url = sprintf('http://api.geonames.org/get?geonameId=%s&username=burckhardtd',
                               $geonames);
                $xml = simplexml_load_file($url);
                $json = json_encode($xml);
                $info_array = json_decode($json, true);
                if (array_key_exists('bbox', $info_array)) {
                    if (is_null($additional)) {
                        $additional = [];
                    }

                    $info = $info_array['bbox'];
                    $additional['bounds'] = [
                        [ $info['south'], $info['west'] ],
                        [ $info['north'], $info['east'] ],
                    ];

                    foreach ( [ 'areaInSqKm', 'population' ] as $key) {
                        if (array_key_exists($key, $info_array)) {
                            $additional[$key] = $info_array[$key];
                        }
                    }

                    $place->setAdditional($additional);
                    $persist = true;
                }
                if ($persist) {
                    $em->persist($place);
                    $em->flush();
                }
            }
        }
    }
    */

    protected function enhanceLocation()
    {
        // currently only homepages
        $em = $this->getContainer()->get('doctrine')->getManager();
        $organizationRepository = $em->getRepository('AppBundle:Location');

        $criteria = new \Doctrine\Common\Collections\Criteria();
        $criteria->where($criteria->expr()->orX(
            $criteria->expr()->isNull('url'),
            $criteria->expr()->isNull('foundingDate'),
            $criteria->expr()->isNull('dissolutionDate')
        ))
        ->andWhere($criteria->expr()->neq('status', -1));

        $organizations = $organizationRepository->matching($criteria);
        foreach ($organizations as $organization) {
            $persist = false;
            $gnd = $organization->getGnd();
            if (empty($gnd)) {
                continue;
            }

            $corporateBody = \AppBundle\Utils\CorporateBodyData::fetchByGnd($gnd);
            if (is_null($corporateBody) || !$corporateBody->isDifferentiated) {
                continue;
            }

            // properties we currently want to fetch
            foreach ([
                    'dateOfEstablishment',
                    'dateOfTermination',
                    'homepage',
                ] as $src)
            {
                if (!empty($corporateBody->{$src})) {
                    switch ($src) {
                        case 'dateOfEstablishment':
                            $val = $organization->getFoundingDate();
                            if (empty($val)) {
                                $organization->setFoundingDate($corporateBody->{$src});
                                $persist = true;
                            }
                            break;

                        case 'dateOfTermination':
                            $val = $organization->getDissolutionDate();
                            if (empty($val)) {
                                $organization->setDissolutionDate($corporateBody->{$src});
                                $persist = true;
                            }
                            break;

                        case 'homepage':
                            $val = $organization->getUrl();
                            if (empty($val)) {
                                $organization->setUrl($corporateBody->{$src});
                                $persist = true;
                            }
                            break;
                    }
                }
            }

            if ($persist) {
                var_dump(json_encode($organization));
                $em->persist($organization);
                $em->flush();
            }
        }
    }

    /*
     * TODO: add additional fields to Country-table
     */
    protected function enhanceCountry()
    {
        // currently info from http://api.geonames.org/countryInfo?username=burckhardtd';
        $url = 'http://api.geonames.org/countryInfo?username=burckhardtd';

        $xml = simplexml_load_file($url);
        $json = json_encode($xml);
        $info_array = json_decode($json, true);

        $info_by_countrycode = [];
        $info_by_geonames = [];
        foreach ($info_array['country'] as $country => $info) {
            $info_by_countrycode[$info['countryCode']] = $info;
            $info_by_geonames[$info['geonameId']] = $info;
        }

        $em = $this->getContainer()->get('doctrine')->getManager();

        $placeRepository = $em->getRepository('AppBundle:Place');
        foreach ([ 'nation' ] as $type) {
            $places = $placeRepository->findBy([ 'type' => $type ]);
            foreach ($places as $country) {
                $persist = false;
                $info = null;
                $countryCode = $country->getCountryCode();
                if (!empty($countryCode)) {
                    if (array_key_exists($countryCode, $info_by_countrycode)) {
                        $info = $info_by_countrycode[$countryCode];
                    }
                }
                else {
                    $geonames = $country->getGeonames();
                    if (!empty($geonames) && array_key_exists($geonames, $info_by_geonames)) {
                        $info = $info_by_geonames[$geonames];
                        $country->setCountryCode($info['countryCode']);
                        $persist = true;
                    }
                }
                if (!empty($info)) {
                    $persist = true;

                    $additional = $country->getAdditional();
                    if (is_null($additional)) {
                        $additional = [];
                    }

                    $additional['bounds'] = [
                        [ $info['south'], $info['west'] ],
                        [ $info['north'], $info['east'] ],
                    ];

                    foreach ( [ 'areaInSqKm', 'population' ] as $key) {
                        if (array_key_exists($key, $info)) {
                            $additional[$key] = $info[$key];
                        }
                    }

                    $country->setAdditional($additional);
                }

                if ($persist) {
                    $em->persist($country);
                    $em->flush();
                }
            }
        }
    }

    protected function enhanceBibitem()
    {
        // currently googleapis.com/books
        $googleapisKey = '';
        if ($this->getContainer()->hasParameter('googleapis.key')) {
            $googleapisKey = $this->getContainer()->getParameter('googleapis.key');
        }

        $em = $this->getContainer()->get('doctrine')->getManager();
        $bibitemRepository = $em->getRepository('AppBundle:Bibitem');
        $items = $bibitemRepository->findBy([ 'status' => [0, 1] ]);
        foreach ($items as $item) {
            $persist = false;
            $isbns = $item->getIsbnListNormalized(false);
            if (empty($isbns)) {
                continue;
            }
            $additional = $item->getAdditional();
            if (is_null($additional) || !array_key_exists('googleapis-books', $additional)) {
                $url = sprintf('https://www.googleapis.com/books/v1/volumes?q=isbn:%s&key=%s',
                               $isbns[0],
                               $googleapisKey);
                // var_dump($url);
                $result = $this->executeJsonQuery($url, [
                    'Accept' => 'application/json',
                    // 'Accept-Language' => $locale, // date-format!
                ]);
                if (false !== $result && $result['totalItems'] > 0) {
                    $resultItem = $result['items'][0];
                    if (!empty($resultItem['selfLink'])) {
                        $result = $this->executeJsonQuery($resultItem['selfLink'], [
                            'Accept' => 'application/json',
                            // 'Accept-Language' => $locale, // date-format!
                        ]);
                        if (false !== $result) {
                            $resultItem = $result;
                        }
                    }

                    if (is_null($additional)) {
                        $additional = [];
                    }

                    $additional['googleapis-books'] = $resultItem;
                    $item->setAdditional($additional);

                    $persist = true;
                }
            }

            if ($persist) {
                $em->persist($item);
                $em->flush();
            }
        }
    }
}
