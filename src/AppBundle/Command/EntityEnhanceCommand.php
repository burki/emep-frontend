<?php

// src/AppBundle/Command/EntityEnhanceCommand.php
namespace AppBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

use Doctrine\ORM\EntityManagerInterface;

class EntityEnhanceCommand
extends Command
{
    protected $em;
    protected $googleapisKey = '';
    protected $projectDir;

    public function __construct(EntityManagerInterface $em,
                                ParameterBagInterface $params,
                                string $projectDir)
    {
        parent::__construct();

        $this->em = $em;

        if ($params->has('googleapis.key')) {
            $this->googleapisKey = $params->get('googleapis.key');
        }

        $this->projectDir = $projectDir;
    }

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

            $fnameFull = $this->projectDir
                       . '/data/' . $fname;
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

    protected function fetchEntityFactsByGnd($gnd, $locale = 'en')
    {
        $url = sprintf('http://hub.culturegraph.org/entityfacts/%s', $gnd);
        $result = $this->executeJsonQuery($url, [
            'Accept' => 'application/json',
            'Accept-Language' => $locale, // date-format!
        ]);

        return $result;
    }

    /**
     * Get additional properties, currently from entityfacts and wikidata
     * TODO: Better wikidata queries in
     * site/tool/missing_personwikidata.php
     *
     * Currently not used and tested
     *
     */
    protected function enhancePersonProperties()
    {
        // lookup people who have gnd or ulan who have birthDate/Place or deathDate/Place not set
        $qb = $this->em->createQueryBuilder();

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

            // var_dump('Query for ' . $person->getFullname());
            /*
            // we currently do wikidata in separate task
            foreach (\AppBundle\Entity\Person::$entityfactsLocales as $locale) {
                $wikidata = [];
                if (!empty($gnd)) {
                    $wikidata = \AppBundle\Utils\BiographicalWikidata::fetchByGnd($gnd, $locale);
                }

                if (empty($wikidata->identifier) && !empty($ulan)) {
                    $wikidata = \AppBundle\Utils\BiographicalWikidata::fetchByUlan($ulan, $locale);
                }

                if (!empty($wikidata->identifier)) {
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
            */

            // entityfacts
            if (!empty($gnd)) {
                foreach (\AppBundle\Entity\Person::$entityfactsLocales as $locale) {
                    $entityfacts = $person->getEntityfacts($locale, true);

                    if (is_null($entityfacts)) {
                        continue;
                    }

                    // try to set birth/death place
                    foreach ([ 'birth', 'death' ] as $property) {
                        $key = 'placeOf' . ucfirst($property);
                        $method = 'get' . ucfirst($property) . 'PlaceLabel';
                        $currentValue = $person->$method();
                        if (empty($currentValue) && !empty($entityfacts[$key])) {
                            echo ($person->getId() . ': ' . $entityfacts['preferredName']) . "\n";
                            var_dump($entityfacts[$key]);
                        }

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
                                        echo ($person->getId() . ': ' . $entityfacts['preferredName']) . "\n";
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

    /**
     * Fetch missing entityfact data
     *
     */
    protected function fetchMissingEntityfacts()
    {


        // lookup people who have gnd but entityfacts is null
        $qb = $this->em->createQueryBuilder();

        $qb->select([
                'P',
            ])
            ->from('AppBundle:Person', 'P')
            ->where('P.status <> -1')
            ->andWhere('P.gnd IS NOT NULL')
            ->andWhere('P.entityfacts IS NULL')
            ->orderBy('P.id', 'DESC')
            ;

        $query = $qb->getQuery()
            //->setMaxResults(25) // for testing
            //->setFirstResult(10)
            ;

        $persistCount = 0;
        foreach ($query->getResult() as $person) {
            $persist = false;
            $gnd = $person->getGnd();

            foreach (\AppBundle\Entity\Person::$entityfactsLocales as $locale) {
                $result = $this->fetchEntityFactsByGnd($gnd, $locale);

                if (false !== $result) {
                    $person->setEntityfacts($result, $locale);
                    $entityfacts = $person->getEntityfacts($locale, true);

                    $persist = true;
                }
            }

            if ($persist) {
                $this->em->persist($person);
                ++$persistCount;
            }

            if ($persistCount > 20) {
                $this->em->flush();
                $persistCount = 0;
            }
        }

        if ($persistCount > 0) {
            $this->em->flush();
        }
    }

    /**
     * Fetch entitifacts and try to set additional properties
     */
    protected function enhancePerson()
    {
        $this->fetchMissingEntityfacts();

        $this->enhancePersonProperties();
    }

    protected function enhanceLocation()
    {
        // currently only homepages
        $organizationRepository = $this->em->getRepository('AppBundle:Location');

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
                $this->em->persist($organization);
                $this->em->flush();
            }
        }
    }

    /**
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

        $placeRepository = $this->em->getRepository('AppBundle:Place');
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
                    $this->em->persist($country);
                    $this->em->flush();
                }
            }
        }
    }

    protected function enhanceBibitem()
    {
        // currently googleapis.com/books
        $bibitemRepository = $this->em->getRepository('AppBundle:Bibitem');
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
                               $this->googleapisKey);
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
                $this->em->persist($item);
                $this->em->flush();
            }
        }
    }
}
