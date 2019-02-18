<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

/**
 *
 */
class BlogController
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
     * @Route("/blog", name="blog-index")
     */
    public function indexAction(Request $request)
    {

        $posts = [];

        $client = $this->instantiateWpApiClient();

        if (false !== $client) {
            try {
                $posts = $client->posts()->get(null, [
                    'per_page' => 15,
                ]);
            }
            catch (\Exception $e) {
                // var_dump($e);
                ; // ignore
            }
        }

        if (empty($posts)) {
            return $this->redirectToRoute('home');
        }

        return $this->render('Blog/index.html.twig', [
            'posts' => $posts,
        ]);
    }

    /**
     * @Route("/blog/{slug}", name="blog")
     */
    public function detailAction(Request $request, $slug)
    {
        $client = $this->instantiateWpApiClient();

        $post = null;

        if (false !== $client) {
            try {
                $posts = $client->posts()->get(null, [
                    'slug' => $slug,
                ]);
            }
            catch (\Exception $e) {
                // var_dump($e);
                ; // ignore
            }

            if (!empty($posts)) {
                $post = $posts[0];
            }
        }

        if (empty($posts)) {
            return $this->redirectToRoute('blog-index');
        }

        return $this->render('Blog/detail.html.twig', [
            'post' => $post,
        ]);
    }
}
