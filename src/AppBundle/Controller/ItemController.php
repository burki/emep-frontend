<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

use PhpOffice\PhpWord\Shared\Converter;

/**
 *
 */
class ItemController
extends Controller
{
    use SharingBuilderTrait;

    protected function buildCollections()
    {
        $em = $this->getDoctrine()
                ->getManager();

        $result = $em->createQuery("SELECT C.id, C.name FROM AppBundle:Collection C"
                                   . " WHERE C.status <> -1"
                                   . " ORDER BY C.name")
                ->getResult();

        $collections = [];

        foreach ($result as $row) {
            $collections[$row['id']] = $row['name'];
        }

        return $collections;
    }

    /**
     * @Route("/work", name="item-index")
     * @Route("/work/by-exhibition", name="item-by-exhibition")
     * @Route("/work/by-exhibition/export", name="item-by-exhibition-export")
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
        if ('item-by-exhibition' == $route || 'item-by-exhibition-export' == $route) {
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
                ->where('E.status <> -1 AND I.status <> -1')
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
                ->leftJoin('I.style', 'T')
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

        $collections = $this->buildCollections();
        $collection = $request->get('collection');

        if (!empty($collection) && array_key_exists($collection, $collections)) {
            $qb->innerJoin('I.collection', 'C');
            $qb->andWhere(sprintf('C.id=%d', intval($collection)));
        }

        $results = $qb->getQuery()
            // ->setMaxResults(10) // for testing
            ->getResult();

        if ('item-by-exhibition-export' == $route) {
            $phpWord = new \PhpOffice\PhpWord\PhpWord();
            $phpWord->setDefaultParagraphStyle([
                'lineHeight' => 1.1,
                // the following is important in order not to get a gap between image and text
                'spaceAfter' => \PhpOffice\PhpWord\Shared\Converter::pointToTwip(0),
            ]);
            $phpWord->addTitleStyle(2, [
                'size' => 12,
                'bold' => true,
                // 'color' => 'e07743',
            ], [
                'spaceBefore' => Converter::pointToTwip(10),
                'spaceAfter' => 0,
            ]);

            $innerStyle = [
                'cellMarginRight' => Converter::pointToTwip(10),
                'cellMarginBottom' => Converter::pointToTwip(10),
                /*
                'bgColor' => 'FF0000', 'cellMarginTop' => 120, 'cellMarginBottom' => 120,
                'cellMarginLeft' => 120, 'cellMarginRight' => 120, 'borderTopSize' => 120,
                'borderBottomSize' => 120, 'borderLeftSize' => 120, 'borderRightSize' => 120,
                'borderInsideHSize' => 120, 'borderInsideVSize' => 120,
                */
            ];

            $height = 65; // in pt

            // Begin code
            $section = $phpWord->addSection();

            foreach ($results as $exhibition) {
                set_time_limit(120);

                $section->addTitle($exhibition->getTitle() .  $exhibition->getTitleAppend(), 2);

                $info = '';

                $displaydate = $exhibition->getDisplaydate();
                if (empty($displaydate)) {
                    $displaydate = \AppBundle\Utils\Formatter::daterangeIncomplete($exhibition->getStartdate(), $exhibition->getEnddate(), $request->getLocale());
                }
                $info .= $displaydate;

                $location = $exhibition->getLocation();
                if (!is_null($location)) {
                    $place = $location->getPlace();
                    if (!is_null($place)) {
                        $info .= ' ' . $place->getNameLocalized($request->getLocale()) . ' : ';
                    }
                    $info .= ' ' . $location->getName();
                }

                $section->addText(htmlspecialchars($info, ENT_COMPAT, 'utf-8'), [], [
                    // 'spaceAfter' => Converter::pointToTwip(10),
                ]);

                $items = $exhibition->getItems();
                if (!empty($items)) {
                    $table = $section->addTable();

                    $count = 0;
                    foreach ($items as $item) {
                        if (0 == $count) {
                            // add new inner row
                            $fullCell = $table->addRow()->addCell();
                            $innerRow = $fullCell->addTable($innerStyle)->addRow();
                        }

                        $cell = $innerRow->addCell();

                        $preview = $item->getPreviewImg();

                        $imgUrl = is_null($preview)
                            ? '/img/placeholder-image.jpg'
                            : '/uploads/' .  $preview->getImgUrl('preview');

                        $imgUrl = 'https://exhibitions05-15.kugb.univie.ac.at' . $imgUrl;
                        $cell->addImage($imgUrl, [ 'height' => $height ]);

                        // build the caption
                        $parts = [];
                        $creators = $item->creators;
                        if (!empty($creators)) {
                            $parts = array_map(function ($creator) { return $creator->getFamilyName(); },
                                               $creators->toArray());
                        }

                        $parts[] = $item->getTitle();

                        $displaydate = $item->getDisplayDate();
                        if (empty($displaydate)) {
                            $displaydate = \AppBundle\Utils\Formatter::dateIncomplete($item->getEarliestdate(), $request->getLocale());
                        }
                        if (!empty($displaydate)) {
                            $parts[] = $displaydate;
                        }

                        $catalogueId = $item->catalogueId;
                        if (!empty($catalogueId)) {
                            $parts[] = $catalogueId;
                        }

                        $style = $item->getStyle();
                        if (!empty($style)) {
                            $parts[] = $style;
                        }

                        $cell->addText(htmlspecialchars(join(', ', $parts), ENT_COMPAT, 'utf-8'));

                        if (++$count > 4) {
                            $count = 0;
                        }
                    }
                }
            }

            $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');


            header("Content-Type: application/vnd.openxmlformats-officedocument.wordprocessing??ml.document");
            header('Content-Disposition: attachment; filename=works-by-exhibition.docx');
            $objWriter->save('php://output');// this would output it like echo, but in combination with header: it will be sent

            exit;

            return new \Symfony\Component\HttpFoundation\Response($ret, 200, [
                'Content-Type' => 'text/plain; charset=utf-8',
            ]);
        }

        return $this->render('Item/index'
                             . $templateAppend
                             . '.html.twig', [
            'pageTitle' => $this->get('translator')->trans('Works'),
            'results' => $results,
            'persons' => $persons,
            'collections' => $collections,
            'collection' => $collection,
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
        $labels = [
            'Naturalistic',
            'Stylized / form OR colour', 'Stylized / form AND colour',
            'Non-representational',
            'Anti-illusionistic',
        ];

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

        $item = null;
        $idIgnore = null;
        if (array_key_exists('ignore', $_GET) && intval($_GET['ignore']) > 0) {
            $idIgnore = intval($_GET['ignore']);
        }
        else if (array_key_exists('id', $_GET) && intval($_GET['id']) > 0) {
            $repo = $this->getDoctrine()
                    ->getRepository('AppBundle:Item');

            $item = $repo->findOneById(intval($_GET['id']));
            $session->set('assessment_show', 'assessed');
        }

        if ($request->getMethod() == 'POST' && array_key_exists('show', $_POST['assessment'])) {
            $session->set('assessment_show', $_POST['assessment']['show']);
        }

        $formOptions = [
            'show_default' => $session->has('assessment_show')
                ? $session->get('assessment_show') : 'not assessed yet',
            'style_choices' => $this->buildStyleChoices(),
            'action' => $this->generateUrl('item-assessment'), // so ?id= isn't passed on
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
                $idIgnore = $item->getId();
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

        if (!is_null($idIgnore)) {
            $fields[] = sprintf("IF(I.id = %d, 1, 0) AS HIDDEN idSort",
                                $idIgnore);
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

                case 'no consensus':
                    $qb->andWhere('UI.id IS NULL AND I.style IS NULL');
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

    /**
     * @Route("/work/assessment/overview", name="item-assessment-overview")
     */
    public function assessmentOverviewAction(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_EXPERT', null, 'Unable to access this page!');

        $user = $this->get('security.token_storage')->getToken()->getUser();

        $qb = $this->getDoctrine()
                ->getManager()
                ->createQueryBuilder();

        $qb->select([
                'I',
                'T.name AS style',
                'T.name HIDDEN styleSort',
                "COALESCE(I.earliestdate, I.creatordate) HIDDEN dateSort",
                "P.familyName HIDDEN nameSort",
                "I.catalogueId HIDDEN catSort",
            ])
            ->from('AppBundle:Item', 'I')
            ->innerJoin('AppBundle:UserItem', 'UI', 'WITH', 'UI.item=I AND UI.user=:user')
            ->innerJoin('UI.style', 'T')
            ->leftJoin('I.creators', 'P')
            ->where('I.status <> -1 AND P.status <> -1')
            ->orderBy('styleSort DESC, dateSort, nameSort, catSort')
            ->setParameter('user', $user)
            ;

        $results = $qb->getQuery()
            // ->setMaxResults(10) // for testing
            ->getResult();

        return $this->render('Item/assessment-overview.html.twig', [
            'pageTitle' => $this->get('translator')->trans('Assessed Works'),
            'results' => $results,
        ]);
    }
}
