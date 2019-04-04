<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

/**
 *
 */
class BlogController
extends DefaultController
{
    /**
     * @Route("/blog", name="blog-index")
     */
    public function blogIndexAction(Request $request)
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
    public function blogDetailAction(Request $request, $slug)
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

        if (is_null($post)) {
            return $this->redirectToRoute('blog-index');
        }

        return $this->render('Blog/detail.html.twig', [
            'post' => $post,
        ]);
    }
}
