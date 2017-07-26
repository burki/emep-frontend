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
     */
    public function indexAction()
    {
        return $this->render('Default/index.html.twig');
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
