<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 *
 */
class AboutController
extends DefaultController
{
    protected function sendMessage($data)
    {
        $template = $this->get('twig')->loadTemplate('About/contact.email.twig');
        $subject = $template->renderBlock('subject', [ 'data' => $data ]);
        $textBody = $template->renderBlock('body_text', [ 'data' => $data ]);
        $htmlBody = $template->renderBlock('body_html', [ 'data' => $data ]);

        $message = \Swift_Message::newInstance()
            ->setSubject($subject)
            ->setFrom('burckhardtd@geschichte.hu-berlin.de')
            ->setTo('burckhardtd@geschichte.hu-berlin.de')
            ->setReplyTo($data['email']);
            ;


        if (!empty($htmlBody)) {
            $message->setBody($htmlBody, 'text/html')
                ->addPart($textBody, 'text/plain');
        }
        else {
            $message->setBody($textBody);
        }

        try {
            return $this->get('mailer')->send($message);
        }
        catch (\Exception $e) {
            return false;
        }
    }

    protected function fetchWordpressPage($slug)
    {
        $client = $this->instantiateWpApiClient();

        $page = null;

        if (false !== $client) {
            try {
                $pages = $client->pages()->get(null, [
                    'slug' => $slug,
                ]);
            }
            catch (\Exception $e) {
                // var_dump($e);
                ; // ignore
            }

            if (!empty($pages)) {
                $page = $pages[0];
            }
        }

        return $page;
    }

    protected function renderWordpress($slug, $fallbackTemplate = null)
    {
        $page = $this->fetchWordpressPage($slug);

        if (!empty($page)) {
            return $this->render('About/detail.html.twig', [
                'page' => $page,
            ]);
        }

        if (is_null($fallbackTemplate)) {
            return $this->redirectToRoute('project');
        }

        return $this->render($fallbackTemplate);
    }

    /**
     * @Route("/info")
     * @Route("/info/project", name="project", options={"sitemap" = true})
     */
    public function infoAction()
    {
        return $this->renderWordpress('about-project', 'Default/project.html.twig');
    }

    /**
     * @Route("/info/team", name="team", options={"sitemap" = true})
     */
    public function teamAction()
    {
        return $this->renderWordpress('team', 'Default/project.html.twig');
    }

    /**
     * @Route("/info/database", name="database", options={"sitemap" = true})
     */
    public function databaseAction()
    {
        return $this->renderWordpress('using-the-database', 'Default/using.html.twig');
    }

    /**
     * @Route("/info/publications", name="publications", options={"sitemap" = true})
     */
    public function publicationsAction()
    {
        return $this->renderWordpress('publications');
    }

    /**
     * @Route("/cooperating-institutions", name="cooperating", options={"sitemap" = true})
     */
    public function cooperatingAction()
    {
        return $this->renderWordpress('about-partners', 'Default/cooperating_institutions.html.twig');
    }

    /**
     * @Route("/contact", name="contact", options={"sitemap" = true})
     */
    public function contactAction(Request $request)
    {
        $form = $this->createForm(\AppBundle\Form\Type\ContactType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $translator = $this->get('translator');
            return $this->render('About/contact-sent.html.twig', [
                'pageTitle' => $translator->trans('Contact'),
                'success' => $this->sendMessage($form->getData()),
            ]);
        }

        return $this->render('About/contact.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
