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


        foreach ($posts as $key => $post) {
            $mediaId = $post['featured_media'];
            if (!empty($mediaId)) {
                $media = $client->media()->get($mediaId);
                $mediaUrl = $media['media_details']['sizes']['onepress-small'];
                $posts[$key]['media_url'] = $mediaUrl;
            }
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

        if (!empty($post['featured_media'])) {
            $media = $client->media()->get($post['featured_media']);
            if (!empty($media['media_details']['sizes']['onepress-small'])) {
                $post['media_url'] = $media['media_details']['sizes']['onepress-small'];
            }

            $post['content']['rendered'] = preg_replace_callback("#(<span class='easy-footnote'><a href='(.*?)'\s*title='(.*?)'>)#",
                                                                 function ($matches) {
                                                                    return sprintf('<a data-toggle="tooltip" href="#" title="%s">',
                                                                                  $matches[3]);
                                                                 }, $post['content']['rendered']);
        }

        return $this->render('Blog/detail.html.twig', [
            'post' => $post,
        ]);
    }
}
