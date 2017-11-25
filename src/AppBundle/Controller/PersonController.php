<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Intl\Intl;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

/**
 *
 */
class PersonController
extends CrudController
{
    use SharingBuilderTrait;

    protected function buildCountries()
    {
        $qb = $this->getDoctrine()
                ->getManager()
                ->createQueryBuilder();

        $qb->select([
                'P.nationality',
            ])
            ->distinct()
            ->from('AppBundle:Person', 'P')
            ->where('P.status <> -1 AND P.nationality IS NOT NULL')
            ;

        $countriesActive = [];

        foreach ($qb->getQuery()->getResult() as $result) {
            $countryCode = $result['nationality'];
            $countriesActive[$countryCode] = Intl::getRegionBundle()->getCountryName($countryCode);
        }

        asort($countriesActive);

        return $countriesActive;
    }

    /**
     * @Route("/person", name="person-index")
     * @Route("/person-by-nationality", name="person-nationality")
     */
    public function indexAction(Request $request)
    {
        $route = $request->get('_route');

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
            ->leftJoin('P.exhibitions', 'E')
            ->leftJoin('P.catalogueEntries', 'IE')
            ->where('P.status <> -1')
            ->groupBy('P.id') // for Count
            ->orderBy('nameSort')
            ;

        $form = $this->get('form.factory')->create(\AppBundle\Filter\PersonFilterType::class, [
            'choices' => array_flip($this->buildCountries()),
        ]);

        if ($request->query->has($form->getName())) {
            // manually bind values from the request
            $form->submit($request->query->get($form->getName()));

            // build the query from the given form object
            $this->get('lexik_form_filter.query_builder_updater')->addFilterConditions($form, $qb);
        }

        $pagination = $this->buildPagination($request, $qb->getQuery(), [
            'defaultSortFieldName' => 'nameSort', 'defaultSortDirection' => 'asc',
        ]);

        return $this->render('Person/index.html.twig', [
            'pageTitle' => $this->get('translator')->trans('Artists'),
            'pagination' => $pagination,
            'form' => $form->createView(),
        ]);
    }

    /* TODO: try to merge with inverse method in ExhibitionController */
    protected function findSimilar($entity)
    {
        $em = $this->getDoctrine()->getManager();
        $dbconn = $em->getConnection();

        // build all the ids
        $exhibitionIds = [];
        foreach ($entity->getExhibitions() as $exhibition) {
            if ($exhibition->getStatus() <> -1) {
                $exhibitionIds[] = $exhibition->getId();
            }
        }

        $numExhibitions = count($exhibitionIds);
        if (0 == $numExhibitions) {
            return [];
        }

        $querystr = "SELECT DISTINCT id_person, id_exhibition"
                  . " FROM ItemExhibition"
                  . " WHERE id_exhibition IN (" . join(', ', $exhibitionIds) . ')'
                  . " AND id_person <> " . $entity->getId()
                  . " ORDER BY id_person";

        $exhibitionsByPerson = [];
        $stmt = $dbconn->query($querystr);
        while ($row = $stmt->fetch()) {
            if (!array_key_exists($row['id_person'], $exhibitionsByPerson)) {
                $exhibitionsByPerson[$row['id_person']] = [];
            }
            $exhibitionsByPerson[$row['id_person']][] = $row['id_exhibition'];
        }

        $jaccardIndex = [];
        $personIds = array_keys($exhibitionsByPerson);
        if (count($personIds) > 0) {
            $querystr = "SELECT CONCAT(COALESCE(firstname, ''), ' ', COALESCE(lastname,firstname)) AS name, id_person, COUNT(DISTINCT id_exhibition) AS num_exhibitions"
                      . " FROM ItemExhibition"
                      . " LEFT OUTER JOIN Person ON ItemExhibition.id_person=Person.id"
                      . " WHERE id_person IN (" . join(', ', $personIds) . ')'
                      . " GROUP BY id_person";
            $stmt = $dbconn->query($querystr);
            while ($row = $stmt->fetch()) {
                $numShared = count($exhibitionsByPerson[$row['id_person']]);

                $jaccardIndex[$row['id_person']] = [
                    'name' => $row['name'],
                    'count' => $numShared,
                    'coefficient' =>
                          1.0
                          * $numShared // shared
                          /
                          ($row['num_exhibitions'] + $numExhibitions - $numShared),
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

    protected function findCatalogueEntries($person)
    {
        // get the catalogue entries by exhibition
        $qb = $this->getDoctrine()
                ->getManager()
                ->createQueryBuilder();

        $qb->select([
                'IE',
                'E.id',
            ])
            ->from('AppBundle:ItemExhibition', 'IE')
            ->innerJoin('IE.exhibition', 'E')
            ->where("IE.person = :person")
            ;

        $results = $qb->getQuery()
            ->setParameter('person', $person)
            ->getResult();

        $entriesByExhibition = [];
        foreach ($results as $result) {
            $catalogueEntry = $result[0];
            $exhibitionId = $result['id'];
            if (!array_key_exists($exhibitionId, $entriesByExhibition)) {
                $entriesByExhibition[$exhibitionId] = [];
            }
            $entriesByExhibition[$exhibitionId][] = $catalogueEntry;
        }

        // TODO: sort each exhibition by catalogueId

        return $entriesByExhibition;
    }

    /**
     * @Route("/person/ulan/{ulan}", requirements={"ulan" = "[0-9]+"}, name="person-by-ulan")
     * @Route("/person/gnd/{gnd}", requirements={"gnd" = "[0-9xX]+"}, name="person-by-gnd")
     * @Route("/person/{id}", requirements={"id" = "\d+"}, name="person")
     */
    public function detailAction(Request $request, $id = null, $ulan = null, $gnd = null)
    {
        $routeName = 'person';
        $routeParams = [];

        $criteria = new \Doctrine\Common\Collections\Criteria();
        $personRepo = $this->getDoctrine()
                ->getRepository('AppBundle:Person');

        if (!empty($id)) {
            $routeParams = [ 'id' => $id ];
            $criteria->where($criteria->expr()->eq('id', $id));
        }
        else if (!empty($ulan)) {
            $routeName = 'person-by-ulan';
            $routeParams = [ 'ulan' => $ulan ];
            $criteria->where($criteria->expr()->eq('ulan', $ulan));
        }
        else if (!empty($gnd)) {
            $routeName = 'person-by-gnd';
            $routeParams = [ 'gnd' => $gnd ];
            $criteria->where($criteria->expr()->eq('gnd', $gnd));
        }

        $criteria->andWhere($criteria->expr()->neq('status', -1));

        $persons = $personRepo->matching($criteria);

        if (0 == count($persons)) {
            return $this->redirectToRoute('person-index');
        }

        $person = $persons[0];

        $locale = $request->getLocale();
        if (in_array($request->get('_route'), [ 'person-jsonld', 'person-by-ulan-json', 'person-by-gnd-jsonld' ])) {
            return new JsonLdResponse($person->jsonLdSerialize($locale));
        }

        return $this->render('Person/detail.html.twig', [
            'pageTitle' => $person->getFullname(true), // TODO: lifespan in brackets
            'person' => $person,
            'showWorks' => !empty($_SESSION['user']),
            'catalogueEntries' => $this->findCatalogueEntries($person),
            'similar' => $this->findSimilar($person),
            'pageMeta' => [
                'jsonLd' => $person->jsonLdSerialize($locale),
                'og' => $this->buildOg($person, $routeName, $routeParams),
                'twitter' => $this->buildTwitter($person, $routeName, $routeParams),
            ],
        ]);
    }

    /*
     * TODO: mode=ulan
     */
    public function beaconAction($mode = 'gnd')
    {
        $translator = $this->container->get('translator');
        $twig = $this->container->get('twig');

        $personRepo = $this->getDoctrine()
                ->getRepository('AppBundle:Person');

        $query = $personRepo
                ->createQueryBuilder('P')
                ->where('P.status >= 0')
                ->andWhere('P.gnd IS NOT NULL')
                ->orderBy('P.gnd')
                ->getQuery()
                ;

        $persons = $query->execute();

        $ret = '#FORMAT: BEACON' . "\n"
             . '#PREFIX: http://d-nb.info/gnd/'
             . "\n";
        $ret .= sprintf('#TARGET: %s/gnd/{ID}',
                        $this->generateUrl('person-index', [], true))
              . "\n";

        $globals = $twig->getGlobals();
        $ret .= '#NAME: ' . $translator->trans($globals['siteName'])
              . "\n";
        // $ret .= '#MESSAGE: ' . "\n";

        foreach ($persons as $person) {
            $ret .=  $person->getGnd() . "\n";
        }

        return new \Symfony\Component\HttpFoundation\Response($ret, \Symfony\Component\HttpFoundation\Response::HTTP_OK,
                                                              [ 'Content-Type' => 'text/plain; charset=UTF-8' ]);
    }
}
