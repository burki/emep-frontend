<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Annotation\Route;

use AppBundle\Utils\SearchListPagination;

/**
 * Base Controller for home-page and other blog- and info-pages
 */
class DefaultController
extends BaseController
{
    protected function instantiateWpApiClient()
    {
        try {
            /* the following can fail */
            $url = $this->getParameter('app.wp-rest.url');

            if (empty($url)) {
                return false;
            }

            $client = new \Vnn\WpApiClient\WpClient(
                new \Vnn\WpApiClient\Http\GuzzleAdapter(new \GuzzleHttp\Client()),
                    $url);
            $client->setCredentials(new \Vnn\WpApiClient\Auth\WpBasicAuth($this->getParameter('app.wp-rest.user'),
                                                                          $this->getParameter('app.wp-rest.password')));

            return $client;
        }
        catch (\InvalidArgumentException $e) {
            ; // ignore
        }

        return false;
    }

    /**
     * @Route("/", name="home", options={"sitemap" = true})
     */
    public function homeAction(Request $request, UrlGeneratorInterface $urlGenerator)
    {
        $connection = $this->getDoctrine()->getManager()->getConnection();

        $counts = [];
        foreach ([ 'ItemExhibition' , 'Exhibition', 'Venue', 'Organizer', 'Person', 'Place' ] as $entity) {
            switch ($entity) {
                case 'ItemExhibition':
                    $listBuilder = new \AppBundle\Utils\ItemExhibitionListBuilder($connection, $request, $urlGenerator, []);
                    break;

                case 'Exhibition':
                    $listBuilder = new \AppBundle\Utils\ExhibitionListBuilder($connection, $request, $urlGenerator, []);
                    break;

                case 'Venue':
                    $listBuilder = new \AppBundle\Utils\VenueListBuilder($connection, $request, $urlGenerator, []);
                    break;

                case 'Organizer':
                    $listBuilder = new \AppBundle\Utils\OrganizerListBuilder($connection, $request, $urlGenerator, []);
                    break;

                case 'Person':
                    $listBuilder = new \AppBundle\Utils\PersonListBuilder($connection, $request, $urlGenerator, []);
                    break;

                case 'Place':
                    $listBuilder = new \AppBundle\Utils\PlaceListBuilder($connection, $request, $urlGenerator, []);
                    break;
            }

            $listPagination = new SearchListPagination($listBuilder);

            $counts[$entity] = $listPagination->getTotal();
        }

        // latest blog posts
        $client = $this->instantiateWpApiClient();

        $posts = [];
        $numPosts = 3;
        $hasMore = false;
        if (false !== $client) {
            try {
                $posts = $client->posts()->get(null, [
                    'per_page' => $numPosts + 1,
                ]);

                if (count($posts) > $numPosts) {
                    $post = $posts[$numPosts];
                    $hasMore = !empty($post['slug']) ? $post['slug'] : true;
                    unset($posts[$numPosts]);
                }

                foreach ($posts as $key => $post) {
                    $mediaId = $post['featured_media'];
                    $media = $client->media()->get($mediaId);
                    $mediaUrl = $media['media_details']['sizes']['onepress-small'];
                    $posts[$key]['media_url'] = $mediaUrl;
                }
            }
            catch (\Exception $e) {
                // var_dump($e);
                ; // ignore
            }
        }

        return $this->render('Default/index.html.twig', [
            'counts' => $counts,
            'posts' => $posts,
            'hasMore' => $hasMore,
            'pageMeta' => [
                'description' => 'This open-access database enables detailed researches into info extracted from exhibition catalogues. It covers as comprehensively as possible the European Continent for the years between 1905 and 1915 – a crucial moment in the history of Avant-gardes.',
            ],
        ]);
    }
}
