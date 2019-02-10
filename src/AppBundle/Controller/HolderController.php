<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use AppBundle\Utils\CsvResponse;


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
    public function indexAction(Request $request, UserInterface $user = null)
    {
        // redirect to saved query
        if ('POST' == $request->getMethod() && !is_null($user)) {
            // check a useraction was requested
            $userActionId = $request->request->get('useraction');
            if (!empty($userActionId)) {
                $userAction = $this->getDoctrine()
                    ->getManager()
                    ->getRepository('AppBundle:UserAction')
                    ->findOneBy([
                        'id' => $userActionId,
                        'user' => $user,
                        'route' => 'holder',
                    ]);

                if (!is_null($userAction)) {
                    return $this->redirectToRoute($userAction->getRoute(),
                                                  $userAction->getRouteParams());
                }
            }
        }

        $requestURI = $request->getRequestUri();

        $route = $request->get('_route');

        $qb = $this->getDoctrine()
                ->getManager()
                ->createQueryBuilder();

        $qb->select([
                'H',
                "CONCAT(COALESCE(H.countryCode, ''),H.placeLabel,H.name) HIDDEN countryPlaceNameSort",
                "H.name HIDDEN nameSort",
                'COUNT(DISTINCT BE.exhibition) AS numBibitemSort',
            ])
            ->from('AppBundle:Holder', 'H')
            ->leftJoin('AppBundle:BibitemHolder', 'BH',
                       \Doctrine\ORM\Query\Expr\Join::WITH,
                       'BH.holder = H')
            ->leftJoin('AppBundle:Bibitem', 'B',
                       \Doctrine\ORM\Query\Expr\Join::WITH,
                       'BH.bibitem = B')
            ->leftJoin('AppBundle:BibitemExhibition', 'BE',
                       \Doctrine\ORM\Query\Expr\Join::WITH,
                       'BE.bibitem = B AND BE.role = 1') // catalogues only
            ->where('H.status <> -1')
            ->groupBy('H.id')
            ->orderBy('nameSort')
            ;

        $form = $this->get('form.factory')->create(\AppBundle\Filter\HolderFilterType::class, [
            'country_choices' => array_flip($this->buildCountries()),
            'ids' => range(0, 9999)
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
        $ids = $form->get('id')->getData();


        // $holders = $qb->getQuery()->getResult();

        return $this->render('Holder/index.html.twig', [
            'pageTitle' => $this->get('translator')->trans('Holding Institutions'),
            'pagination' => $pagination,
            'countryArray' => $this->buildCountries(),
            'countries' => $countries,
            'stringPart' => $stringQuery,
            'ids' => $ids,
            'form' => $form->createView(),
            'requestURI' =>  $requestURI,
            'searches' => $this->lookupSearches($user, 'exhibition')
        ]);
    }

    /**
     * @Route("/holder/csv", name="holder-csv")
     */
    public function indexToCsvAction(Request $request)
    {
        $route = $request->get('_route');

        $qb = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();

        $qb->select([
                'H',
                "CONCAT(COALESCE(H.countryCode, ''), H.placeLabel, H.name) HIDDEN countryPlaceNameSort",
                'H.name HIDDEN nameSort',
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
            'ids' => range(0, 9999)
        ]);

        if ($request->query->has($form->getName())) {
            // manually bind values from the request
            $form->submit($request->query->get($form->getName()));

            // build the query from the given form object
            $this->get('lexik_form_filter.query_builder_updater')->addFilterConditions($form, $qb);
        }

        $result = $qb->getQuery()->execute();

        $csvResult = [];

        foreach ($result as $value) {
            $innerArray = [];

            $entry = $value[0];

            array_push($innerArray, $entry->getName(), $value['numBibitemSort'] );

            array_push($csvResult, $innerArray);
        }

        return new CsvResponse($csvResult, 200, [ 'Name', '# of Catalogues' ], 'holding_institutions.xlsx');
    }

    // TODO MOVE TO SHARED
    protected function lookupSearches($user)
    {
        if (is_null($user)) {
            return [];
        }

        $qb = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();

        $qb->select('UA')
            ->from('AppBundle:UserAction', 'UA')
            ->where("UA.route = 'holder'")
            ->andWhere("UA.user = :user")
            ->orderBy("UA.createdAt", "DESC")
            ->setParameter('user', $user)
            ;

        $searches = [];

        foreach ($qb->getQuery()->getResult() as $userAction) {
            $searches[$userAction->getId()] = $userAction->getName();
        }

        return $searches;
    }

    /**
     * @Route("/holder/save", name="holder-save")
     */
    public function saveSearchActionHolder(Request $request,
                                             UserInterface $user)
    {

        $parametersAsString = $request->get('entity');
        $parametersAsString = str_replace("/holder?", '', $parametersAsString);


        parse_str($parametersAsString, $parameters);


        $form = $this->createForm(\AppBundle\Form\Type\SaveSearchType::class);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $userAction = new \AppBundle\Entity\UserAction();

            $userAction->setUser($user);
            $userAction->setRoute($route = 'holder');
            $userAction->setRouteParams($parameters);

            $userAction->setName($data['name']);

            $em = $this->getDoctrine()
                ->getManager();

            $em->persist($userAction);
            $em->flush();

            return $this->redirectToRoute($route, $parameters);
        }

        return $this->render('Search/save.html.twig', [
            'pageTitle' => $this->get('translator')->trans('Save your query'),
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
