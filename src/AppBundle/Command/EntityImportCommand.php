<?php

// src/AppBundle/Command/EntityEnhanceCommand.php
namespace AppBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Intl\Countries;

use Doctrine\ORM\EntityManagerInterface;

class EntityImportCommand
extends Command
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
            ->setName('entity:import')
            ->setDescription('Import Country Entities')
            ->addArgument(
                'type',
                InputArgument::REQUIRED,
                'which entities do you want to import (country)'
            )
            ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
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
        $qb = $this->em->createQueryBuilder();

        $qb->select([
                'P.countryCode',
                'C.countryCode AS ignore',
            ])
            ->distinct()
            ->from('AppBundle\Entity\Place', 'P')
            ->leftJoin('P.country', 'C')
            ->where("P.type IN ('inhabited places') AND P.countryCode IS NOT NULL")
            ->having('C.countryCode IS NULL')
            ;

        $flush = false;
        foreach ($qb->getQuery()->getResult() as $result) {
            $country = new \AppBundle\Entity\Country();
            $country->setCountryCode($result['countryCode']);
            $country->setName(Countries::getName($result['countryCode'], 'en'));
            $this->em->persist($country);
            $flush = true;
        }

        if ($flush) {
            $this->em->flush();
        }

        return 0;
    }
}
