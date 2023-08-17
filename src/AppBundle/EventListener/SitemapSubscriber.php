<?php

namespace AppBundle\EventListener;

/**
 * See https://github.com/prestaconcept/PrestaSitemapBundle/blob/master/Resources/doc/4-dynamic-routes-usage.md
 */

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

use Doctrine\ORM\EntityManagerInterface;

use Presta\SitemapBundle\Event\SitemapPopulateEvent;
use Presta\SitemapBundle\Service\UrlContainerInterface;
use Presta\SitemapBundle\Sitemap\Url\UrlConcrete;

class SitemapSubscriber
implements EventSubscriberInterface
{
    /**
     * @var UrlGeneratorInterface
     */
    private $urlGenerator;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var ParameterBagInterface
     */
    private $params;

    /**
     * @param UrlGeneratorInterface $urlGenerator
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(UrlGeneratorInterface $urlGenerator,
                                EntityManagerInterface $entityManager,
                                ParameterBagInterface $params)
    {
        $this->urlGenerator = $urlGenerator;
        $this->entityManager = $entityManager;
        $this->params = $params;
    }

    /**
     * @inheritdoc
     */
    public static function getSubscribedEvents()
    {
        return [
            SitemapPopulateEvent::ON_SITEMAP_POPULATE => 'populate',
        ];
    }

    private function registerFromListBuilder(UrlContainerInterface $urls,
                                             \AppBundle\Utils\SearchListBuilder $listBuilder, $section,
                                             $routeParamsBuilder = null): void
    {
        foreach ($listBuilder->query()->execute()->fetchAll() as $row) {
            list($route, $routeParams) = !is_null($routeParamsBuilder)
                ? $routeParamsBuilder($row)
                : [ $section, [ 'id' => $row['id'] ] ];

            $urls->addUrl(
                new UrlConcrete(
                    $this->urlGenerator->generate(
                        $route,
                        $routeParams,
                        UrlGeneratorInterface::ABSOLUTE_URL
                    ),
                    new \DateTime($row['changedAt']),
                    UrlConcrete::CHANGEFREQ_WEEKLY,
                    0.7
                ),
                $section
            );
        }
    }

    /**
     * @param SitemapPopulateEvent $event
     */
    public function populate(SitemapPopulateEvent $event): void
    {
        $this->registerExhibitionUrls($event->getUrlContainer());
        $this->registerVenueUrls($event->getUrlContainer());
        $this->registerOrganizerUrls($event->getUrlContainer());
        $this->registerPersonUrls($event->getUrlContainer());
        $this->registerPlaceUrls($event->getUrlContainer());
        $this->registerBlogUrls($event->getUrlContainer());
        $this->registerHolderUrls($event->getUrlContainer());
    }

    /**
     * @param UrlContainerInterface $urls
     */
    public function registerExhibitionUrls(UrlContainerInterface $urls): void
    {
        $listBuilder = new \AppBundle\Utils\ExhibitionListBuilder($this->entityManager->getConnection(),
                                                                  null, $this->urlGenerator, [], 'sitemap');

        $this->registerFromListBuilder($urls, $listBuilder, 'exhibition');
    }

    /**
     * @param UrlContainerInterface $urls
     */
    public function registerVenueUrls(UrlContainerInterface $urls): void
    {
        $listBuilder = new \AppBundle\Utils\VenueListBuilder($this->entityManager->getConnection(),
                                                             null, $this->urlGenerator, [], 'sitemap');

        $this->registerFromListBuilder($urls, $listBuilder, 'location');
    }

    /**
     * @param UrlContainerInterface $urls
     */
    public function registerOrganizerUrls(UrlContainerInterface $urls): void
    {
        $listBuilder = new \AppBundle\Utils\OrganizerListBuilder($this->entityManager->getConnection(),
                                                                 null, $this->urlGenerator, [], 'sitemap');

        $this->registerFromListBuilder($urls, $listBuilder, 'organizer');
    }

    /**
     * @param UrlContainerInterface $urls
     */
    public function registerPersonUrls(UrlContainerInterface $urls): void
    {
        $personRepo = $this->entityManager
                ->getRepository('AppBundle\Entity\Person');

        $query = $personRepo
                ->createQueryBuilder('P')
                ->select('P.id, P.ulan, P.gnd, P.changedAt')
                ->where('P.status >= 0')
                ->orderBy('P.id')
                ->getQuery()
                ;

        foreach ($query->execute() as $person) {
            $route = 'person';
            $routeParams = [ 'id' => $person['id'] ];
            foreach ([ 'ulan', 'gnd' ] as $key) {
                if (!empty($person[$key])) {
                    $route = 'person-by-' . $key;
                    $routeParams = [ $key => $person[$key] ];
                    break;
                }
            }

            $urls->addUrl(
                new UrlConcrete(
                    $this->urlGenerator->generate(
                        $route,
                        $routeParams,
                        UrlGeneratorInterface::ABSOLUTE_URL
                    ),
                    $person['changedAt'],
                    UrlConcrete::CHANGEFREQ_WEEKLY,
                    0.7
                ),
                'person'
            );
        }
    }

    /**
     * @param UrlContainerInterface $urls
     */
    public function registerPlaceUrls(UrlContainerInterface $urls): void
    {
        $listBuilder = new \AppBundle\Utils\PlaceListBuilder($this->entityManager->getConnection(),
                                                             null, $this->urlGenerator, [], 'sitemap');

        $this->registerFromListBuilder($urls, $listBuilder, 'place',
                                       function ($row) { return [ 'place-by-tgn', [ 'tgn' => $row['tgn']] ]; });
    }

    /**
     * @param UrlContainerInterface $urls
     */
    public function registerBlogUrls(UrlContainerInterface $urls): void
    {
        /* check if we have settings for wp-rest */
        $url = $this->params->has('app.wp-rest.url')
            ? $this->params->get('app.wp-rest.url') : null;

        if (empty($url)) {
            return;
        }

        $client = new \Vnn\WpApiClient\WpClient(
            new \Vnn\WpApiClient\Http\GuzzleAdapter(new \GuzzleHttp\Client()),
                $url);
        $client->setCredentials(new \Vnn\WpApiClient\Auth\WpBasicAuth($this->params->get('app.wp-rest.user'),
                                                                      $this->params->get('app.wp-rest.password')));
        $posts = [];

        if (false !== $client) {
            try {
                $posts = $client->posts()->get(null, [
                    'per_page' => 100,
                ]);
            }
            catch (\Exception $e) {
                // var_dump($e);
                ; // ignore
            }
        }

        $route = 'blog';
        foreach ($posts as $key => $post) {
            $urls->addUrl(
                new UrlConcrete(
                    $this->urlGenerator->generate(
                        $route,
                        [ 'slug' => $post['slug'] ],
                        UrlGeneratorInterface::ABSOLUTE_URL
                    ),
                    new \DateTime($post['modified_gmt']),
                    UrlConcrete::CHANGEFREQ_MONTHLY,
                    0.7
                ),
                'blog'
            );
        }
    }

    /**
     * @param UrlContainerInterface $urls
     */
    public function registerHolderUrls(UrlContainerInterface $urls): void
    {
        $listBuilder = new \AppBundle\Utils\HolderListBuilder($this->entityManager->getConnection(),
                                                              null, $this->urlGenerator, [], 'sitemap');

        $this->registerFromListBuilder($urls, $listBuilder, 'holder');
    }
}
