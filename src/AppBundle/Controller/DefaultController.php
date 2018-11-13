<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

/**
 *
 */
class DefaultController extends Controller
{
    /**
     * @Route("/", name="home")
     * @Route("/data", name="data")
     */

    public function indexAction()
    {

        $em = $this->getDoctrine()->getEntityManager();
        $dbconn = $em->getConnection();

        $numberOfExhibitionStr = "SELECT Count(*) as numberOfExhibitions FROM Exhibition";
        $numberOfArtistStr = "SELECT Count(*) as numberOfArtist FROM Person";

        // TODO VENUES AND ORG BODIES

        $stmtExhibition = $dbconn->query($numberOfExhibitionStr);
        $numberOfExhibition = $stmtExhibition->fetch()['numberOfExhibitions'];

        $stmtArtist = $dbconn->query($numberOfArtistStr);
        $numberOfArtist = $stmtArtist->fetch()['numberOfArtist'];



        return $this->render('Default/index.html.twig', [
            'numberOfExhibitions' => $numberOfExhibition,
            'numberOfArtist' => $numberOfArtist
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
