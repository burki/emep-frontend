<?php

/**
 *
 * Shared methods to build Facebook OpenGraph
 *  https://developers.facebook.com/docs/sharing/webmasters#markup
 * and Twitter Cards meta-tags
 *  https://dev.twitter.com/cards/overview
 */

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;

trait SharingBuilderTrait
{
    /*
     * transforms en -> en_US and de -> de_DE
     *
     */
    protected function buildOgLocale()
    {
        $locale = $this->translator->getLocale();

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
    public function buildOg(Request $request, $entity, $routeName, $routeParams = [])
    {
        $twig = $this->container->get('twig');

        if (empty($routeParams)) {
            $routeParams = [ 'id' => $entity->getId() ];
        }

        $og = [
            'og:site_name' => $this->translator->trans($this->getGlobal('siteName')),
            'og:locale' => $this->buildOgLocale(),
            'og:url' => $this->generateUrl($routeName, $routeParams,
                                           \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL),
        ];

        $baseUri = $request->getUriForPath('/');

        if ($entity instanceof \AppBundle\Entity\OgSerializable) {
            $ogEntity = $entity->ogSerialize($request->getLocale(), $baseUri);
            if (isset($ogEntity)) {
                $og = array_merge($og, $ogEntity);
                if (array_key_exists('article:section', $og)) {
                    $og['article:section'] = $this->translator->trans($og['article:section']);
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
