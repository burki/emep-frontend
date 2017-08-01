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
     * @Route("/work/by-style", name="item-by-style")
     */
    public function indexAction(Request $request)
    {
        $route = $request->get('_route');

        $qb = $this->getDoctrine()
                ->getManager()
                ->createQueryBuilder();

        $qbPerson = $this->getDoctrine()
                ->getManager()
                ->createQueryBuilder();
        $qbPerson->select('P')
            ->distinct()
            ->from('AppBundle:Person', 'P')
            ->innerJoin('AppBundle:ItemPerson', 'IP', 'WITH', 'IP.person=P')
            ->innerJoin('AppBundle:Item', 'I', 'WITH', 'IP.item=I')
            ->where('I.status <> -1')
            ->orderBy('P.familyName');


        $templateAppend = '';
        if ('item-by-exhibition' == $route) {
            $templateAppend = '-by-exhibition';
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

            $qbPerson
                ->innerJoin('I.exhibitions', 'E');
        }
        else if ('item-by-style' == $route) {
            $templateAppend = '-by-style';
            $qb->select([
                    'I',
                    'T.name HIDDEN styleSort',
                    "COALESCE(I.earliestdate, I.creatordate) HIDDEN dateSort",
                    "P.familyName HIDDEN nameSort",
                    "I.catalogueId HIDDEN catSort",
                ])
                ->from('AppBundle:Item', 'I')
                ->leftJoin('I.creators', 'P')
                ->innerJoin('I.style', 'T')
                ->where('I.status <> -1 AND P.status <> -1')
                ->orderBy('styleSort DESC, dateSort, nameSort, catSort')
                ;
            $qbPerson
                ->innerJoin('I.style', 'T');
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
            unset($qbPerson);
        }

        $persons = null;
        if (isset($qbPerson)) {
            $person = $request->get('person');
            if (!empty($person) && intval($person) > 0) {
                $qb->andWhere(sprintf('P.id=%d', intval($person)));
            }
            $persons = $qbPerson->getQuery()->getResult();
        }

        $results = $qb->getQuery()
            // ->setMaxResults(10) // for testing
            ->getResult();

        return $this->render('Item/index'
                             . $templateAppend
                             . '.html.twig', [
            'pageTitle' => $this->get('translator')->trans('Works'),
            'results' => $results,
            'persons' => $persons,
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
