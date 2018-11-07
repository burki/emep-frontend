<?php

// src/AppBundle/Command/ExportGdfCommand.php
namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

class ExportGdfCommand
extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('export:gdf')
            ->setDescription('Export GDF')
            ->addArgument(
                'type',
                InputArgument::REQUIRED,
                'what you want to export (person or location)'
            )
            ->addOption(
                'save-export-path',
                false,
                InputOption::VALUE_OPTIONAL,
                'Write output to app.export.path/type.gdf'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $saveExportPath = $input->getOption('save-export-path');
        if ($saveExportPath !== false) {
            $container = $this->getContainer();
            $exportPath = $container->hasParameter('app.export.path')
                ? $container->getParameter('app.export.path')
                : $container->get('kernel')->getProjectDir() . '/../site/htdocs/uploads/export';

            if (!file_exists($exportPath)) {
                $output->writeln(sprintf('<error>app.export.path: %s does not exist</error>',
                                         $exportPath));
                return 1;
            }

            $type = $input->getArgument('type');
            if (!in_array($type, [ 'person', 'location' ])) {
                $output->writeln(sprintf('<error>invalid type: %s</error>',
                                         $input->getArgument('type')));
                return 1;
            }

            $fnameLock = realpath($exportPath) . DIRECTORY_SEPARATOR . $type . '.lock';
            if (file_exists($fnameLock)) {
                $output->writeln(sprintf('<error>Execution is alread in progress (%s)</error>',
                                         $fnameLock));
                return 2;
            }

            touch($fnameLock);

            $fnameFull = realpath($exportPath) . DIRECTORY_SEPARATOR . $type . '.gdf';

            $output = new \Symfony\Component\Console\Output\StreamOutput($fp = fopen($fnameFull, 'w', false));
        }

        switch ($input->getArgument('type')) {
            case 'person':
                $res = $this->exportPersonGdf($output);
                break;

            case 'location':
                $res = $this->exportLocationGdf();
                break;

            default:
                $output->writeln(sprintf('<error>invalid type: %s</error>',
                                         $input->getArgument('type')));
                return 1;
        }

        if ($saveExportPath !== false) {
            fclose($fp);
            unlink($fnameLock);
        }

        return $res;
    }

    private function setEdges(&$edges, $shared_ids, $weighted = false)
    {
        $count_shared_ids = count($shared_ids);
        for ($i = 0; $i < $count_shared_ids - 1; $i++) {
            $src_id = $shared_ids[$i];
            for ($j = $i + 1; $j < $count_shared_ids; $j++) {
                $target_id = $shared_ids[$j];
                $src_target = $src_id < $target_id
                    ? array($src_id, $target_id)
                    : array($target_id, $src_id);
                $edge_key = join(',', $src_target);
                if (!array_key_exists($edge_key, $edges)) {
                    $edges[$edge_key] = 0;
                }
                if ($weighted) {
                    $edges[$edge_key] += 1.0 / ($count_shared_ids - 1);
                }
                else {
                    $edges[$edge_key] += 1;
                }
            }
        }
    }

    protected function exportPersonGdf($output)
    {
        $em = $this->getContainer()->get('doctrine')->getManager();
        $qb = $em->createQueryBuilder();

        $qb->select([
                'P',
                'COUNT(DISTINCT E.id) AS numExhibitionSort',
            ])
            ->from('AppBundle:Person', 'P')
            ->leftJoin('P.exhibitions', 'E')
            ->where('P.status <> -1 AND E.status <> -1')
            ->groupBy('P.id') // for Count
            ->having('numExhibitionSort >= 2')
            // ->orderBy('numExhibitionSort', 'DESC')
            // ->setMaxResults(5)
            ;

        $query = $qb->getQuery();
        $results = $query->getResult();
        $nodes = $edges = [];
        foreach ($results as $result) {
            $person = $result[0];
            $nodes[$person->getId()] = $person->getFullname(true);
        }

        $output->writeln('nodedef>name VARCHAR,label VARCHAR');
        $data = [];
        foreach ($nodes as $nodeId => $nodeLabel) {
            // echo implode(',', [ $nodeId, $nodeLabel ]), "\n";
            $data[] = [ $nodeId, $nodeLabel ];
        }

        $fp = fopen('php://temp', 'w+');
        foreach ($data as $fields) {
            // Add row to CSV buffer
            fputcsv($fp, $fields);
        }
        rewind($fp); // Set the pointer back to the start
        $output->writeln(stream_get_contents($fp)); // Fetch the contents of our CSV
        fclose($fp); // Close our pointer and free up memory and /tmp space

        $qb = $em->createQueryBuilder();
        $qb->select('E.id as exhibitionId', 'P.id as personId')
            ->distinct()
            ->from('AppBundle:ItemExhibition', 'IE')
            ->join('AppBundle:Exhibition', 'E',
                   \Doctrine\ORM\Query\Expr\Join::WITH,
                   'E = IE.exhibition')
            ->join('AppBundle:Person', 'P',
                   \Doctrine\ORM\Query\Expr\Join::WITH,
                   'P = IE.person')
            ->orderBy('exhibitionId', 'ASC');

        $results = $qb->getQuery()->getResult();
        $lastExhibitionId = -1;
        $persons = [];
        foreach ($results as $result) {
            if ($lastExhibitionId != $result['exhibitionId']) {
                if (count($persons) > 1) {
                    $this->setEdges($edges, $persons);
                }
                $persons = [];
                $lastExhibitionId = $result['exhibitionId'];
                fwrite(STDERR, 'ID: ' . $lastExhibitionId . "\n");
                flush();
            }
            $personId = $result['personId'];
            if (array_key_exists($personId, $nodes)) {
                // var_dump($result);
                $persons[] = $personId;
            }
        }

        if (count($persons) > 1) {
            $this->setEdges($edges, $persons);
        }

        $output->writeln('edgedef>node1 VARCHAR,node2 VARCHAR, weight DOUBLE');
        foreach ($edges as $edgeKey => $edgeCount) {
            $output->writeln(join(',', [ $edgeKey, $edgeCount ]));
        }
    }

    protected function exportLocationGdf()
    {
        $em = $this->getContainer()->get('doctrine')->getManager();
        $qb = $em->createQueryBuilder();

        $qb->select([
                'L',
                'COUNT(DISTINCT E.id) AS numExhibitionSort',
                'COUNT(DISTINCT IE.id) AS numCatEntrySort',
            ])
            ->from('AppBundle:Location', 'L')
            ->leftJoin('L.place', 'P')
            ->leftJoin('P.country', 'C')
            ->innerJoin('AppBundle:Exhibition', 'E',
                       \Doctrine\ORM\Query\Expr\Join::WITH,
                       'E.location = L AND E.status <> -1')
            ->innerJoin('AppBundle:ItemExhibition', 'IE',
                       \Doctrine\ORM\Query\Expr\Join::WITH,
                       'IE.exhibition = E AND IE.title IS NOT NULL')
            ->where('L.status <> -1')
            ->groupBy('L.id')
            ->having('numExhibitionSort >= 1')
            ->orderBy('numExhibitionSort', 'DESC')
            // ->setMaxResults(30)
            ;

        $query = $qb->getQuery();
        $results = $query->getResult();
        $nodes = $edges = [];
        foreach ($results as $result) {
            $location = $result[0];
            $name = preg_replace('/,/', ' ', $location->getName());
            if (preg_match('/exact location unknown/', $name)) {
                continue;
            }
            $nodes[$location->getId()] = $name;
        }

        echo 'nodedef>name VARCHAR,label VARCHAR' . "\n";
        foreach ($nodes as $nodeId => $nodeLabel) {
            echo implode(',', [ $nodeId, $nodeLabel ]), "\n";
        }

        $qb = $em->createQueryBuilder();
        $qb->select('L.id as locationId', 'P.id as personId')
            ->distinct()
            ->from('AppBundle:ItemExhibition', 'IE')
            ->join('AppBundle:Exhibition', 'E',
                   \Doctrine\ORM\Query\Expr\Join::WITH,
                   'E = IE.exhibition')
            ->join('E.location', 'L')
            ->join('AppBundle:Person', 'P',
                   \Doctrine\ORM\Query\Expr\Join::WITH,
                   'P = IE.person')
            ->orderBy('personId', 'ASC');

        $results = $qb->getQuery()->getResult();
        $lastPersonId = -1;
        $locations = [];
        foreach ($results as $result) {
            if ($lastPersonId != $result['personId']) {
                if (count($locations) > 1) {
                    $this->setEdges($edges, $locations);
                }
                $locations = [];
                $lastPersonId = $result['personId'];
                fwrite(STDERR, 'ID: ' . $lastPersonId . "\n");
                flush();
            }
            $locationId = $result['locationId'];
            if (array_key_exists($locationId, $nodes)) {
                // var_dump($result);
                $locations[] = $locationId;
            }
        }

        if (count($locations) > 1) {
            $this->setEdges($edges, $locations);
        }

        echo 'edgedef>node1 VARCHAR,node2 VARCHAR, weight DOUBLE' . "\n";
        foreach ($edges as $edgeKey => $edgeCount) {
            echo join(',', [ $edgeKey, $edgeCount ]), "\n";
        }
    }
}
