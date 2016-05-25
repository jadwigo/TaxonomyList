<?php


namespace Bolt\Extension\Jadwigo\TaxonomyList\Twig;

use Silex\Application;

class TaxonomyListExtension
{
    /** @var array $config */
    protected $config;

    /** @var Application $app */
    protected $app;

    public function __construct(array $config, Application $app)
    {
        $this->config = $config;
        $this->app = $app;
    }

    /**
     * Return an array with items in a taxonomy
     *
     * @param bool $name
     * @param bool $params
     * @return null
     */
    public function twigTaxonomyList($name = false, $params = false)
    {
        // if $name isn't set, use the one from the config.yml. Unless that's empty too, then use "tags".
        if (empty($name)) {
            if (!empty($this->config['default_taxonomy'])) {
                $name = $this->config['default_taxonomy'];
            } else {
                $name = "tags";
            }
        }

        $taxonomy = $this->app['config']->get('taxonomy');

        if (array_key_exists($name, $taxonomy)) {
            $named = $taxonomy[$name];
            if ($params != false || $named['behaves_like']=='tags') {
                $named = $this->getFullTaxonomy($name, $taxonomy, $params);
            }

            if (array_key_exists('options', $named)) {
                foreach ($named['options'] as $slug => $item) {
                    if (is_array($item) && $item['name']) {
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
                    if (array_key_exists('weight', $item) && $item['weight']>=0) {
                        $options[$slug]['weight'] = $item['weight'];
                        $options[$slug]['weightclass'] = $item['weightclass'];
                    }
                }
                return $options;
            }
        }

        return null;
    }

    /**
     * Get the full taxonomy data from the database, count all occurences of a certain taxonomy name
     *
     * @param null $name
     * @param null $taxonomy
     * @param null $params
     * @return null
     * @throws \Doctrine\DBAL\DBALException
     */
    protected function getFullTaxonomy($name = null, $taxonomy = null, $params = null)
    {
        if (array_key_exists($name, $taxonomy)) {
            $named = $taxonomy[$name];

            // default params
            $limit = $weighted = $contenttype = false;
            if (isset($params['limit']) && is_numeric($params['limit'])) {
                $limit = $params['limit'];
            }

            if (isset($params['weighted']) && $params['weighted']==true) {
                $weighted = true;
            }

            if (isset($params['contenttype']) && $params['contenttype']!="") {
                $contenttype = $params['contenttype'];
            }

            $prefix = $this->app['config']->get('general/database/prefix', 'bolt_');
            $tablename = $prefix . "taxonomy";

            // type of sort depending on params
            if ($weighted) {
                $sortorder = 'count DESC';
            } else {
                $sortorder = 'sortorder ASC';
            }

            if (!$contenttype) {
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
            } elseif ($contenttype!=false) {
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
            if ($limit) {
                $query .= sprintf(' LIMIT 0, %d', $limit);
            }

            // fetch results from db
            $rows = $this->app['db']->executeQuery($query)->fetchAll();

            if ($rows && ($weighted || $limit)) {
                // find the max / min for the results
                $named['maxcount'] = 0;
                $named['number_of_tags'] = count($named['options']);
                foreach ($rows as $row) {
                    if ($row['count']>=$named['maxcount']) {
                        $named['maxcount']= $row['count'];
                    }
                    if (!isset($named['mincount']) || $row['count']<=$named['mincount']) {
                        $named['mincount']= $row['count'];
                    }
                }

                $named['deltacount'] = $named['maxcount'] - $named['mincount'] + 1;
                $named['stepsize'] = $named['deltacount'] / 5;

                // return only rows with results
                $populatedrows = array();
                foreach ($rows as $row) {
                    $row['weightpercent'] = 1; // everything is all
                    if ($named['maxcount'] != $named['mincount']) { // prevent divide by zero
                        $row['weightpercent'] = ($row['count'] - $named['mincount']) / ($named['maxcount'] - $named['mincount']);
                    }
                    $row['weight'] = round($row['weightpercent'] * 100);

                    if ($row['weight']<=20) {
                        $row['weightclass'] = 'xs';
                    } elseif ($row['weight']<=40) {
                        $row['weightclass'] = 's';
                    } elseif ($row['weight']<=60) {
                        $row['weightclass'] = 'm';
                    } elseif ($row['weight']<=80) {
                        $row['weightclass'] = 'l';
                    } else {
                        $row['weightclass'] = 'xl';
                    }

                    $populatedrows[$row['slug']] = $row;
                }
                $named['options'] = $populatedrows;
            } elseif ($rows) {
                // return all rows - so add the count to all existing rows
                // weight is useless here
                foreach ($rows as $row) {
                    $named['options'][$row['slug']] = $row;
                }
            }

            return $named;
        }

        return null;
    }
}