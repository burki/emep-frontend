<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\User\UserInterface;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Pagerfanta\Pagerfanta;

use AppBundle\Utils\CsvResponse;
use AppBundle\Utils\SearchListBuilder;
use AppBundle\Utils\SearchListPagination;
use AppBundle\Utils\SearchListAdapter;


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

    protected function buildFilterForm()
    {
        $this->form = $this->createForm(\AppBundle\Filter\HolderFilterType::class, [
            'choices' => [
                'country' => array_flip($this->buildCountries()),
            ],
        ]);
    }

    /**
     * @Route("/holder", name="holder-index")
     */
    public function indexAction(Request $request,
                                UrlGeneratorInterface $urlGenerator,
                                UserInterface $user = null)
    {
        $response = $this->handleUserAction($request, $user);
        if (!is_null($response)) {
            return $response;
        }

        $this->buildFilterForm();

        $listBuilder = $this->instantiateListBuilder($request, $urlGenerator, false, 'Holder');

        $listPagination = new SearchListPagination($listBuilder);

        $page = $request->get('page', 1);
        $listPage = $listPagination->get($this->pageSize, ($page - 1) * $this->pageSize);

        $adapter = new SearchListAdapter($listPage);
        $pager = new Pagerfanta($adapter);
        $pager->setMaxPerPage($listPage['limit']);
        $pager->setCurrentPage(intval($listPage['offset'] / $listPage['limit']) + 1);

        return $this->render('Holder/index.html.twig', [
            'pageTitle' => $this->get('translator')->trans('Holding Institutions'),
            'pager' => $pager,

            'listBuilder' => $listBuilder,
            'form' => $this->form->createView(),
            'searches' => $this->lookupSearches($user, $request->get('_route')),
        ]);
    }

    protected function buildSaveSearchParams(Request $request, UrlGeneratorInterface $urlGenerator)
    {
        $route = str_replace('-save', '-index', $request->get('_route'));

        $this->buildFilterForm();

        $listBuilder = $this->instantiateListBuilder($request, $urlGenerator, false, 'Holder');
        $filters = $listBuilder->getQueryFilters();
        if (empty($filters)) {
            return [ $route, [] ];
        }

        $routeParams = [
            'filter' => $filters,
        ];

        return [ $route, $routeParams ];
    }

    /**
     * @Route("/holder/save", name="holder-save")
     */
    public function saveSearchAction(Request $request,
                                     UrlGeneratorInterface $urlGenerator,
                                     UserInterface $user)
    {
        return $this->handleSaveSearchAction($request, $urlGenerator, $user);
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
        if (in_array($request->get('_route'), [ 'holder-jsonld' ])) {
            return new JsonLdResponse($holder->jsonLdSerialize($locale));
        }

        $citeProc = $this->instantiateCiteProc($request->getLocale());

        $qb = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();

        $qb->select([
                'H',
                'H.placeLabel',
                'P.latitude',
                'P.longitude'
            ])
            ->from('AppBundle:Holder', 'H')
            ->leftJoin('AppBundle:Place', 'P',
                       \Doctrine\ORM\Query\Expr\Join::WITH,
                       'P.name = H.placeLabel')
            ->where('H.id = ' . $id)
            ;

        $bibitems = $holder->findBibitems($this->getDoctrine()->getManager(), true);

        $holderPlace = $qb->getQuery()->execute();

        return $this->render('Holder/detail.html.twig', [
            'place' => $holderPlace[0],
            'pageTitle' => $holder->getName(),
            'holder' => $holder,
            'bibitems' => $bibitems,
            'citeProc' => $citeProc,
            'pageMeta' => [
                /*
                'jsonLd' => $holder->jsonLdSerialize($locale),
                'og' => $this->buildOg($holder, $routeName, $routeParams),
                'twitter' => $this->buildTwitter($holder, $routeName, $routeParams),
                */
            ],
        ]);
    }

    /**
     * @Route("/holder/{id}/catalogues/csv", requirements={"id" = "\d+"}, name="holder-catalogue-csv")
     */
    public function detailActionCatalogueCsv(Request $request, $id = null, $ulan = null, $gnd = null)
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

        $result = $holder->findBibitems($this->getDoctrine()->getManager(), true);

        $csvResult = [];

        foreach ($result as $key => $value) {
            $bibitem = $value[0];

            $innerArray = [];

            $year = '';
            $datePublished = $bibitem->getDatePublished();
            if (!is_null($datePublished) && preg_match('/^(\d{4})/', $datePublished, $matches)) {
                $year = $matches[1];
            }

            $publisher = $bibitem->getPublisher();
            if (!is_null($publisher)) {
                $publisher = $publisher->getName();
            }

            array_push($innerArray,
                       $bibitem->getTitle(),
                       $bibitem->getPublicationLocation(),
                       $publisher,
                       $year);

            array_push($csvResult, $innerArray);
        }

        return new CsvResponse($csvResult, 200, [
                'Title', 'Place of Publication', 'Publisher', 'Year of Publication',
            ], 'catalogues.xlsx');
    }
}
