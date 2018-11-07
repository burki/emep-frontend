<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessBuilder;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

use Cocur\BackgroundProcess\BackgroundProcess;

/**
 *
 */
class GraphController
extends Controller
{
    static function getPhpBin()
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // we don't seem to have a reliable way on windows, so assume it is in same dir as php.ini
            return dirname(php_ini_loaded_file()) . DIRECTORY_SEPARATOR . 'php.exe';;
        }


        $phpFinder = new \Symfony\Component\Process\PhpExecutableFinder;
        if (!$phpPath = $phpFinder->find()) {
            throw new \Exception('The php executable could not be found, add it to your PATH environment variable and try again');
        }

        return $phpPath;
    }

    /**
     * @Route("/person/gdf", name="person-gdf")
     */
    public function gdfAction(Request $request)
    {
        $route = $request->get('_route');

        $exportPath = $this->container->hasParameter('app.export.path')
            ? $this->getParameter('app.export.path')
            : $this->get('kernel')->getProjectDir() . '/../site/htdocs/uploads/export';

        if (false === realpath($exportPath)) {
            throw new \InvalidArgumentException($exportPath . ' does not exist');
        }

        $fname = 'person';

        $fnameFull = realpath($exportPath) . DIRECTORY_SEPARATOR . $fname . '.gdf';
        $fnameLock = realpath($exportPath) . DIRECTORY_SEPARATOR . $fname . '.lock';

        $regenerate = true;

        if (file_exists($fnameFull)) {
            $regenerate = false;
        }

        if ($regenerate || file_exists($fnameLock)) {
            if (!file_exists($fnameLock)) {
                $phpBin = self::getPhpBin();

                $builder = new ProcessBuilder();
                $builder->setPrefix($phpBin);
                $command = $builder->setArguments([
                        realpath(__DIR__ .'/../../../bin/console'),
                        'export:gdf',
                        'person',
                        '--save-export-path'
                    ])
                    ->getProcess()
                    ->getCommandLine();

                $process = new BackgroundProcess($command);
                $process->run();
            }

            $response = new Response();

            $response->setStatusCode(200);
            $url = $this->generateUrl($route, [],
                                      \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL);


            $response->setContent(sprintf('<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8" />
        <meta http-equiv="refresh" content="5;url=%1$s" />
        <title>Redirecting to %1$s</title>
    </head>
    <body>
        Regeneration of %2$s is currently in progress. Please wait.
    </body>
</html>',
            htmlspecialchars($url, ENT_QUOTES, 'UTF-8'), $fname . '.gdf'));

            // $response->headers->set('Refresh', '5; url=' . $url);

            return $response;
        }

        if (!file_exists($fnameFull)) {
           throw $this->createNotFoundException($fname . '.gdf' . ' does not exist');
        }

        return new \Symfony\Component\HttpFoundation\StreamedResponse(
            function () use ($fnameFull) {
                readfile($fnameFull);
            }, Response::HTTP_OK, [
                'Content-Type' => 'text/plain',
                // 'Content-Disposition' => 'attachment; filename="' . $fname . '.gdf' . '"'
            ]
        );
    }
}
