<?php
// src/AppBundle/Twig/AppExtension.php

/**
 * see http://symfony.com/doc/current/cookbook/templating/twig_extension.html
 *
 * register in
 *   app/config/services.yml
 * as
 * services:
 *   app.twig_extension:
 *       class: AppBundle\Twig\AppExtension
 *       public: false
 *       tags:
 *           - { name: twig.extension }
 *
 */

namespace AppBundle\Twig;

class AppExtension extends \Twig_Extension
{
    private $translator;
    private $slugifyer;

    public function __construct(\Symfony\Component\Translation\TranslatorInterface $translator = null,
                                $slugifyer = null)
    {
        $this->translator = $translator;
        $this->slugifyer = $slugifyer;
        if (!is_null($slugifyer)) {
            // this should be set in bundlesetup
            $slugifyer->addRule('Ṿ', 'V');
        }
    }

    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('file_exists', 'file_exists'),
            new \Twig_SimpleFunction('daterangeincomplete', [ $this, 'daterangeincompleteFunction' ]),
        ];
    }

    public function getFilters()
    {
        return [
            // general
            new \Twig_SimpleFilter('dateincomplete', [ $this, 'dateincompleteFilter' ]),
            new \Twig_SimpleFilter('datedecade', [ $this, 'datedecadeFilter' ]),
            new \Twig_SimpleFilter('epoch', [ $this, 'epochFilter' ]),
            new \Twig_SimpleFilter('prettifyurl', [ $this, 'prettifyurlFilter' ]),

            // appbundle-specific
            new \Twig_SimpleFilter('placeTypeLabel', [ $this, 'placeTypeLabelFilter' ]),
            new \Twig_SimpleFilter('lookupLocalizedTopic', [ $this, 'lookupLocalizedTopicFilter' ]),
            new \Twig_SimpleFilter('glossaryAddRefLink', [ $this, 'glossaryAddRefLinkFilter' ],
                                   [ 'is_safe' => [ 'html' ] ]),
            new \Twig_SimpleFilter('renderCitation', [ $this, 'renderCitation' ],
                                   [ 'is_safe' => [ 'html' ] ]),
        ];
    }

    private function getLocale()
    {
        if (is_null($this->translator)) {
            return 'en';
        }

        return $this->translator->getLocale();
    }

    public function dateincompleteFilter($datestr, $locale = null)
    {
        if (is_null($locale)) {
            $locale = $this->getLocale();
        }

        if (is_object($datestr) && $datestr instanceof \DateTime) {
            $datestr = $datestr->format('Y-m-d');
        }

        return \AppBundle\Utils\Formatter::dateIncomplete($datestr, $locale);
    }

    public function daterangeincompleteFunction($datestrFrom, $datestrUntil, $locale = null)
    {
        if (is_null($locale)) {
            $locale = $this->getLocale();
        }

        if (is_object($datestrFrom) && $datestrFrom instanceof \DateTime) {
            $datestrFrom = $datestrFrom->format('Y-m-d');
        }

        if (is_object($datestrUntil) && $datestrUntil instanceof \DateTime) {
            $datestrUntil = $datestrUntil->format('Y-m-d');
        }

        return \AppBundle\Utils\Formatter::daterangeIncomplete($datestrFrom, $datestrUntil, $locale);
    }

    public function datedecadeFilter($datestr, $locale = null)
    {
        if (is_null($locale)) {
            $locale = $this->getLocale();
        }

        if (is_object($datestr) && $datestr instanceof \DateTime) {
            $datestr = $datestr->format('Y-m-d');
        }

        return \AppBundle\Utils\Formatter::dateDecade($datestr, $locale);
    }

    public function epochFilter($epoch, $class, $locale = null)
    {
        if (is_null($locale)) {
            $locale = $this->getLocale();
        }

        return $this->translator->trans($class, [
            '%epoch%' => $epoch,
            '%century%' => intval($epoch / 100) + 1,
            '%decade%' => $epoch % 100,
        ]);
    }

    public function prettifyurlFilter($url)
    {
        $parsed = parse_url($url);
        if (empty($parsed['host'])) {
            // probably not an url, so return as is;
            return $url;
        }

        return $parsed['host']
            . (!empty($parsed['path']) && '/' !== $parsed['path'] ? $parsed['path'] : '');
    }

    public function lookupLocalizedTopicFilter($topic, $locale = null)
    {
        if (is_null($locale)) {
            $locale = $this->getLocale();
        }
        return \AppBundle\Controller\TopicController::lookupLocalizedTopic($topic, $this->translator, $locale);
    }

    public function glossaryAddRefLinkFilter($description)
    {
        $slugifyer = $this->slugifyer;

        return preg_replace_callback('/\[\[(.*?)\]\]/',
                    function ($matches) use ($slugifyer) {
                       $slug = $label = $matches[1];
                       if (!is_null($slugifyer)) {
                           $slug = $slugifyer->slugify($slug);
                       }
                       return '→ <a href="#' . rawurlencode($slug) . '">'
                         . $label
                         . '</a>';
                    },
                    $description);
    }

    public function renderCitation($encoded)
    {
        $locale = $this->getLocale();

        $path = __DIR__ . '/../Resources/data/csl/jgo-infoclio-de.csl.xml';

        $citeProc = new \AcademicPuma\CiteProc\CiteProc(file_get_contents($path),
                                                        $locale);

        return $citeProc->render(json_decode($encoded));
    }

    public function placeTypeLabelFilter($placeType, $count = 1, $locale = null)
    {
        if (is_null($locale)) {
            $locale = $this->getLocale();
        }

        return \AppBundle\Entity\Place::buildPluralizedTypeLabel($placeType, $count);
    }

    public function getName()
    {
        return 'app_extension';
    }
}
