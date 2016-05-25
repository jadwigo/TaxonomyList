<?php
// Taxonomy listing Extension for Bolt, by Lodewijk Evers

namespace Bolt\Extension\Jadwigo\TaxonomyList;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Silex\ControllerCollection;
use Bolt\Extension\SimpleExtension;
use Silex\Application;

class TaxonomyListExtension extends SimpleExtension
{
    protected function registerFrontendRoutes(ControllerCollection $collection)
    {
        $config = $this->getConfig();

        $collection->match($config['route_path'], [$this, 'taxonomies']);
    }

    protected function getDefaultConfig()
    {
        return [
            'template' => 'taxonomylist.twig',
            'default_taxonomy' => 'tags',
            'route_path' => '/taxonomies'
        ];
    }

    protected function registerTwigFunctions()
    {
        $app = $this->getContainer();

        return [
            'taxonomylist' => [
                [$app['taxonomylist.twig'], 'twigTaxonomyList'],

            ]
        ];
    }


    protected function registerServices(Application $app)
    {
        $app['taxonomylist.twig'] = $app->share(
            function ($app) {
                $config = $app['extensions']->get('Jadwigo/TaxonomyList')->getConfig();

                return new \Bolt\Extension\Jadwigo\TaxonomyList\Twig\TaxonomyListExtension($config, $app);
            }
        );
    }

    public function taxonomies(Application $app, Request $request)
    {
        $config = $this->getConfig();

        $taxonomy = $app['config']->get('taxonomy');
        $taxonomies = array_keys($taxonomy);

        $template = $config['template'];

        $body = $this->renderTemplate($template, [
            'taxonomies' => $taxonomies
        ]);

        return new Response($body);
    }
}
