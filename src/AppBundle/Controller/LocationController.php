<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

/**
 *
 */
class LocationController
extends CrudController
{
    use SharingBuilderTrait;

    // TODO: share with ExhibitionController
    protected function buildCountries()
    {
        $qb = $this->getDoctrine()
                ->getManager()
                ->createQueryBuilder();

        $qb->select([
                'P.countryCode',
            ])
            ->distinct()
            ->from('AppBundle:Location', 'L')
            ->leftJoin('L.place', 'P')
            ->where('L.status <> -1 AND 0 = BIT_AND(L.flags, 256) AND P.countryCode IS NOT NULL')
            ;

        return $this->buildActiveCountries($qb);
    }

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
                "CONCAT(COALESCE(C.name, P.countryCode), COALESCE(P.alternateName,P.name),L.name) HIDDEN countryPlaceNameSort",
                "L.name HIDDEN nameSort",
                'COUNT(DISTINCT E.id) AS numExhibitionSort',
                'COUNT(DISTINCT IE.id) AS numCatEntrySort',
            ])
            ->from('AppBundle:Location', 'L')
            ->leftJoin('L.place', 'P')
            ->leftJoin('P.country', 'C')
            ->leftJoin('AppBundle:Exhibition', 'E',
                       \Doctrine\ORM\Query\Expr\Join::WITH,
                       'E.location = L AND E.status <> -1')
            ->leftJoin('AppBundle:ItemExhibition', 'IE',
                       \Doctrine\ORM\Query\Expr\Join::WITH,
                       'IE.exhibition = E AND IE.title IS NOT NULL')
            ->where('L.status <> -1 AND 0 = BIT_AND(L.flags, 256)')
            ->groupBy('L.id')
            ->orderBy('countryPlaceNameSort')
            ;

        $types = $this->buildVenueTypes();
        $form = $this->get('form.factory')->create(\AppBundle\Filter\LocationFilterType::class, [
            'country_choices' => array_flip($this->buildCountries()),
            'location_type_choices' => array_combine($types, $types),
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

        $locations = $qb->getQuery()->getResult();

        $countries = $form->get('country')->getData();
        $locationType = $form->get('location_type')->getData();

        return $this->render('Location/index.html.twig', [
            'pageTitle' => $this->get('translator')->trans('Venues'),
            'pagination' => $pagination,
            'form' => $form->createView(),
            'countries' => $countries,
            'locationType' => $locationType
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

        // artists this venue
        $qb = $this->getDoctrine()
                ->getManager()
                ->createQueryBuilder();

        $qb->select([
                'P',
                'COUNT(DISTINCT E.id) AS numExhibitionSort',
                'COUNT(DISTINCT IE.id) AS numCatEntrySort',
                "CONCAT(COALESCE(P.familyName,P.givenName), ' ', COALESCE(P.givenName, '')) HIDDEN nameSort"
            ])
            ->from('AppBundle:Person', 'P')
            ->innerJoin('AppBundle:ItemExhibition', 'IE',
                       \Doctrine\ORM\Query\Expr\Join::WITH,
                       'IE.person = P AND IE.title IS NOT NULL')
            ->innerJoin('IE.exhibition', 'E')
            ->where('E.location = :location AND E.status <> -1')
            ->setParameter('location', $location)
            ->groupBy('P.id')
            ->orderBy('nameSort')
            ;
        $artists = $qb->getQuery()->getResult();

        // stats
        $qb = $this->getDoctrine()
                ->getManager()
                ->createQueryBuilder();
        $qb->select([
                'E.id AS id',
                'COUNT(DISTINCT IE.id) AS numCatEntrySort',
                'COUNT(DISTINCT P.id) AS numPersonSort',
            ])
            ->from('AppBundle:Exhibition', 'E')
            ->leftJoin('E.location', 'L')
            ->leftJoin('AppBundle:ItemExhibition', 'IE',
                       \Doctrine\ORM\Query\Expr\Join::WITH,
                       'IE.exhibition = E AND IE.title IS NOT NULL')
            ->leftJoin('IE.person', 'P')
            ->where('E.location = :location AND E.status <> -1')
            ->setParameter('location', $location)
            ->groupBy('E.id')
            ;
        $exhibitionStats = [];
        foreach ($qb->getQuery()->getResult() as $row) {
           $exhibitionStats[$row['id']] = $row;
        }

        return $this->render('Location/detail.html.twig', [
            'pageTitle' => $location->getName(),
            'location' => $location,
            'exhibitionStats' => $exhibitionStats,
            'artists' => $artists,
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
