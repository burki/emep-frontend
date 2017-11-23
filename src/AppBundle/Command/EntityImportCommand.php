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

class EntityImportCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('entity:import')
            ->setDescription('Import Country Entities')
            ->addArgument(
                'type',
                InputArgument::REQUIRED,
                'which entities do you want to import (country)'
            )
            ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        switch ($input->getArgument('type')) {
            case 'country':
                return $this->importCountry();
                break;

            default:
                $output->writeln(sprintf('<error>invalid type: %s</error>',
                                         $input->getArgument('type')));
                return 1;
        }
    }

    protected function importCountry()
    {
        $em = $this->getContainer()->get('doctrine')->getManager();
        $qb = $em->createQueryBuilder();

        $qb->select([
                'P.countryCode',
                'C.countryCode AS ignore',
            ])
            ->distinct()
            ->from('AppBundle:Place', 'P')
            ->leftJoin('P.country', 'C')
            ->where("P.type IN ('inhabited places') AND P.countryCode IS NOT NULL")
            ->having('C.countryCode IS NULL')
            ;

        $flush = false;
        foreach ($qb->getQuery()->getResult() as $result) {
            $country = new \AppBundle\Entity\Country();
            $country->setCountryCode($result['countryCode']);
            $country->setName(\Symfony\Component\Intl\Intl::getRegionBundle()->getCountryName($result['countryCode'], 'en'));
            $em->persist($country);
            $flush = true;
        }

        if ($flush) {
            $em->flush();
        }
    }
}
