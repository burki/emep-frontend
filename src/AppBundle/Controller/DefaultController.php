<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Annotation\Route;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use AppBundle\Utils\SearchListPagination;

/**
 *
 */
class DefaultController
extends Controller
{
    protected function instantiateWpApiClient()
    {
        /* check if we have settings for wp-rest */
        $url = $this->container->hasParameter('app.wp-rest.url')
            ? $this->getParameter('app.wp-rest.url') : null;

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

    /**
     * @Route("/", name="home")
     */
    public function indexAction(Request $request, UrlGeneratorInterface $urlGenerator)
    {
        $connection = $this->getDoctrine()->getEntityManager()->getConnection();

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

        $client = $this->instantiateWpApiClient();

        $posts = [];
        if (false !== $client) {
            try {
                $posts = $client->posts()->get(null, [
                    'per_page' => 3,
                ]);

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
            'posts' => $posts
        ]);
    }
}
