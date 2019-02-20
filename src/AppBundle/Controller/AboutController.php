<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

/**
 *
 */
class AboutController extends Controller
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
        } else {
            $message->setBody($textBody);
        }

        try {
            return $this->get('mailer')->send($message);
        }
        catch (\Exception $e) {
            return false;
        }
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
