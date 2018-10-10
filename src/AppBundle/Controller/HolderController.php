<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

/**
 *
 */
class HolderController
extends CrudController
{
    use SharingBuilderTrait;

    protected function buildCountries()
    {
        $qb = $this->getDoctrine()
                ->getManager()
                ->createQueryBuilder();

        $qb->select([
                'H.countryCode',
            ])
            ->distinct()
            ->from('AppBundle:Holder', 'H')
            ->where('H.status <> -1 AND H.countryCode IS NOT NULL')
            ;

        return $this->buildActiveCountries($qb);
    }

    /**
     * @Route("/holder", name="holder-index")
     */
    public function indexAction(Request $request)
    {
        $route = $request->get('_route');

        $qb = $this->getDoctrine()
                ->getManager()
                ->createQueryBuilder();

        $qb->select([
                'H',
                "CONCAT(COALESCE(H.countryCode, ''),H.placeLabel,H.name) HIDDEN countryPlaceNameSort",
                "H.name HIDDEN nameSort",
                'COUNT(DISTINCT BH.bibitem) AS numBibitemSort',
            ])
            ->from('AppBundle:Holder', 'H')
            ->leftJoin('AppBundle:BibitemHolder', 'BH',
                       \Doctrine\ORM\Query\Expr\Join::WITH,
                       'BH.holder = H')
            ->where('H.status <> -1')
            ->groupBy('H.id')
            ->orderBy('nameSort')
            ;

        $form = $this->get('form.factory')->create(\AppBundle\Filter\HolderFilterType::class, [
            'country_choices' => array_flip($this->buildCountries()),
        ]);

        if ($request->query->has($form->getName())) {
            // manually bind values from the request
            $form->submit($request->query->get($form->getName()));

            // build the query from the given form object
            $this->get('lexik_form_filter.query_builder_updater')->addFilterConditions($form, $qb);
        }

        $pagination = $this->buildPagination($request, $qb->getQuery(), [
            // the following leads to wrong display in combination with our
            // helper.pagination_sortable()
            // 'defaultSortFieldName' => 'countryPlaceNameSort', 'defaultSortDirection' => 'asc',
        ]);

        $countries = $form->get('country')->getData();
        $stringQuery = $form->get('search')->getData();


        // $holders = $qb->getQuery()->getResult();

        return $this->render('Holder/index.html.twig', [
            'pageTitle' => $this->get('translator')->trans('Holding Institutions'),
            'pagination' => $pagination,
            'countryArray' => $this->buildCountries(),
            'countries' => $countries,
            'stringPart' => $stringQuery,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/holder/{id}", requirements={"id" = "\d+"}, name="holder")
     */
    public function detailAction(Request $request, $id = null, $ulan = null, $gnd = null)
    {
        $routeName = $request->get('_route'); $routeParams = [];

        $repo = $this->getDoctrine()
                ->getRepository('AppBundle:Holder');

        if (!empty($id)) {
            $routeParams = [ 'id' => $id ];
            $holder = $repo->findOneById($id);
        }

        if (!isset($holder) || $holder->getStatus() == -1) {
            return $this->redirectToRoute('holder-index');
        }

        $locale = $request->getLocale();
        if (in_array($request->get('_route'), [ 'location-jsonld' ])) {
            return new JsonLdResponse($person->jsonLdSerialize($locale));
        }

        $citeProc = $this->instantiateCiteProc($request->getLocale());

        return $this->render('Holder/detail.html.twig', [
            'pageTitle' => $holder->getName(),
            'holder' => $holder,
            'bibitems' => $holder->findBibitems($this->getDoctrine()->getManager()),
            'citeProc' => $citeProc,
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
