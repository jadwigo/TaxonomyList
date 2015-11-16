<?php
// Taxonomy listing Extension for Bolt, by Lodewijk Evers

namespace Bolt\Extension\Jadwigo\TaxonomyList;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Bolt\Extensions\Snippets\Location as SnippetLocation;

class Extension extends \Bolt\BaseExtension
{
    const NAME = 'TaxonomyList';
    /**
     * Provide default Extension Name
     */

    public function getName()
    {
        return Extension::NAME;
    }

    /**
     * Initialize TaxonomyList. Called during bootstrap phase.
     */
    public function initialize()
    {
        if ($this->app['config']->getWhichEnd() == 'frontend') {
            // Add Twig functions
            if (empty($this->config['default_taxonomy'])) {
                $this->config['default_taxonomy'] = 'tags';
            }
            
            if (empty($this->config['route_path'])) {
                $this->config['route_path'] = '/taxonomies';
            }

            // Set up the routes for the sitemap..
            $this->app->match($this->config['route_path'], array($this, 'taxonomies'));

            $this->addTwigFunction('taxonomylist', 'twigTaxonomyList');
        }
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
        // dump($this->app['paths']);

        $taxonomy = $this->app['config']->get('taxonomy');

        if(array_key_exists($name, $taxonomy)) {
            $named = $taxonomy[$name];
            if($params != false || $named['behaves_like']=='tags') {
                $named = $this->getFullTaxonomy($name, $taxonomy, $params);
            }

            if(array_key_exists('options', $named)) {
                // dump($named);
                foreach($named['options'] as $slug => $item) {

                    if(is_array($item) && $item['name']) {
                        $catname = $item['name'];
                        $itemcount = $item['count'];
                    } else {
                        $catname = $item;
                        $itemcount = null;
                        $item = array(
                            'name' => $catname,
                            'count' => null
                        );
                    }
                    $itemlink = $this->app['paths']['root'].$name .'/'.$slug;

                    $options[$slug] = array(
                        'slug' => $slug,
                        'name' => $catname,
                        'link' => $itemlink,
                        'count' => $itemcount,
                    );
                    if(array_key_exists('weight', $item) && $item['weight']>=0) {
                        $options[$slug]['weight'] = $item['weight'];
                        $options[$slug]['weightclass'] = $item['weightclass'];
                    }
                }
                // dump($named);
                // dump($options);
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
            $limit = $weighted = $contenttype = false;
            if(isset($params['limit']) && is_numeric($params['limit'])) {
                $limit = $params['limit'];
            }

            if(isset($params['weighted']) && $params['weighted']==true) {
                $weighted = true;
            }

            if(isset($params['contenttype']) && $params['contenttype']!="") {
                $contenttype = $params['contenttype'];
            }

            $prefix = $this->app['config']->get('general/database/prefix', 'bolt_');
            $tablename = $prefix . "taxonomy";

            // type of sort depending on params
            if($weighted) {
                $sortorder = 'count DESC';
            } else {
                $sortorder = 'sortorder ASC';
            }

            if(!$contenttype) {
                // the normal query
                $query = sprintf(
                    "SELECT COUNT(name) as count, slug, name 
                        FROM %s
                        WHERE taxonomytype IN ('%s')
                        GROUP BY name, slug, sortorder 
                        ORDER BY %s",
                    $tablename,
                    $name,
                    $sortorder
                );
            } elseif($contenttype!=false) {
                // TODO: get the contenttype table from the contenttype slug instead of guessing
                $contenttype_table = $prefix . $contenttype;
                // the normal query with only published items
                $query = sprintf(
                    "SELECT COUNT(name) as count, slug, name 
                        FROM %s
                        WHERE taxonomytype = '%s'
                            AND contenttype = '%s'
                            AND content_id IN (SELECT id FROM %s WHERE status = 'published' AND id = content_id)
                        GROUP BY name, slug, sortorder 
                        ORDER BY %s",
                    $tablename,
                    $name,
                    $contenttype,
                    $contenttype_table,
                    $sortorder
                );
            }

            // append limit to query the parameter is set
            if($limit) {
                $query .= sprintf(' LIMIT 0, %d', $limit);
            }

            // dump($query);

            // fetch results from db
            $rows = $this->app['db']->executeQuery( $query )->fetchAll();
            // dump($rows);

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
                    $row['weightpercent'] = 1; // everything is all
                    if($named['maxcount'] != $named['mincount']) { // prevent divide by zero
                        $row['weightpercent'] = ($row['count'] - $named['mincount']) / ($named['maxcount'] - $named['mincount']);
                    }
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

            // dump($named);
            return $named;
        }

        return null;
    }
}
