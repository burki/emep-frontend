<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Intl\Intl;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

/**
 *
 */
class ExhibitionController
extends CrudController
{
    use SharingBuilderTrait;

    protected function buildCountries()
    {
        $qb = $this->getDoctrine()
                ->getManager()
                ->createQueryBuilder();

        $qb->select([
                'P.countryCode',
            ])
            ->distinct()
            ->from('AppBundle:Exhibition', 'E')
            ->leftJoin('E.location', 'L')
            ->leftJoin('L.place', 'P')
            ->where('E.status <> -1 AND P.countryCode IS NOT NULL')
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
     * @Route("/exhibition", name="exhibition-index")
     */
    public function indexAction(Request $request)
    {
        $route = $request->get('_route');

        $qb = $this->getDoctrine()
                ->getManager()
                ->createQueryBuilder();

        $qb->select([
                'E',
                "E.startdate HIDDEN dateSort",
                "CONCAT(COALESCE(P.alternateName, P.name), E.startdate) HIDDEN placeSort"
            ])
            ->from('AppBundle:Exhibition', 'E')
            ->leftJoin('E.location', 'L')
            ->leftJoin('L.place', 'P')
            ->where('E.status <> -1')
            ->orderBy('dateSort')
            ;

        $form = $this->get('form.factory')->create(\AppBundle\Filter\ExhibitionFilterType::class, [
            'choices' => array_flip($this->buildCountries()),
        ]);

        if ($request->query->has($form->getName())) {
            // manually bind values from the request
            $form->submit($request->query->get($form->getName()));

            // build the query from the given form object
            $this->get('lexik_form_filter.query_builder_updater')->addFilterConditions($form, $qb);
        }

        $pagination = $this->buildPagination($request, $qb->getQuery(), [
            'defaultSortFieldName' => 'dateSort', 'defaultSortDirection' => 'asc',
        ]);

        return $this->render('Exhibition/index.html.twig', [
            'pageTitle' => $this->get('translator')->trans('Exhibitions'),
            'pagination' => $pagination,
            'form' => $form->createView(),
        ]);
    }

    /* TODO: try to merge with inverse method in PersonController */
    protected function findSimilar($entity)
    {
        $em = $this->getDoctrine()->getManager();
        $dbconn = $em->getConnection();

        // build all the ids
        $personIds = [];
        foreach ($entity->getArtists() as $person) {
            if ($person->getStatus() <> -1) {
                $personIds[] = $person->getId();
            }
        }

        $numArtists = count($personIds);
        if (0 == $numArtists) {
            return [];
        }
        $querystr = "SELECT DISTINCT id_person, id_exhibition"
                  . " FROM ItemExhibition"
                  . " WHERE id_person IN (" . join(', ', $personIds) . ')'
                  . " AND id_exhibition <> " . $entity->getId()
                  . " ORDER BY id_exhibition";

        $personsByExhibition = [];
        $stmt = $dbconn->query($querystr);
        while ($row = $stmt->fetch()) {
            if (!array_key_exists($row['id_exhibition'], $personsByExhibition)) {
                $personsByExhibition[$row['id_exhibition']] = [];
            }
            $personsByExhibition[$row['id_exhibition']][] = $row['id_person'];
        }

        $jaccardIndex = [];
        $exhibitionIds = array_keys($personsByExhibition);
        if (count($exhibitionIds) > 0) {
            $querystr = "SELECT Exhibition.title AS title, id_exhibition, COUNT(DISTINCT id_person) AS num_artists"
                      . " FROM ItemExhibition"
                      . " LEFT OUTER JOIN Exhibition ON ItemExhibition.id_exhibition=Exhibition.id AND Exhibition.status <> -1"
                      . " WHERE id_exhibition IN (" . join(', ', $exhibitionIds) . ')'
                      . " GROUP BY id_exhibition";
            $stmt = $dbconn->query($querystr);
            while ($row = $stmt->fetch()) {
                $numShared = count($personsByExhibition[$row['id_exhibition']]);
                $jaccardIndex[$row['id_exhibition']] = [
                    'title' => $row['title'],
                    'count' => $numShared,
                    'coefficient' =>
                          1.0
                          * $numShared // shared
                          /
                          ($row['num_artists'] + $numArtists - $numShared),
                ];
            }
            uasort($jaccardIndex,
                function ($a, $b) {
                    if ($a['coefficient'] == $b['coefficient']) {
                        return 0;
                    }
                    // highest first
                    return $a['coefficient'] < $b['coefficient'] ? 1 : -1;
                }
            );
        }
        return $jaccardIndex;
    }

    /**
     * @Route("/exhibition/{id}", requirements={"id" = "\d+"}, name="exhibition")
     */
    public function detailAction(Request $request, $id = null, $ulan = null, $gnd = null)
    {
        $routeName = $request->get('_route'); $routeParams = [];

        $repo = $this->getDoctrine()
                ->getRepository('AppBundle:Exhibition');

        if (!empty($id)) {
            $routeParams = [ 'id' => $id ];
            $exhibition = $repo->findOneById($id);
        }

        if (!isset($exhibition) || $exhibition->getStatus() == -1) {
            return $this->redirectToRoute('exhibition-index');
        }

        $locale = $request->getLocale();
        if (in_array($request->get('_route'), [ 'exhibition-jsonld' ])) {
            return new JsonLdResponse($person->jsonLdSerialize($locale));
        }

        return $this->render('Exhibition/detail.html.twig', [
            'pageTitle' => $exhibition->title, // TODO: dates in brackets
            'exhibition' => $exhibition,
            'showWorks' => !empty($_SESSION['user']),
            'similar' => $this->findSimilar($exhibition),
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
