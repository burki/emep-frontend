<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 *
 */
class AboutController
extends DefaultController
{
    protected function sendMessage(MailerInterface $mailer, $data)
    {
        $template = $this->getTwig()->load('About/contact.email.twig');
        $subject = $template->renderBlock('subject', [ 'data' => $data ]);
        $textBody = $template->renderBlock('body_text', [ 'data' => $data ]);
        $htmlBody = $template->renderBlock('body_html', [ 'data' => $data ]);

        $message = (new Email())
            ->subject($subject)
            ->from('burckhardtd@geschichte.hu-berlin.de')
            ->to('burckhardtd@geschichte.hu-berlin.de')
            ->replyTo($data['email']);
            ;

        if (!empty($htmlBody)) {
            $message->html($htmlBody)
                ->text($textBody);
        }
        else {
            $message->text($textBody);
        }

        try {
            $mailer->send($message);
        }
        catch (\Exception $e) {
            return false;
        }

        return true;
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
        return $this->renderWordpress('database', 'Default/using.html.twig');
    }

    /**
     * @Route("/info/using", name="using", options={"sitemap" = true})
     */
    public function usingAction()
    {
        return $this->renderWordpress('using-the-database');
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
    public function contactAction(Request $request,
                                  TranslatorInterface $translator,
                                  MailerInterface $mailer)
    {
        $form = $this->createForm(\AppBundle\Form\Type\ContactType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            return $this->render('About/contact-sent.html.twig', [
                'pageTitle' => $translator->trans('Contact'),
                'success' => $this->sendMessage($mailer, $form->getData()),
            ]);
        }

        return $this->render('About/contact.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
