TaxonomyList
============

This extension adds a twig tag for Taxonomy Listings.

You can use this extension to create category listings and tagclouds.

Simple lookup
-------------

The quick lookup only reads the taxonomy config and does not count items in the database. Usage with quick lookup:

```twig
    {% set list = taxonomylist('categories') %}
    <ul>
    {% for item in list %}
        <li>
            <a class="taxonomy-{{ item.slug }}" href="{{ item.link }}" title="{{ item.name }}">
                {{ item.name }}
            </a>
        </li>
    {% endfor %}
    </ul>
```

Tags lookup or full lookup
--------------------------

The full lookup counts all items in the database for each category and returns this in ``{{ item.count }}``.

If your taxonomy behaves like a `tags` category, there will automatically be a full lookup - because the individual tags are not visible in the config file.

Usage with full lookup:

```twig
    {% set list = taxonomylist('categories', true) %}
    <ul>
    {% for item in list %}
        <li>
            <a class="taxonomy-{{ item.slug }}" href="{{ item.link }}" title="{{ item.name }}">
                {{ item.name }}
                <small>{{ item.count }}</small>
            </a>
        </li>
    {% endfor %}
    </ul>
```

Use as a Tagcloud
-----------------

A tagcloud, usage with limit and weight in a full lookup (it does not make sense to do a quick lookup with weighted tags, or limits):

```twig
    {% set list = taxonomylist('categories', { 'limit': 10, 'weighted': true } ) %}
    <nav class="tags">
    {% for item in list %}
            <a class="taxonomy-{{ item.slug }} weight-{{ item.weightclass }}" href="{{ item.link }}" title="{{ item.name }}">
                {{ item.name }}
                <small>{{ item.count }} items</small>
            </a>
    {% endfor %}
    </nav>
```

Weighted will return the tags with the most matches first, unweighted will return the tags in the original sortorder. Taxonomy terms without any records in the database will not be returned.

The weighted query also returns ``{{ item.weight }}`` and ``{{ item.weightclass }}``. The ``{{ item.weight }}`` is a percentage.
The ``{{ item.weightclass }}`` is one of xl, x, m, s, xs.


Only published items
--------------------

If you only want to count the published items you will need to know the content type as well as the taxonomy. The rest of the behaviour is the same as the other options.

```twig
    {% set list = taxonomylist('categories', { 'limit': 10, 'weighted': true, 'contenttype': 'pages' } ) %}
```

_This might be a problem if you share taxonomies with more contentypes._
