<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

/**
 *
 */
class LocationController extends Controller
{
    use SharingBuilderTrait;

    /**
     * @Route("/location", name="location-index")
     */
    public function indexAction(Request $request)
    {
        $route = $request->get('_route');

        $qb = $this->getDoctrine()
                ->getManager()
                ->createQueryBuilder();

        $qb->select([
                'L',
                "COALESCE(P.alternateName,P.name) HIDDEN nameSort",
            ])
            ->from('AppBundle:Location', 'L')
            // ->leftJoin('L.exhibitions', 'E')
            ->leftJoin('L.place', 'P')
            ->where('L.status <> -1')
            ->orderBy('P.countryCode, nameSort, L.name')
            ;

        $locations = $qb->getQuery()->getResult();

        return $this->render('Location/index.html.twig', [
            'pageTitle' => $this->get('translator')->trans('Venues'),
            'locations' => $locations,
        ]);
    }

    /**
     * @Route("/location/{id}", requirements={"id" = "\d+"}, name="location")
     */
    public function detailAction(Request $request, $id = null, $ulan = null, $gnd = null)
    {
        $routeName = $request->get('_route'); $routeParams = [];

        $repo = $this->getDoctrine()
                ->getRepository('AppBundle:Location');

        if (!empty($id)) {
            $routeParams = [ 'id' => $id ];
            $location = $repo->findOneById($id);
        }

        if (!isset($location) || $location->getStatus() == -1) {
            return $this->redirectToRoute('location-index');
        }

        $locale = $request->getLocale();
        if (in_array($request->get('_route'), [ 'location-jsonld' ])) {
            return new JsonLdResponse($person->jsonLdSerialize($locale));
        }

        return $this->render('Location/detail.html.twig', [
            'pageTitle' => $location->getName(),
            'location' => $location,
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
