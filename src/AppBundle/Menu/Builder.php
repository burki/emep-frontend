<?php
// src/AppBundle/Menu/Builder.php

// registered in services.yml to pass $securityContext and $requestStack
// see http://symfony.com/doc/current/bundles/KnpMenuBundle/index.html
namespace AppBundle\Menu;

use Knp\Menu\FactoryInterface;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\PhpBridgeSessionStorage;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class Builder
{
    private $factory;
    private $authorizationChecker;
    private $requestStack;

    /**
     * @param FactoryInterface $factory
     * @param RequestStack $requestStack
     *
     * Add any other dependency you need
     */
    public function __construct(FactoryInterface $factory,
                                AuthorizationCheckerInterface $authorizationChecker,
                                RequestStack $requestStack)
    {
        $this->factory = $factory;
        $this->authorizationChecker = $authorizationChecker;
        $this->requestStack = $requestStack;
    }

    public function createTopMenu(array $options)
    {
        $menu = $this->factory->createItem('root');
        if (array_key_exists('position', $options) && 'footer' == $options['position']) {
            $menu->setChildrenAttributes([ 'id' => 'menu-top-footer', 'class' => 'small' ]);
        }
        else {
            $menu->setChildrenAttributes([ 'id' => 'menu-top', 'class' => 'list-inline' ]);
        }

        $menu->addChild('contact', [
            'label' => 'Contact', 'route' => 'contact',
        ]);

        return $menu;
    }

    public function createFooterMainMenu(array $options)
    {
        $options['position'] = 'footer';

        return $this->createMainMenu($options);
    }

    public function createMainMenu(array $options)
    {
        try {
            $loggedIn = $this->authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY');
        }
        catch (\Exception $e) {
            // can happen on error pages
            $loggedIn = false;
        }

        $showWorks = $loggedIn || !empty($_SESSION['user']);

        // for translation, see http://symfony.com/doc/master/bundles/KnpMenuBundle/i18n.html
        $menu = $this->factory->createItem('home', [
            'label' => 'Home',
            'route' => 'home',
        ]);
        $menu->setChildrenAttributes([ 'id' => 'menu-main', 'class' => 'nav-menu w-nav-menu', 'role' => 'navigation' ]);


        $menu->addChild('Home', [ 'route' => 'home' ]); // maybe create a view for view data


        $menu->addChild('Search Lists', [ 'route' => 'exhibition' ]); // maybe create a view for view data
        $menu['Search Lists']->addChild('Exhibitions', [
            'route' => 'exhibition',
        ]);

        $menu['Search Lists']->addChild('Artists', [
            'route' => 'person',
        ]);

        $menu['Search Lists']->addChild('Venues', [
            'route' => 'venue',
        ]);

        $menu['Search Lists']->addChild('Organizing Bodies', [
            'route' => 'organizer',
        ]);





        $menu->addChild('Advanced Search', [ 'route' => 'search' ]);


        /* $menu['View Data']->addChild('Places', [
            'route' => 'place',
        ]); */



        /*

        if ($showWorks) {
            $menu->addChild('Works', [ 'route' => 'item' ]);
            $menu['Works']->addChild('List by Artist', [
                'route' => 'item',
            ]);
            $menu['Works']->addChild('List by Exhibition', [
                'route' => 'item-by-exhibition',
            ]);
            $menu['Works']->addChild('List by Style', [
                'route' => 'item-by-style',
            ]);
            $menu['Works']->addChild('List by Style', [
                'route' => 'item-by-style',
            ]);
            $menu['Works']->addChild('Exhibition Map', [
                'route' => 'item-by-place',
            ]);
            $menu['Works']->addChild('Stats by Artist', [
                'route' => 'item-by-person',
            ]);
        } */

        $menu->addChild('Info', [ 'route' => 'project' ]); // maybe create a view for view data
        $menu['Info']->addChild('Our Project', [
            'route' => 'project',
        ]);
        $menu['Info']->addChild('Using the Database', [
            'route' => 'using',
        ]);

        $menu['Info']->addChild('Cooperating Institutions', [
            'route' => 'cooperating',
        ]);


        $menu['Info']->addChild('Holding Institutions', [
            'route' => 'holder',
        ]);


        $menu->addChild('My Data', [ 'route' => 'my-data' ]);


        /*
         *
         * the following didn't work and is now handled
         * by the RequestVoter registered in services, see
         * https://gist.github.com/nateevans/9958390
         *
         * services:
         *  app.menu_request_voter:
         *      class: AppBundle\Menu\RequestVoter
         *      arguments: [ "@request_stack" ]
         *      tags:
         *         - { name: knp_menu.voter }

        $uriCurrent = $this->requestStack->getCurrentRequest()->getRequestUri();

        // create the iterator
        $itemIterator = new \Knp\Menu\Iterator\RecursiveItemIterator($menu);

        // iterate recursively on the iterator
        $iterator = new \RecursiveIteratorIterator($itemIterator, \RecursiveIteratorIterator::SELF_FIRST);

        foreach ($iterator as $item) {
            $uri = $item->getUri();
            if (substr($uriCurrent, 0, strlen($uri)) === $uri) {
                $item->setCurrent(true);
                break;
            }
        }
        */

        return $menu;
    }

    public function breadcrumbMenu(FactoryInterface $factory, array $options)
    {
        $menu = $this->mainMenu($factory, $options + [ 'position' => 'breadcrumb' ]);

        // try to return the active item
        $currentRoute = 'home'; /* $this->get('request_stack')->getCurrentRequest()
                            ->get('_route'); */
        if ('home' == $currentRoute) {
            return $menu;
        }

        // first level
        $item = $menu[$currentRoute];
        if (isset($item)) {
            return $item;
        }

        // additional routes
        switch ($currentRoute) {
            case 'about':
            case 'terms':
            case 'contact':
                $toplevel = $this->topMenu($factory, []);
                $item = $toplevel[$currentRoute];
                $item->setParent(null);
                $item = $menu->addChild($item);
                break;

            case 'person':
            case 'person-by-ulan':
            case 'person-by-gnd':
                $item = $menu['_lookup']['person-index'];
                $item = $item->addChild($currentRoute, [ 'label' => 'Detail', 'uri' => '#' ]);
                break;

            case 'place':
            case 'place-by-tgn':
                $item = $menu['_lookup']['place-index'];
                $item = $item->addChild($currentRoute, [ 'label' => 'Detail', 'uri' => '#' ]);
                break;

            case 'location':
            // case 'location-by-gnd':
                $item = $menu['_lookup']['organization-index'];
                $item = $item->addChild($currentRoute, [ 'label' => 'Detail', 'uri' => '#' ]);
                break;

            case 'bibliography':
                $item = $menu['_lookup']['bibliography-index'];
                $item = $item->addChild($currentRoute, [ 'label' => 'Detail', 'uri' => '#' ]);
                break;

            case 'search-index':
                $item = $menu->addChild($currentRoute, [ 'label' => 'Search' ]);
                break;

           default:
                if (!is_null($currentRoute)) {
                    var_dump($currentRoute);
                }
        }

        if (isset($item)) {
            $item->setCurrent(true);
            return $item;
        }

        return $menu;
    }
}
