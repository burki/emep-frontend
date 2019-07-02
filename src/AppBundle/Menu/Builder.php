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
     * @param AuthorizationCheckerInterface $authorizationChecker
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

        // $menu->addChild('Home', [ 'route' => 'home' ]);

        $menu->addChild($toplevel = 'Browse', [ 'route' => 'exhibition-index' ]); // maybe create a view for view data

        $menu[$toplevel]->addChild('Exhibitions', [
            'route' => 'exhibition-index',
        ]);

        $menu[$toplevel]->addChild('Artists', [
            'route' => 'person-index',
        ]);

        $menu[$toplevel]->addChild('Venues', [
            'route' => 'venue-index',
        ]);

        $menu[$toplevel]->addChild('Organizing Bodies', [
            'route' => 'organizer-index',
        ]);

        $menu[$toplevel]->addChild('Exhibiting Cities', [
            'route' => 'place-index',
        ]);

        $menu->addChild('Advanced Search', [ 'route' => 'search-index' ]);

        $menu->addChild('Blog', [ 'route' => 'blog-index' ]);

        $menu->addChild('Info', [ 'route' => 'project' ]); // maybe create a view for view data

        $menu['Info']->addChild('Our Project', [
            'route' => 'project',
        ]);

        $menu['Info']->addChild('Team', [
            'route' => 'team',
        ]);

        $menu['Info']->addChild('Content of the Database', [
            'route' => 'database',
        ]);

        $menu['Info']->addChild('Cooperating Institutions', [
            'route' => 'cooperating',
        ]);

        $menu['Info']->addChild('Holding Institutions', [
            'route' => 'holder',
        ]);

        $menu['Info']->addChild('Publications', [
            'route' => 'publications',
        ]);

        $menu->addChild('My Data', [ 'route' => 'my-data' ]);

        return $menu;
    }

    public function breadcrumbMenu(FactoryInterface $factory, array $options)
    {
        return $this->mainMenu($factory, $options + [ 'position' => 'breadcrumb' ]);
    }
}
