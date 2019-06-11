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

    protected function renderWordpress($slug, $fallbackTemplate)
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

        if (!empty($page)) {
            return $this->render('About/detail.html.twig', [
                'page' => $page,
            ]);
        }

        return $this->render($fallbackTemplate);
    }

    /**
     * @Route("/info")
     * @Route("/info/project", name="project")
     */
    public function infoAction()
    {
        return $this->renderWordpress('about-project', 'Default/project.html.twig');
    }

    /**
     * @Route("/info/team", name="team")
     */
    public function teamAction()
    {
        return $this->renderWordpress('team', 'Default/project.html.twig');
    }

    /**
     * @Route("/info/using", name="using")
     */
    public function usingAction()
    {
        return $this->renderWordpress('using-the-database', 'Default/using.html.twig');
    }

    /**
     * @Route("/cooperating-institutions", name="cooperating")
     */
    public function cooperatingAction()
    {
        return $this->renderWordpress('about-partners', 'Default/cooperating_institutions.html.twig');
    }

    /**
     * @Route("/contact", name="contact")
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
