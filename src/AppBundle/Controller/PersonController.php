<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

/**
 *
 */
class PersonController extends Controller
{
    use SharingBuilderTrait;

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
                "CONCAT(COALESCE(P.familyName,P.givenName), ' ', COALESCE(P.givenName, '')) HIDDEN nameSort"
            ])
            ->from('AppBundle:Person', 'P')
            ->where('P.status <> -1')
            ->orderBy('nameSort')
            ;

        $persons = $qb->getQuery()->getResult();

        return $this->render('Person/index.html.twig', [
            'pageTitle' => $this->get('translator')->trans('Artists'),
            'persons' => $persons,
        ]);
    }

    /**
     * @Route("/person/ulan/{ulan}", requirements={"ulan" = "[0-9]+"}, name="person-by-ulan")
     * @Route("/person/gnd/{gnd}", requirements={"gnd" = "[0-9xX]+"}, name="person-by-gnd")
     * @Route("/person/{id}", requirements={"id" = "\d+"}, name="person")
     */
    public function detailAction(Request $request, $id = null, $ulan = null, $gnd = null)
    {
        $routeName = 'person'; $routeParams = [];

        $personRepo = $this->getDoctrine()
                ->getRepository('AppBundle:Person');

        if (!empty($id)) {
            $routeParams = [ 'id' => $id ];
            $person = $personRepo->findOneById($id);
        }
        else if (!empty($ulan)) {
            $routeName = 'person-by-ulan'; $routeParams = [ 'ulan' => $ulan ];
            $person = $personRepo->findOneByUlan($ulan);
        }
        else if (!empty($gnd)) {
            $routeName = 'person-by-gnd'; $routeParams = [ 'gnd' => $gnd ];
            $person = $personRepo->findOneByGnd($gnd);
        }

        if (!isset($person) || $person->getStatus() == -1) {
            return $this->redirectToRoute('person-index');
        }

        $locale = $request->getLocale();
        if (in_array($request->get('_route'), [ 'person-jsonld', 'person-by-ulan-json', 'person-by-gnd-jsonld' ])) {
            return new JsonLdResponse($person->jsonLdSerialize($locale));
        }

        return $this->render('Person/detail.html.twig', [
            'pageTitle' => $person->getFullname(true), // TODO: lifespan in brackets
            'person' => $person,
            'pageMeta' => [
                'jsonLd' => $person->jsonLdSerialize($locale),
                'og' => $this->buildOg($person, $routeName, $routeParams),
                'twitter' => $this->buildTwitter($person, $routeName, $routeParams),
            ],
        ]);
    }

    /*
     * TODO: mode=tgn
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
