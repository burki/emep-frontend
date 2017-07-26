<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

/**
 *
 */
class ExhibitionController extends Controller
{
    use SharingBuilderTrait;

    /**
     * @Route("/exhibition", name="exhibition-index")
     */
    public function indexAction(Request $request)
    {
        $route = $request->get('_route');

        $qb = $this->getDoctrine()
                ->getManager()
                ->createQueryBuilder();

        $qb->select([
                'E',
                "E.startdate HIDDEN dateSort"
            ])
            ->from('AppBundle:Exhibition', 'E')
            ->where('E.status <> -1')
            ->orderBy('dateSort')
            ;

        $exhibitions = $qb->getQuery()->getResult();

        return $this->render('Exhibition/index.html.twig', [
            'pageTitle' => $this->get('translator')->trans('Exhibitions'),
            'exhibitions' => $exhibitions,
        ]);
    }

    /**
     * @Route("/exhibition/{id}", requirements={"id" = "\d+"}, name="exhibition")
     */
    public function detailAction(Request $request, $id = null, $ulan = null, $gnd = null)
    {
        $routeName = $request->get('_route'); $routeParams = [];

        $repo = $this->getDoctrine()
                ->getRepository('AppBundle:Exhibition');

        if (!empty($id)) {
            $routeParams = [ 'id' => $id ];
            $exhibition = $repo->findOneById($id);
        }

        if (!isset($exhibition) || $exhibition->getStatus() == -1) {
            return $this->redirectToRoute('exhibition-index');
        }

        $locale = $request->getLocale();
        if (in_array($request->get('_route'), [ 'exhibition-jsonld' ])) {
            return new JsonLdResponse($person->jsonLdSerialize($locale));
        }

        return $this->render('Exhibition/detail.html.twig', [
            'pageTitle' => $exhibition->title, // TODO: dates in brackets
            'exhibition' => $exhibition,
            'pageMeta' => [
                /*
                'jsonLd' => $exhibition->jsonLdSerialize($locale),
                'og' => $this->buildOg($exhibition, $routeName, $routeParams),
                'twitter' => $this->buildTwitter($exhibition, $routeName, $routeParams),
                */
            ],
        ]);
    }
}
