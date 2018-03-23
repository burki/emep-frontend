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
        $loggedIn = $this->authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY');
        $showWorks = $loggedIn || !empty($_SESSION['user']);

        // for translation, see http://symfony.com/doc/master/bundles/KnpMenuBundle/i18n.html
        $menu = $this->factory->createItem('home', [
            'label' => 'Home',
            'route' => 'home',
        ]);
        $menu->setChildrenAttributes([ 'id' => 'menu-main', 'class' => 'nav navbar-nav' ]);

        $menu->addChild('Exhibitions', ['route' => 'exhibition']);
        $menu['Exhibitions']->addChild('List', [
            'route' => 'exhibition',
        ]);
        $menu['Exhibitions']->addChild('Map', [
            'route' => 'exhibition-by-place',
        ]);
        $menu['Exhibitions']->addChild('Chart: By Month', [
            'route' => 'exhibition-by-month',
        ]);
        $menu['Exhibitions']->addChild("Chart: Artists' Nationality", [
            'route' => 'exhibition-nationality',
        ]);

        $menu->addChild('Venues', ['route' => 'location']);
        $menu['Venues']->addChild('List', [
            'route' => 'location',
        ]);
        $menu['Venues']->addChild('Map', [
            'route' => 'location-by-place',
        ]);

        $menu->addChild('Artists', ['route' => 'person']);
        $menu['Artists']->addChild('List', [
            'route' => 'person',
        ]);
        $menu['Artists']->addChild('Map: Birth/Death Place', [
            'route' => 'person-by-place',
        ]);
        $menu['Artists']->addChild('Chart: Birth/Death', [
            'route' => 'person-by-year',
        ]);
        $menu['Artists']->addChild('Chart: Exhibiting Age', [
            'route' => 'person-exhibition-age',
        ]);
        $menu['Artists']->addChild('Chart: Number of Exhibitions', [
            'route' => 'person-distribution',
        ]);
        $menu['Artists']->addChild("Chart: Popularity according to Wikipedia", [
            'route' => 'person-popularity',
        ]);

        if ($showWorks) {
            $menu->addChild('Works', ['route' => 'item']);
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
        }

        $menu->addChild('Places', ['route' => 'place']);
        $menu['Places']->addChild('List', [
            'route' => 'place',
        ]);
        $menu['Places']->addChild('Map', [
            'route' => 'place-map',
        ]);

        // find the matching parent
        // TODO: maybe use a voter, see https://gist.github.com/nateevans/9958390
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
