<?php

/**
 *
 * Shared methods
 */

namespace AppBundle\Entity;

trait AddressesTrait
{
    static $ADDRESS_KEYS = [
        'from', 'until', 'id_exhibition', 'note',
        'address', 'place',
        'street', 'zip', 'country',
        'geo',
    ];

    static function splitAddresses($entries)
    {
        $ret = [];
        if (empty($entries)) {
            return $ret;
        }

        foreach ($entries as $entry) {
            $idx = empty($ret) ? 0 : count($ret['place']);
            foreach (self::$ADDRESS_KEYS as $key) {
                $val = '';
                if (array_key_exists($key, $entry)) {
                    $val = $entry[$key];
                }
                $ret[$key][$idx] = $val;
            }
        }

        return $ret;
    }

    /*
     *
     *
     */
    function buildAddresses($entries, $showCountry = false, $filterExhibition = null)
    {
        $addresses = self::splitAddresses($entries);
        if (empty($addresses)) {
            return [];
        }

        $numAddresses = empty($addresses) ? 0 : count($addresses['place']);
        $fields = [];
        for ($i = 0; $i < $numAddresses; $i++) {
            if (!is_null($filterExhibition)) {
                if (!array_key_exists('id_exhibition', $addresses)
                    || !array_key_exists($i, $addresses['id_exhibition'])
                    || !is_array($addresses['id_exhibition'][$i])
                    || !in_array($filterExhibition, $addresses['id_exhibition'][$i]))
                {
                    continue;
                }
            }

            $lines = [];

            if ($showCountry) {
                $range = join('-', [ $addresses['from'][$i], $addresses['until'][$i] ]);

                if ('-' != $range) {
                    $lines[] = $range;
                }
            }

            foreach ([ [ 'address', 'place' ],
                       $showCountry ? [ 'street', 'zip', 'country' ] : [ 'street', 'zip' ],
                       // [ 'geo' ],
                       ] as $keys)
            {
                $parts = [];
                foreach ($keys as $key) {
                    if (!empty($addresses[$key][$i])) {
                        $parts[] = $addresses[$key][$i];
                    }
                }
                if (!empty($parts)) {
                    $lines[] = join(', ', $parts);
                }
            }

            if (!empty($addresses['note'][$i])) {
                $lines[] = '[' . $addresses['note'][$i] . ']';
            }

            if (!empty($lines)) {
                $fields[] = [ 'info' => join("\n", $lines) ];
            }
        }

        return $fields;
    }

    protected function buildOgLocale()
    {
        $locale = $this->get('request_stack')->getCurrentRequest()->getLocale();

        switch ($locale) {
            case 'en':
                $append = 'US';
                break;

            default:
                $append = strtoupper($locale);

        }
        return implode('_', [ $locale, $append ]);
    }

    /**
     * Build og:* meta-tags for sharing on FB
     *
     * Debug through https://developers.facebook.com/tools/debug/sharing/
     *
     */
    public function buildOg($entity, $routeName, $routeParams = [])
    {
        $translator = $this->container->get('translator');
        $twig = $this->container->get('twig');
        $globals = $twig->getGlobals();

        if (empty($routeParams)) {
            $routeParams = [ 'id' => $entity->getId() ];
        }

        $og = [
            'og:site_name' => $translator->trans($globals['siteName']),
            'og:locale' => $this->buildOgLocale(),
            'og:url' => $this->generateUrl($routeName, $routeParams, true),
        ];

        /*
        foreach ($app['app_allowed_locales'] as $locale) {
            $locale_full = $this->buildOgLocale($request, $app, $locale);
            if ($locale_full != $og['og:locale']) {
                if (!isset($og['og:locale:alternate'])) {
                    $og['og:locale:alternate'] = [];
                }
                $og['og:locale:alternate'][] = $locale_full;
            }
        }
        */

        $request = $this->get('request_stack')->getCurrentRequest();

        $baseUri = $request->getUriForPath('/');

        if ($entity instanceof \AppBundle\Entity\OgSerializable) {
            $ogEntity = $entity->ogSerialize($request->getLocale(), $baseUri);
            if (isset($ogEntity)) {
                $og = array_merge($og, $ogEntity);
                if (array_key_exists('article:section', $og)) {
                    $og['article:section'] = $translator->trans($og['article:section']);
                }
            }
        }

        if (empty($og['og:image'])) {
            // this one is required
            if ($entity instanceof \AppBundle\Entity\Person) {
                $og['og:image'] = $baseUri . 'img/icon/placeholder_person.png';
            }
            else if ($entity instanceof \AppBundle\Entity\Bibitem) {
                $og['og:image'] = $baseUri . 'img/icon/placeholder_bibitem.png';
            }
        }

        return $og;
    }

    /**
     *
     * Build twitter:* meta-tags for Twitter Decks
     * This can be tested through
     *  http://cards-dev.twitter.com/validator
     *
     */
    public function buildTwitter($entity, $routeName, $routeParams = [], $params = [])
    {
        $twitter = [];

        $twig = $this->container->get('twig');
        $globals = $twig->getGlobals();
        if (empty($globals['twitterSite'])) {
            return $twitter;
        }

        // we don't put @ in parameters.yaml since @keydocuments looks like a service
        $twitter['twitter:card'] = 'summary';
        $twitter['twitter:site'] = '@' . $globals['twitterSite'];

        $request = $this->get('request_stack')->getCurrentRequest();
        if ($entity instanceof \AppBundle\Entity\TwitterSerializable) {
            $baseUri = $request->getUriForPath('/');
            $twitterEntity = $entity->twitterSerialize($request->getLocale(), $baseUri, $params);
            if (isset($twitterEntity)) {
                $twitter = array_merge($twitter, $twitterEntity);
            }
        }

        return $twitter;
    }
}
