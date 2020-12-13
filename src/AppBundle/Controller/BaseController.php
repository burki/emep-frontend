<?php

/**
 * Shared methods for Controllers
 */

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use Cocur\Slugify\SlugifyInterface;

abstract class BaseController
extends AbstractController
{
    protected $kernel;
    protected $translator;
    protected $slugify;
    private $globals = null;

    public function __construct(KernelInterface $kernel,
                                TranslatorInterface $translator,
                                SlugifyInterface $slugify)
    {
        $this->kernel = $kernel;
        $this->translator = $translator;
        $this->slugify = $slugify;
    }

    protected function getGlobal($key)
    {
        if (is_null($this->globals)) {
            $this->globals = $this->get('twig')->getGlobals();
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
