<?php

// src/AppBundle/Command/PersonCommand.php

namespace AppBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Doctrine\ORM\EntityManagerInterface;

class PersonFetchCommand extends Command
{
    protected $em;

    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct();

        $this->em = $em;
    }

    protected function configure(): void
    {
        $this
            ->setName('person:fetch')
            ->setDescription('Fetch Info about Person from various sources')
            ->addArgument(
                'action',
                InputArgument::REQUIRED,
                'what you want to fetch (wikilinks / wikistats)'
            )
            ->addOption(
                'overwrite',
                null,
                InputOption::VALUE_NONE,
                'If set, existing entities will be overwritten'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        switch ($input->getArgument('action')) {
            case 'wikilinks':
                return $this->fetchWikilinks();
                break;

            case 'wikistats':
                return $this->fetchWikistats();
                break;

            default:
                $output->writeln(sprintf(
                    '<error>invalid action: %s</error>',
                    $input->getArgument('action')
                ));
                return 1;
        }
    }

    protected function findWikilinks($sparqlClient, $entityId)
    {
        $query = <<<EOT
            PREFIX wd: <http://www.wikidata.org/entity/>
            #
            SELECT DISTINCT ?wd (group_concat(DISTINCT ?sitelink;separator="|") as ?sitelinks)
            WHERE {
                BIND(wd:{$entityId} AS ?wd)

                  VALUES ( ?language ) {
                    ( 'en' )
                    ( 'fr' )
                    ( 'de' )
                }

                # get site links (only from ?language wikipedia sites)
                ?sitelink schema:about ?wd ;
                        schema:inLanguage ?language .
                FILTER (contains(str(?sitelink), 'wikipedia'))
            } GROUP BY ?wd
            EOT;
        $result = $sparqlClient->query($query);

        foreach ($result as $row) {
            $sitelinks = (string) $row->sitelinks;
            if (empty($sitelinks)) {
                continue;
            }

            $ret = [];
            foreach (explode('|', $sitelinks) as $link) {
                $parts = parse_url($link);
                if (preg_match('/^(.+)\.wikipedia\.org$/', $parts['host'], $matches)) {
                    $lang = $matches[1];
                    $article = preg_replace('~^/wiki/~', '', $parts['path']);

                    $ret[$lang] = $article;
                }
            }

            return $ret;
        }
    }

    protected function fetchWikilinks($update = false): int
    {
        $sparqlClient = new \EasyRdf\Sparql\Client('https://query.wikidata.org/sparql');

        $personRepository = $this->em->getRepository('AppBundle\Entity\Person');

        $criteria = new \Doctrine\Common\Collections\Criteria();
        $criteria->where($criteria->expr()->neq(
            'wikidata',
            null
        ))
        ->andWhere($criteria->expr()->neq('status', -1))
        // ->andWhere($criteria->expr()->eq('ulan', '500004793'))
        ;

        // $criteria->setMaxResults(5); // testing

        $persons = $personRepository->matching($criteria);
        foreach ($persons as $person) {
            $persist = false;

            $additional = $person->getAdditional();
            if (is_null($additional)) {
                $additional = [];
            }

            if (array_key_exists('wikilinks', $additional) && !$update) {
                continue;
            }

            $additional['wikilinks'] = $this->findWikilinks($sparqlClient, $person->getWikidata());
            $person->setAdditional($additional);
            $persist = true;

            if ($persist) {
                $this->em->persist($person);
                $this->em->flush();
            }
        }

        return 0;
    }

    protected function fetchWikistats($update = false): int
    {
        $sparqlClient = new \EasyRdf\Sparql\Client('https://query.wikidata.org/sparql');

        $personRepository = $this->em->getRepository('AppBundle\Entity\Person');

        $criteria = new \Doctrine\Common\Collections\Criteria();
        $criteria->where($criteria->expr()->neq(
            'wikidata',
            null
        ))
            ->andWhere($criteria->expr()->neq('status', -1))
            // ->andWhere($criteria->expr()->eq('ulan', '500004793'))
        ;

        // $criteria->setMaxResults(5); // testing

        $persons = $personRepository->matching($criteria);
        foreach ($persons as $person) {
            $persist = false;

            $additional = $person->getAdditional();
            if (is_null($additional)
                || !array_key_exists('wikilinks', $additional)
                || !is_array($additional['wikilinks'])) {
                continue;
            }

            foreach ($additional['wikilinks'] as $lang => $article) {
                if (array_key_exists('wikistats', $additional)
                    && array_key_exists($lang, $additional['wikistats'])
                    && !$update) {
                    continue;
                };

                // monthly for the last year
                // https://wikimedia.org/api/rest_v1/metrics/pageviews/per-article/en.wikipedia.org/all-access/user/Bernard_Adeney/monthly/2016010100/2016123100
                $year = date('Y') - 1;
                $url = sprintf(
                    'https://wikimedia.org/api/rest_v1/metrics/pageviews/per-article/%s.wikipedia.org/all-access/user/%s/monthly/%d010100/%d123100',
                    $lang,
                    $article,
                    $year,
                    $year
                );
                try {
                    $stats = json_decode(file_get_contents($url), true);
                }
                catch (\Exception $e) {
                    $stats = false;
                }

                if (false !== $stats && isset($stats['items'])) {
                    $latest_sum = 0;
                    foreach ($stats['items'] as $info) {
                        $latest_sum += $info['views'];
                    }
                    $stats['total'] = $latest_sum;

                    if (!array_key_exists('wikistats', $additional)) {
                        $additional['wikistats'] = [];
                    }
                    $additional['wikistats'][$lang] = $stats['total'];
                    $person->setAdditional($additional);
                    echo $article . "\t" . $lang . "\t" . $latest_sum . "\n";
                    $persist = true;
                }
                else if (array_key_exists('wikistats', $additional)) {
                    unset($additional['wikistats'][$lang]);
                }
            }

            if ($persist) {
                $this->em->persist($person);
                $this->em->flush();
            }
        }

        return 0;
    }
}
