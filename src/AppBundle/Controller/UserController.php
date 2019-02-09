<?php

namespace AppBundle\Controller;

use AppBundle\Form\Type\UserType;
use AppBundle\Entity\User;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 *
 */
class UserController
extends Controller
{

    /**
     * @Route("/my-data", name="my-data")
     */
    public function myDataAction(Request $request, UserInterface $user = null)
    {
        if (is_null($user)) {
            return $this->redirectToRoute('login');
        }

        $queries = $this->lookupAllSearches($user);

        return $this->render('User/mydata.html.twig', [
            'pageTitle' => 'My Data',
            'queries' => $queries,
        ]);
    }

    protected function lookupAllSearches($user)
    {
        if (is_null($user)) {
            return [];
        }

        $qb = $this->getDoctrine()
            ->getManager()
            ->createQueryBuilder();

        $qb->select('UA')
            ->from('AppBundle:UserAction', 'UA')
            ->andWhere("UA.user = :user")
            ->orderBy("UA.createdAt", "DESC")
            ->setParameter('user', $user)
        ;

        $searches = [];

        foreach ($qb->getQuery()->getResult() as $userAction) {
                $searches[$userAction->getId()] = [ $userAction->getName(),
                    $userAction->getRoute(),
                    $userAction->getRoute() . "/?" . http_build_query( $userAction->getRouteParams() )
                ];

        }

        return $searches;
    }


    /**
     * @Route("/login", name="login")
     */
    public function loginAction(Request $request, AuthenticationUtils $authenticationUtils = null)
    {
        // check if we can authenticate from backend-session
        if (!empty($_SESSION['user']) && !empty($_SESSION['user']['login'])) {
            $userRepo = $this->getDoctrine()->getRepository('AppBundle:User');
            $user = $userRepo->loadUserByUsername($_SESSION['user']['login']);
            if (!is_null($user)) {
                $firewall = 'main';

                $token = new UsernamePasswordToken($user, null, $firewall, $user->getRoles());
                $this->get('security.token_storage')->setToken($token);

                // If the firewall name is not main, then the set value would be instead:
                // $this->get('session')->set('_security_XXXFIREWALLNAMEXXX', serialize($token));
                $this->get('session')->set('_security_' . $firewall, serialize($token));

                // dispatch the login event
                $event = new InteractiveLoginEvent($request, $token);
                $this->get('event_dispatcher')->dispatch('security.interactive_login', $event);

                // now redirect to target_path
                $url = $this->generateUrl('home');

                $key = '_security.' . $firewall . '.target_path';
                $session = $this->get('session');
                if ($session->has($key)) {
                    $url = $session->get($key);
                }

                return $this->redirect($url);
            }
        }

        if (is_null($authenticationUtils)) {
            $authenticationUtils = $this->get('security.authentication_utils');
        }

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('User/login.html.twig', [
            'last_username' => $lastUsername,
            'error'         => $error,
        ]);
    }

    /**
     * @Route("/logout", name="logout")
     */
    public function logoutAction()
    {
    }

    protected function sendMessage($template, $data)
    {
        $template = $this->get('twig')->loadTemplate($template);

        $subject = $template->renderBlock('subject', [ 'data' => $data ]);
        $textBody = $template->renderBlock('body_text', [ 'data' => $data ]);
        $htmlBody = $template->renderBlock('body_html', [ 'data' => $data ]);

        $message = (new \Swift_Message($subject))
            ->setFrom('burckhardtd@geschichte.hu-berlin.de')
            ->setTo($data['to'])
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
            // var_dump($e->getMessage());

            return false;
        }
    }

    /**
     * @Route("/register", name="user_registration")
     */
    public function registerAction(Request $request, UserPasswordEncoderInterface $passwordEncoder)
    {
        // 1) build the form
        $user = new User();
        $form = $this->createForm(UserType::class, $user);

        // 2) handle the submit (will only happen on POST)
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // 3) Encode the password (you could also do this via Doctrine listener)
            $password = $passwordEncoder->encodePassword($user, $user->getPlainPassword());
            $user->setPassword($password);

            // 4) save the User!
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($user);
            $entityManager->flush();

            // set a "flash" success message for the user
            $this->addFlash('success', 'Your account has been created. You can now login to the site.');

            return $this->redirectToRoute('login');
        }

        return $this->render('User/register.html.twig', [
            'form' => $form->createView(),
        ]);
    }


    /**
     * @Route("/forgot-password", name="user_recoverpassword")
     */
    public function sendRecoverAction(Request $request, UrlGeneratorInterface $router, AuthenticationUtils $authenticationUtils = null)
    {
        // 1) build the form
        $form = $this->createForm(\AppBundle\Form\Type\UserRecoverType::class);

        // 2) handle the submit (will only happen on POST)
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $data = $form->getData();

                if (!empty($data['email'])) {
                    $email = trim($data['email']);

                    $userRepo = $this->getDoctrine()->getRepository('AppBundle:User');
                    $user = $userRepo->loadUserByUsername(trim($email));
                    if (!is_null($user)) {
                        $user->setGeneratedConfirmationToken();

                        $entityManager = $this->getDoctrine()->getManager();
                        $entityManager->persist($user);
                        $entityManager->flush();

                        $success = $this->sendMessage('user/reset-password.twig', [
                            'to' => $email,
                            'resetUrl' => $router->generate('user_checkrecoverpassword', [ 'username' => $user->getUsername(), 'token' => $user->getConfirmationToken() ], UrlGeneratorInterface::ABSOLUTE_URL),
                        ]);

                        if ($success) {
                            return $this->render('User/recover-success.html.twig', [
                                'to' => $email,
                            ]);
                        }

                        return $this->render('User/recover-fail.html.twig', [
                            'to' => $email,
                        ]);
                    }
                    else {
                        $error = new \Symfony\Component\Form\FormError("We couldn't find a matching account");
                        $form->get('email')->addError($error);
                    }
                }
            }
        }
        else {
            if (is_null($authenticationUtils)) {
                $authenticationUtils = $this->get('security.authentication_utils');
            }

            // last username entered by the user
            $lastUsername = $authenticationUtils->getLastUsername();
            $form->get('email')->setData($lastUsername);
        }

        return $this->render('User/recover.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/reset-password/{username}/{token}", name="user_checkrecoverpassword")
     */
    public function checkRecoverAction(Request $request, UserPasswordEncoderInterface $passwordEncoder, $username, $token)
    {
        $userRepo = $this->getDoctrine()->getRepository('AppBundle:User');

        $user = null;

        if (!empty($username)) {
            $user = $userRepo->loadUserByUsername($username);
        }

        if (is_null($user)) {
            $this->addFlash('warning', 'You used a wrong or outdated recover link. Please request a new one.');

            return $this->redirectToRoute('user_recoverpassword');
        }

        if (empty($token) || $token !== $user->getConfirmationToken()) {
            $this->addFlash('warning', 'You used a wrong or outdated recover link. Please request a new one.');

            return $this->redirectToRoute('user_recoverpassword');
        }

        $form = $this->createForm(\AppBundle\Form\Type\UserResetType::class, $user);

        // 2) handle the submit (will only happen on POST)
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                // 3) Encode the password (you could also do this via Doctrine listener)
                $password = $passwordEncoder->encodePassword($user, $user->getPlainPassword());
                $user->setPassword($password);

                // 4) save the User!
                $entityManager = $this->getDoctrine()->getManager();
                $entityManager->persist($user);
                $entityManager->flush();

                // set a "flash" success message for the user
                $this->addFlash('success', 'Your new password has been set. You can now use it to login to the site.');

                return $this->redirectToRoute('login');
            }
        }

        return $this->render('User/reset.html.twig', [
            'username' => $username,
            'form' => $form->createView(),
        ]);
    }
}
