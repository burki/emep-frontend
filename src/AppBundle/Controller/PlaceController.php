<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Intl\Intl;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

/**
 *
 */
class PlaceController
extends CrudController
{
    protected $pageSize = 200; // 3000 if we want all on one page

    protected function buildCountries()
    {
        $qb = $this->getDoctrine()
                ->getManager()
                ->createQueryBuilder();

        $qb->select([
                'P.countryCode',
            ])
            ->distinct()
            ->from('AppBundle:Place', 'P')
            ->where("P.type IN ('inhabited places') AND P.countryCode IS NOT NULL")
            ;

        $countriesActive = [];

        foreach ($qb->getQuery()->getResult() as $result) {
            $countryCode = $result['countryCode'];
            $countriesActive[$countryCode] = Intl::getRegionBundle()->getCountryName($countryCode);
        }

        asort($countriesActive);

        return $countriesActive;
    }

    /**
     * @Route("/place", name="place-index")
     */
    public function indexAction(Request $request)
    {
        $qb = $this->getDoctrine()
                ->getManager()
                ->createQueryBuilder();

        $qb->select([
                'P', 'C',
                'COUNT(DISTINCT E.id) AS numExhibitionSort',
                'COUNT(DISTINCT IE.id) AS numCatEntrySort',
                "COALESCE(P.alternateName,P.name) HIDDEN nameSort",
                "CONCAT(COALESCE(C.name, P.countryCode), COALESCE(P.alternateName,P.name)) HIDDEN countrySort",
            ])
            ->from('AppBundle:Place', 'P')
            ->leftJoin('P.country', 'C')
            ->leftJoin('AppBundle:Location', 'L',
                       \Doctrine\ORM\Query\Expr\Join::WITH,
                       'L.place = P')
            ->leftJoin('AppBundle:Exhibition', 'E',
                       \Doctrine\ORM\Query\Expr\Join::WITH,
                       'E.location = L AND E.status <> -1')
            ->leftJoin('AppBundle:ItemExhibition', 'IE',
                       \Doctrine\ORM\Query\Expr\Join::WITH,
                       'IE.exhibition = E AND IE.title IS NOT NULL')
            ->where("P.type IN ('inhabited places')")
            ->groupBy('P.id')
            ->orderBy('nameSort')
            ;

        $form = $this->get('form.factory')->create(\AppBundle\Filter\PlaceFilterType::class, [
            'choices' => array_flip($this->buildCountries()),
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
            // 'defaultSortFieldName' => 'nameSort', 'defaultSortDirection' => 'asc',
        ]);

        return $this->render('Place/index.html.twig', [
            'pageTitle' => $this->get('translator')->trans('Places'),
            'pagination' => $pagination,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/place/{id}", requirements={"id" = "\d+"}, name="place")
     * @Route("/place/tgn/{tgn}", requirements={"tgn" = "\d+"}, name="place-by-tgn")
     */
    public function detailAction(Request $request, $id = null, $tgn = null)
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

        $locale = $request->getLocale();

        if (in_array($request->get('_route'), [ 'place-jsonld', 'place-by-tgn-jsonld' ])) {
            return new JsonLdResponse($place->jsonLdSerialize($locale));
        }

        // get the persons associated with this place, currently birthplace / deathplace
        // TODO: places of activity
        $qb = $this->getDoctrine()
                ->getManager()
                ->createQueryBuilder();

        $qb->select([
                'P',
                "CONCAT(COALESCE(P.familyName,P.givenName), ' ', COALESCE(P.givenName, '')) HIDDEN nameSort"
            ])
            ->from('AppBundle:Person', 'P')
            ->where("P.birthPlace = :place OR P.deathPlace = :place")
            ->andWhere('P.status <> -1')
            ->orderBy('P.birthDate')
            ->addOrderBy('nameSort')
            ;

        $persons = $qb->getQuery()
            ->setParameter('place', $place)
            ->getResult();

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
            ->where('L.place = :place AND E.status <> -1')
            ->setParameter('place', $place)
            ->groupBy('E.id')
            ;
        $exhibitionStats = [];
        foreach ($qb->getQuery()->getResult() as $row) {
           $exhibitionStats[$row['id']] = $row;
        }

        return $this->render('Place/detail.html.twig', [
            'pageTitle' => $place->getNameLocalized($locale),
            'place' => $place,
            'persons' => $persons,
            'exhibitionStats' => $exhibitionStats,
            'em' => $this->getDoctrine()->getManager(),
            'pageMeta' => [
                'jsonLd' => $place->jsonLdSerialize($locale),
            ],
        ]);
    }
}
