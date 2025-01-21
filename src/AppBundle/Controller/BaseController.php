<?php

/**
 * Shared methods for Controllers
 */

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use Cocur\Slugify\SlugifyInterface;
use Doctrine\Persistence\ManagerRegistry;

abstract class BaseController
extends AbstractController
{
    protected $kernel;
    protected $translator;
    private $doctrine;
    protected $slugify;
    private $twig;

    private $globals = null;

    public function __construct(KernelInterface $kernel,
                                TranslatorInterface $translator,
                                ManagerRegistry $doctrine,
                                SlugifyInterface $slugify,
                                \Twig\Environment $twig)
    {
        $this->kernel = $kernel;
        $this->translator = $translator;
        $this->doctrine = $doctrine;
        $this->slugify = $slugify;
        $this->twig = $twig;
    }

    /**
     * Add deprecated method for forward compatibility
     */
    protected function getDoctrine(): ManagerRegistry
    {
        return $this->doctrine;
    }

    /**
     * Add deprecated method for forward compatibility
     */
    protected function getTwig(): \Twig\Environment
    {
        return $this->twig;
    }

    /**
     * Get a global twig variable by $key
     */
    protected function getGlobal($key)
    {
        if (is_null($this->globals)) {
            $this->globals = $this->twig->getGlobals();
        }

        return array_key_exists($key, $this->globals)
            ? $this->globals[$key] : null;
    }

    protected function expandSaveSearchRoute($route)
    {
        if (!preg_match('/\-index$/', $route)) {
            // we only save the base, so append the -index
            $route .= '-index';
        }

        return $route;
    }
}
