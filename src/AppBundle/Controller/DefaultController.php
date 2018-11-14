<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

use AppBundle\Utils\SearchListPagination;

/**
 *
 */
class DefaultController
extends Controller
{
    /**
     * @Route("/", name="home")
     * @Route("/data", name="data")
     */

    public function indexAction(Request $request, UrlGeneratorInterface $urlGenerator)
    {
        $connection = $this->getDoctrine()->getEntityManager()->getConnection();

        $counts = [];
        foreach ([ 'ItemExhibition', 'Exhibition', 'Venue', 'Organizer', 'Person' ] as $entity) {
            switch ($entity) {
                case 'ItemExhibition':
                    $listBuilder = new \AppBundle\Utils\ItemExhibitionListBuilder($connection, $request, $urlGenerator, []);
                    break;

                case 'Exhibition':
                    $listBuilder = new \AppBundle\Utils\ExhibitionListBuilder($connection, $request, $urlGenerator, []);
                    break;

                case 'Venue':
                    $listBuilder = new \AppBundle\Utils\VenueListBuilder($connection, $request, $urlGenerator, []);
                    break;

                case 'Organizer':
                    $listBuilder = new \AppBundle\Utils\OrganizerListBuilder($connection, $request, $urlGenerator, []);
                    break;

                case 'Person':
                    $listBuilder = new \AppBundle\Utils\PersonListBuilder($connection, $request, $urlGenerator, []);
                    break;
            }

            $listPagination = new SearchListPagination($listBuilder);

            $counts[$entity] = $listPagination->getTotal();
        }

        return $this->render('Default/index.html.twig', [
            'counts' => $counts,
        ]);
    }

    /**
     * @Route("/project", name="project")
     */

    public function infoAction()
    {
        return $this->render('Default/project.html.twig');
    }

    /**
     * @Route("/using", name="using")
     */

    public function usingAction()
    {
        return $this->render('Default/using.html.twig');
    }

    /**
     * @Route("/cooperating-institutions", name="cooperating")
     */

    public function cooperatingAction()
    {
        return $this->render('Default/cooperating_institutions.html.twig');
    }

    /**
     * @Route("/rulerz", name="rulerz")
     */
    public function rulerzAction()
    {
        $compiler =  \RulerZ\Compiler\Compiler::create();

        $rulerz = new \RulerZ\RulerZ(
            $compiler, [
               new \RulerZ\Target\DoctrineORM\DoctrineORM(), // this line is Doctrine-specific
                // other compilation targets...
            ]
        );

        $entityManager = $this->getDoctrine()->getEntityManager();

        $exhibitionQueryBuilder = $entityManager
            ->createQueryBuilder()
            ->select('e')
            ->from('AppBundle\Entity\Exhibition', 'e')
            ->orderBy('e.id');

        $rule = 'not(type in [ "solo"]) and startdate >= "1915-00-00" and startdate < "1915-03-99" and status != "-1"';

        $parameters = [ /* 'title' => '1912 Cologne Sonderbund' */ ];

        foreach ($rulerz->filter($exhibitionQueryBuilder, $rule, $parameters) as $result) {
            echo $result->id . ': ' . $result->title . '<br />';
        }
        exit;
    }
}
