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
            'version' => "0.2",
            'required_bolt_version' => "1.6.5",
            'highest_bolt_version' => "1.6.5",
            'type' => "General",
            'first_releasedate' => "2014-06-06",
            'latest_releasedate' => "2014-06-19",
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
    function twigTaxonomyList($name = false, $params = false) {

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
            if($params != false) {
                $named = $this->getFullTaxonomy($name, $taxonomy, $params);
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
                    if($item['weight']>=0) {
                        $options[$slug]['weight'] = $item['weight'];
                        $options[$slug]['weightclass'] = $item['weightclass'];
                    }
                }
                // \Dumper::dump($named);
                // \Dumper::dump($options);
                return $options;
            }
        }

        return null;

    }

    /**
     * Get the full taxonomy data from the database, count all occurences of a certain taxonomy name
     */
    function getFullTaxonomy($name = null, $taxonomy = null, $params = null) {

        if(array_key_exists($name, $taxonomy)) {
            $named = $taxonomy[$name];

            // default params
            $limit = $weighted = false;
            if(isset($params['limit']) && is_numeric($params['limit'])) {
                $limit = $params['limit'];
            }
            if(isset($params['weighted']) && $params['weighted']==true) {
                $weighted = true;
            }

            $prefix = $this->app['config']->get('general/database/prefix', 'bolt_');
            $tablename = $prefix . "taxonomy";

            // type of sort depending on params
            if($weighted) {
                $sortorder = 'count DESC';
            } else {
                $sortorder = 'sortorder ASC';
            }

            // the normal query
            $query = sprintf(
                "SELECT COUNT(name) as count, slug, name FROM %s WHERE taxonomytype IN ('%s') GROUP BY name ORDER BY %s",
                $tablename,
                $name,
                $sortorder
            );

            // append limit to query the parameter is set
            if($limit) {
                $query .= sprintf(' LIMIT 0, %d', $limit);
            }

            // fetch results from db
            $rows = $this->app['db']->executeQuery( $query )->fetchAll();

            if($rows && ($weighted || $limit)) {
                // find the max / min for the results
                $named['maxcount'] = 0;
                $named['number_of_tags'] = count($named['options']);
                foreach($rows as $row) {
                    if($row['count']>=$named['maxcount']) {
                        $named['maxcount']= $row['count'];
                    }
                    if(!isset($named['mincount']) || $row['count']<=$named['mincount']) {
                        $named['mincount']= $row['count'];
                    }
                }

                $named['deltacount'] = $named['maxcount'] - $named['mincount'] + 1;
                $named['stepsize'] = $named['deltacount'] / 5;

                // return only rows with results
                $populatedrows = array();
                foreach($rows as $row) {
                    $row['weightpercent'] = ($row['count'] - $named['mincount']) / ($named['maxcount'] - $named['mincount']);
                    $row['weight'] = round($row['weightpercent'] * 100);

                    if($row['weight']<=20) {
                        $row['weightclass'] = 'xs';
                    } elseif($row['weight']<=40) {
                        $row['weightclass'] = 's';
                    } elseif($row['weight']<=60) {
                        $row['weightclass'] = 'm';
                    } elseif($row['weight']<=80) {
                        $row['weightclass'] = 'l';
                    } else {
                        $row['weightclass'] = 'xl';
                    }

                    $populatedrows[$row['slug']] = $row;
                }
                $named['options'] = $populatedrows;
            } elseif($rows) {
                // return all rows - so add the count to all existing rows
                // weight is useless here
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

