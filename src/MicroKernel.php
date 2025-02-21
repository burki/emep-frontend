<?php

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

// see https://github.com/ikoene/symfony-micro
final class MicroKernel extends Kernel
{
    use MicroKernelTrait;
    /*
     * Set an Environment Variable in Apache Configuration
     *   SetEnv APP_ENVIRONMENT prod
     * for production setting instead of having www/app.php and www/app_dev.php
     * This approach is described in
     *   https://www.pmg.com/blog/symfony-no-app-dev/
     */
    public static function fromEnvironment()
    {
        $env = getenv('APP_ENVIRONMENT');
        if (false === $env) {
            $env = 'dev';
            $debug = true;
        }
        else {
            $debug = filter_var(getenv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN);
        }

        /*
        // hack to access backend session
        session_name('sid-emep');
        session_start();
        */

        return new self($env, $debug);
    }

    /*
     * {@inheritDoc}
     */
    public function registerBundles(): iterable
    {
        $bundles = [
            new \Symfony\Bundle\FrameworkBundle\FrameworkBundle(),

            new \Symfony\Bundle\TwigBundle\TwigBundle(),

            new \Doctrine\Bundle\DoctrineBundle\DoctrineBundle(),
            // new \Doctrine\Bundle\DoctrineCacheBundle\DoctrineCacheBundle(),
            new \Stof\DoctrineExtensionsBundle\StofDoctrineExtensionsBundle(),

            new \Symfony\Bundle\MonologBundle\MonologBundle(), // required by JMS\TranslationBundle\JMSTranslationBundle

            // login
            new \Symfony\Bundle\SecurityBundle\SecurityBundle(),

            // https://github.com/a-r-m-i-n/scssphp-bundle
            new \Armin\ScssphpBundle\ScssphpBundle(),

            // menu
            // see http://symfony.com/doc/current/bundles/KnpMenuBundle/index.html
            new \Knp\Bundle\MenuBundle\KnpMenuBundle(),

            // search / filter / paginate
            new \Spiriit\Bundle\FormFilterBundle\SpiriitFormFilterBundle(),
            new \Knp\Bundle\PaginatorBundle\KnpPaginatorBundle(),

            // select2 integration
            new \Tetranz\Select2EntityBundle\TetranzSelect2EntityBundle(),

            // converturls filter
            new \Liip\UrlAutoConverterBundle\LiipUrlAutoConverterBundle(),

            // form
            new \Shapecode\Bundle\HiddenEntityTypeBundle\ShapecodeHiddenEntityTypeBundle(),

            // $slug = $this->get('cocur_slugify')->slugify('Hello World!');
            // see https://github.com/cocur/slugify#user-content-symfony2
            new \Cocur\Slugify\Bridge\Symfony\CocurSlugifyBundle(),

            // pagination
            new \BabDev\PagerfantaBundle\BabDevPagerfantaBundle(),

            // sitemap
            new \Presta\SitemapBundle\PrestaSitemapBundle(),

            // own code
            new \AppBundle\AppBundle(),
        ];

        if (in_array($this->getEnvironment(), ['dev', 'test'], true)) {
            $bundles[] = new \Symfony\Bundle\WebProfilerBundle\WebProfilerBundle();
            $bundles[] = new \Symfony\Bundle\DebugBundle\DebugBundle();
        }

        return $bundles;
    }

    // see https://github.com/symfony/symfony-standard/blob/master/app/AppKernel.php
    public function getCacheDir(): string
    {
        return $this->getProjectDir() . '/var/cache/' . $this->getEnvironment();
    }

    public function getLogDir(): string
    {
        return $this->getProjectDir() . '/var/logs';
    }

    public function getConfigDir(): string
    {
        return $this->getProjectDir() . '/config';
    }

    /*
     * {@inheritDoc}
     */
    protected function configureContainer(ContainerBuilder $c, LoaderInterface $loader)
    {
        $loader->load($this->getConfigDir() . '/config_' . $this->getEnvironment() . '.yaml');
        $loader->load($this->getConfigDir() . '/services.yaml');
    }

    /*
     * {@inheritDoc}
     *
     * use
     *      bin/console debug:router
     * to show all your routes
     *
     */
    protected function configureRoutes(RoutingConfigurator $routes)
    {
        if (in_array($this->getEnvironment(), [ 'dev', 'test' ], true)) {
            $routes->import('@WebProfilerBundle/Resources/config/routing/wdt.xml')->prefix('/_wdt');
            $routes->import('@WebProfilerBundle/Resources/config/routing/profiler.xml')->prefix('/_profiler');

            // Preview error pages through /_error/{statusCode}
            //   see http://symfony.com/doc/current/cookbook/controller/error_pages.html
            // Note: not sure why this is mapped to /_error/_error/{code}.{_format} as can be seen by
            //   bin/console debug:router | grep error
            // -> _preview_error  ANY      ANY      ANY    /_error/_error/{code}.{_format}
            $routes->import('@FrameworkBundle/Resources/config/routing/errors.xml')->prefix('/_error');
        }

        // our controllers
        $confDir = $this->getConfigDir();

        $routes->import('../config/{routes}/*.yaml');

        if (is_file($confDir . '/routes.yaml')) {
            $routes->import($confDir . '/routes.yaml');
        }
        else {
            $routes->import($confDir . '/{routes}.php');
        }
    }
}
