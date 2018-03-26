<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

/**
 *
 */
class ItemController
extends Controller
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

    protected function fetchAssessment(\AppBundle\Entity\User $user,
                                       \AppBundle\Entity\Item $item)
    {
        $qb = $this->getDoctrine()
                ->getManager()
                ->createQueryBuilder();

        $qb->select([
            'UI'
        ])
        ->from('AppBundle:UserItem', 'UI')
        ->where('UI.user = :user AND UI.item = :item')
        ->setParameters([
            'user' => $user,
            'item' => $item,
        ]);

        return $qb->getQuery()->getOneOrNullResult();
    }

    protected function buildStyleChoices()
    {
        $labels = [ 'Figurative', 'Abstracted', 'Abstract' ];
        $termRepo = $this->getDoctrine()
                ->getRepository('AppBundle:Term');

        $options = [];
        foreach ($labels as $label) {
            $option = $termRepo->findOneByName($label);
            if (!is_null($option)) {
                $options[] = $option;
            }
        }

        return $options;
    }

    /**
     * @Route("/work/assessment", name="item-assessment")
     */
    public function assignStyleAction(Request $request,
                                      \Symfony\Component\HttpFoundation\Session\SessionInterface $session)
    {
        $this->denyAccessUnlessGranted('ROLE_EXPERT', null, 'Unable to access this page!');

        $user = $this->get('security.token_storage')->getToken()->getUser();
        $assessment = new \AppBundle\Entity\UserItem();
        $assessment->setUser($user);

        $id_ignore = null;
        if (array_key_exists('id', $_GET) && intval($_GET['id']) > 0) {
            $id_ignore = intval($_GET['id']);
        }

        $item = null;
        if ($request->getMethod() == 'POST' && array_key_exists('show', $_POST['assessment'])) {
            $session->set('assessment_show', $_POST['assessment']['show']);
        }

        $formOptions = [
            'show_default' => $session->has('assessment_show')
                ? $session->get('assessment_show') : 'not assessed yet',
            'style_choices' => $this->buildStyleChoices(),
        ];

        $form = $this->get('form.factory')->create(\AppBundle\Form\Type\AssessmentType::class,
                                                   $assessment, $formOptions);
        if ($request->getMethod() == 'POST' && array_key_exists('submit', $_POST['assessment'])) {
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                $item = $assessment->getItem();
                $assessmentExisting = $this->fetchAssessment($user, $item);
                if (!is_null($assessmentExisting)) {
                    $assessmentExisting->setStyle($assessment->getStyle());
                    $assessment = $assessmentExisting;
                }

                $em = $this->getDoctrine()
                    ->getManager();

                $em->persist($assessment);
                $em->flush();
                $id_ignore = $item->getId();
                $item = null;

                $assessment = new \AppBundle\Entity\UserItem();
                $assessment->setUser($user);
            }
        }

        $qb = $this->getDoctrine()
                ->getManager()
                ->createQueryBuilder();

        $fields = [
            'I',
            'UI.id',
            "P.familyName HIDDEN nameSort",
            "COALESCE(I.earliestdate, I.creatordate) HIDDEN dateSort",
            "I.catalogueId HIDDEN catSort",
            "RAND() as HIDDEN randSort"
        ];
        $orders = [];

        if (!is_null($id_ignore)) {
            $fields[] = sprintf("IF(I.id = %d, 1, 0) AS HIDDEN idSort",
                                $id_ignore);
            $orders[] = 'idSort';
        }
        $orders[] = 'randSort';

        if (is_null($item)) {
            $qb->select($fields)
                ->from('AppBundle:Item', 'I')
                ->innerJoin('I.media', 'M')
                ->leftJoin('I.creators', 'P')
                ->leftJoin('AppBundle:UserItem', 'UI', 'WITH', 'UI.item=I AND UI.user=:user')
                ->where('I.status <> -1 AND P.status <> -1')
                ->orderBy(implode(', ', $orders))
                ->setMaxResults(1)
                ->setParameter('user', $user)
            ;

            switch ($formOptions['show_default']) {
                case 'not assessed yet':
                    $qb->having('UI.id IS NULL');
                    break;

                case 'assessed':
                    $qb->having('UI.id IS NOT NULL');
                    break;

                default:
                    // var_dump($formOptions['show_default']);
            }

            $items = $qb->getQuery()->getResult();
            $item = count($items) > 0 ? $items[0][0] : null;
        }

        if (!is_null($item)) {
            $assessmentExisting = $this->fetchAssessment($user, $item);
            if (!is_null($assessmentExisting)) {
                $assessment = $assessmentExisting;
            }
            else {
                $assessment->setItem($item);
            }
        }

        $form = $this->get('form.factory')->create(\AppBundle\Form\Type\AssessmentType::class,
                                                   $assessment, $formOptions);

        return $this->render('Item/assessment.html.twig', [
            'pageTitle' => 'Assign Style',
            'item' => $item,
            'assessment' => $assessment,
            'form' => $form->createView(),
        ]);
    }
}
