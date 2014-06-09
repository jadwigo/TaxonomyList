<?php
// Sitemap Extension for Bolt, by Bob den Otter

namespace TaxonomyList;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Bolt\Extensions\Snippets\Location as SnippetLocation;

class Extension extends \Bolt\BaseExtension
{


    /**
     * Info block for Sitemap Extension.
     */
    function info()
    {

        $data = array(
            'name' => "TaxonomyList",
            'description' => "An extension that adds a twig tag for Taxonomy Listings.",
            'author' => "Lodewijk Evers",
            'link' => "http://bolt.cm",
            'version' => "0.1",
            'required_bolt_version' => "1.6.5",
            'highest_bolt_version' => "1.6.5",
            'type' => "General",
            'first_releasedate' => "2014-06-06",
            'latest_releasedate' => "2014-06-06",
            'dependencies' => "",
            'priority' => 10
        );

        return $data;

    }

    /**
     * Initialize TaxonomyList. Called during bootstrap phase.
     */
    function initialize()
    {
        if (empty($this->config['default_taxonomy'])) {
            $this->config['default_taxonomy'] = 'tags';
        }

        // Set up the routes for the sitemap..
        $this->app->match("/taxonomies", array($this, 'taxonomies'));

        $this->addTwigFunction('taxonomylist', 'twigTaxonomyList');
    }


    public function taxonomies($xml = false)
    {
        $taxonomy = $this->app['config']->get('taxonomy');
        $taxonomies = array_keys($taxonomy);

        $template = $this->config['template'];

        $this->app['twig.loader.filesystem']->addPath(__DIR__);

        $body = $this->app['render']->render($template, array(
            'taxonomies' => $taxonomies
        ));

        $headers = array();

        return new Response($body, 200, $headers);

    }


    /**
     * Return an array with items in a taxonomy
     */
    function twigTaxonomyList($name = false, $full = false) {

        // if $name isn't set, use the one from the config.yml. Unless that's empty too, then use "tags".
        if (empty($name)) {
            if (!empty($this->config['default_taxonomy'])) {
                $name = $this->config['default_taxonomy'];
            } else {
                $name = "tags";
            }
        }
        // \Dumper::dump($this->app['paths']);

        $taxonomy = $this->app['config']->get('taxonomy');

        if(array_key_exists($name, $taxonomy)) {
            $named = $taxonomy[$name];
            if($full != false) {
                $named = $this->getFullTaxonomy($name, $taxonomy);
            }
            if(array_key_exists('options', $named)) {
                // \Dumper::dump($named);

                foreach($named['options'] as $slug => $item) {

                    if(is_array($item) && $item['name']) {
                        $catname = $item['name'];
                        $itemcount = $item['count'];
                    } else {
                        $catname = $item;
                        $itemcount = null;
                    }
                    $itemlink = $this->app['paths']['root'].$name .'/'.$slug;

                    $options[$slug] = array(
                        'slug' => $slug,
                        'name' => $catname,
                        'link' => $itemlink,
                        'count' => $itemcount,
                    );
                }
                //krumo($options);
                return $options;
            }
        }

        return null;

    }

    /**
     * Get the full taxonomy data from the database, count all occurences of a certain taxonomy name
     */
    function getFullTaxonomy($name = null, $taxonomy = null) {

        if(array_key_exists($name, $taxonomy)) {
            $named = $taxonomy[$name];

            // \Dumper::dump($name, $named);
            // \Dumper::dump($this->app['config']);
            $prefix = $this->app['config']->get('general/database/prefix', 'bolt_');

            $tablename = $prefix . "taxonomy";

            // \Dumper::dump($tablename);

            $query = sprintf(
                "SELECT COUNT(name) as count, slug, name FROM %s WHERE taxonomytype IN ('%s') GROUP BY name ORDER BY sortorder ASC",
                $tablename,
                $name
            );
            // \Dumper::dump($query);

            // \Dumper::dump($this->app['db']);
            $rows = $this->app['db']->executeQuery( $query )->fetchAll();

            // \Dumper::dump($rows);

            if($rows) {
                foreach($rows as $row) {
                    $named['options'][$row['slug']] = $row;
                }
            }

            // \Dumper::dump($named);
            return $named;
        }

        return null;
    }
}

