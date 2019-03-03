<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Annotation\Route;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use AppBundle\Utils\SearchListPagination;

/**
 *
 */
class DefaultController
extends Controller
{
    /**
     * @Route("/", name="home")
     */
    public function indexAction(Request $request, UrlGeneratorInterface $urlGenerator)
    {
        $connection = $this->getDoctrine()->getEntityManager()->getConnection();

        $counts = [];
        foreach ([ 'ItemExhibition' , 'Exhibition', 'Venue', 'Organizer', 'Person' ] as $entity) {
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
}
