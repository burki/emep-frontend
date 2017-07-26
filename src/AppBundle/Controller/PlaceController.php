<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

/**
 *
 */
class PlaceController
extends Controller
{
    /**
     * @Route("/place", name="place-index")
     */
    public function indexAction()
    {
        $qb = $this->getDoctrine()
                ->getManager()
                ->createQueryBuilder();

        $qb->select([
                'P',
                "COALESCE(P.alternateName,P.name) HIDDEN nameSort"
            ])
            ->from('AppBundle:Place', 'P')
            ->where("P.type IN ('inhabited places')")
            ->orderBy('nameSort')
            ;

        $places = $qb->getQuery()->getResult();

        return $this->render('Place/index.html.twig', [
            'pageTitle' => $this->get('translator')->trans('Places'),
            'places' => $places,
        ]);
    }

    /**
     * @Route("/place/{id}", requirements={"id" = "\d+"}, name="place")
     * @Route("/place/tgn/{tgn}", requirements={"tgn" = "\d+"}, name="place-by-tgn")
     */
    public function detailAction($id = null, $tgn = null)
    {
        $placeRepo = $this->getDoctrine()
                ->getRepository('AppBundle:Place');

        if (!empty($id)) {
            $place = $placeRepo->findOneById($id);
        }
        else if (!empty($tgn)) {
            $place = $placeRepo->findOneByTgn($tgn);
        }

        if (!isset($place) /* || $place->getStatus() < 0 */) {
            return $this->redirectToRoute('place-index');
        }

        $request = $this->get('request_stack')->getCurrentRequest();
        $locale = $request->getLocale();

        if (in_array($request->get('_route'), [ 'place-jsonld', 'place-by-tgn-jsonld' ])) {
            return new JsonLdResponse($place->jsonLdSerialize($locale));
        }

        return $this->render('Place/detail.html.twig', [
            'pageTitle' => $place->getNameLocalized($locale),
            'place' => $place,
            'em' => $this->getDoctrine()->getManager(),
            'pageMeta' => [
                'jsonLd' => $place->jsonLdSerialize($locale),
            ],
        ]);
    }
}
