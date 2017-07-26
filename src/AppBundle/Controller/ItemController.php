<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

/**
 *
 */
class ItemController extends Controller
{
    use SharingBuilderTrait;

    /**
     * @Route("/work", name="item-index")
     * @Route("/work/by-exhibition", name="item-by-exhibition")
     */
    public function indexAction(Request $request)
    {
        $route = $request->get('_route');

        $qb = $this->getDoctrine()
                ->getManager()
                ->createQueryBuilder();

        if ('item-by-exhibition' == $route) {
            $qb->select([
                    'E',
                    "P.familyName HIDDEN nameSort",
                    "COALESCE(I.earliestdate, I.creatordate) HIDDEN dateSort",
                    "I.catalogueId HIDDEN catSort",
                ])
                ->from('AppBundle:Exhibition', 'E')
                ->innerJoin('E.items', 'I')
                ->leftJoin('I.creators', 'P')
                ->leftJoin('I.media', 'M')
                ->where('I.status <> -1')
                ->orderBy('E.startdate, E.id')
                // , nameSort, dateSort, catSort')
                ;
        }
        else {
            $qb->select([
                    'I',
                    "P.familyName HIDDEN nameSort",
                    "COALESCE(I.earliestdate, I.creatordate) HIDDEN dateSort",
                    "I.catalogueId HIDDEN catSort",
                ])
                ->from('AppBundle:Item', 'I')
                ->leftJoin('I.creators', 'P')
                ->where('I.status <> -1 AND P.status <> -1')
                ->orderBy('nameSort, dateSort, catSort')
                ;
        }

        $results = $qb->getQuery()
            // ->setMaxResults(10) // for testing
            ->getResult();

        return $this->render('Item/index'
                             . ('item-by-exhibition' == $route ? '-by-exhibition' : '')
                             . '.html.twig', [
            'pageTitle' => $this->get('translator')->trans('Works'),
            'results' => $results,
        ]);
    }

    /**
     * @Route("/work/{id}", requirements={"id" = "\d+"}, name="item")
     */
    public function detailAction(Request $request, $id = null)
    {
        $routeName = $request->get('_route'); $routeParams = [];

        $repo = $this->getDoctrine()
                ->getRepository('AppBundle:Item');

        if (!empty($id)) {
            $routeParams = [ 'id' => $id ];
            $item = $repo->findOneById($id);
        }

        if (!isset($item) || $item->getStatus() == -1) {
            return $this->redirectToRoute('item-index');
        }

        $locale = $request->getLocale();
        if (in_array($request->get('_route'), [ 'item-jsonld' ])) {
            return new JsonLdResponse($person->jsonLdSerialize($locale));
        }

        return $this->render('Item/detail.html.twig', [
            'pageTitle' => $item->title, // TODO: dates in brackets
            'item' => $item,
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
