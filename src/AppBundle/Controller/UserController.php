<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;


use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

/**
 *
 */
class UserController
extends Controller
{
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
}
