<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use Pagerfanta\Pagerfanta;

use AppBundle\Utils\CsvResponse;
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
            ->from('AppBundle\Entity\Holder', 'H')
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
                                TranslatorInterface $translator,
                                ?UserInterface $user = null)
    {
        $settings = $this->lookupSettingsFromRequest($request);

        $response = $this->handleUserAction($request, $user, $settings['base']);
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
            'pageTitle' => $translator->trans('Holding Institutions'),
            'pager' => $pager,

            'listBuilder' => $listBuilder,
            'form' => $this->form->createView(),
            'searches' => $this->lookupSearches($user, $settings['base']),
        ]);
    }

    protected function buildSaveSearchParams(Request $request, UrlGeneratorInterface $urlGenerator)
    {
        $settings = $this->lookupSettingsFromRequest($request);
        $route = $settings['base'];

        $this->buildFilterForm();

        $listBuilder = $this->instantiateListBuilder($request, $urlGenerator, false, 'Holder');
        $filters = $listBuilder->getQueryFilters(true);
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
                                     UserInterface $user,
                                     TranslatorInterface $translator)
    {
        return $this->handleSaveSearchAction($request, $urlGenerator, $user, $translator);
    }

    /**
     * @Route("/holder/{id}.jsonld", requirements={"id" = "\d+"}, name="holder-jsonld")
     * @Route("/holder/{id}", requirements={"id" = "\d+"}, name="holder")
     */
    public function detailAction(Request $request, $id = null, $ulan = null, $gnd = null)
    {
        $routeName = $request->get('_route'); $routeParams = [];

        $repo = $this->getDoctrine()
                ->getRepository('AppBundle\Entity\Holder');

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

        $holder->setDateModified(\AppBundle\Utils\HolderListBuilder::fetchDateModified($this->getDoctrine()->getConnection(), $holder->getId()));

        $placeLabel = $holder->getPlaceLabel();
        $place = null;
        if (!empty($placeLabel)) {
            // currently no relation, so try to look-up
            $qb = $this->getDoctrine()
                ->getManager()
                ->createQueryBuilder();

            $qb->select([
                    'H',
                    'H.placeLabel',
                    'P.latitude',
                    'P.longitude'
                ])
                ->from('AppBundle\Entity\Holder', 'H')
                ->leftJoin('AppBundle\Entity\Place', 'P',
                           \Doctrine\ORM\Query\Expr\Join::WITH,
                           "P.type='inhabited places' AND (P.name = H.placeLabel OR P.alternateName = H.placeLabel)")
                ->where('H.id = ' . $id)
                ;

            $holderPlace = $qb->getQuery()->execute();
            $place = $holderPlace[0];
        }

        return $this->render('Holder/detail.html.twig', [
            'place' => $place,
            'pageTitle' => $holder->getName(),
            'holder' => $holder,
            'bibitems' => $holder->findBibitems($this->getDoctrine()->getManager(), true),
            'citeProc' => $this->instantiateCiteProc($request->getLocale()),
            'pageMeta' => [
                /*
                'jsonLd' => $holder->jsonLdSerialize($locale),
                'og' => $this->buildOg($request, $holder, $routeName, $routeParams),
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
            ->getRepository('AppBundle\Entity\Holder');

        if (!empty($id)) {
            $routeParams = [ 'id' => $id ];
            $holder = $repo->findOneById($id);
        }

        if (!isset($holder) || $holder->getStatus() == -1) {
            return $this->redirectToRoute('holder-index');
        }

        $csvResult = [];

        foreach ($holder->findBibitems($this->getDoctrine()->getManager(), true) as $row) {
            $bibitem = $row[0];

            $year = '';
            $datePublished = $bibitem->getDatePublished();
            if (!is_null($datePublished) && preg_match('/^(\d{4})/', $datePublished, $matches)) {
                $year = $matches[1];
            }

            $publisher = $bibitem->getPublisher();
            if (!is_null($publisher)) {
                $publisher = $publisher->getName();
            }

            $startDate = $endDate = $displayDate = $venue = '';
            foreach ($bibitem->exhibitionRefs as $exhibitionRef) {
                if ($exhibitionRef->getRole() == 1) {
                    $exhibition = $exhibitionRef->getExhibition();
                    $startDate = $exhibition->getStartdate();
                    $endDate = $exhibition->getEnddate();
                    $displayDate = $exhibition->getDisplaydate();
                    $location = $exhibition->getLocation();
                    if (!is_null($location)) {
                        $venue = $location->getName();
                    }
                    break;
                }
            }


            $csvResult[] = [
                $bibitem->getTitle(),
                $bibitem->getPublicationLocation(),
                $publisher,
                $year,
                $row['signature'],
                $row['url'],
                $startDate,
                $endDate,
                $displayDate,
                $venue,
            ];
        }

        return new CsvResponse($csvResult, 200, [
                'Title', 'Place of Publication', 'Publisher', 'Year of Publication',
                'Signature', 'URL',
                'Start Date', 'End Date', 'Display Date',
                'Venue',
            ], 'catalogues.xlsx');
    }
}
