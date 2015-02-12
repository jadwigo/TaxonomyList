TaxonomyList
============

This extension adds a twig tag for Taxonomy Listings.

The quick lookup only reads the taxonomy config and does not count items in the database. Usage with quick lookup:

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

If your taxonomy behaves like a `tags` category, there will automatically be a full lookup - because the individual tags are not visible in the config file.

The full lookup counts all items in the database for each category and returns this in ``{{ item.count }}``. Usage with full lookup:

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

Usage with limit and weight in a full lookup (it does not make sense to do a quick lookup with weighted tags, or limits):

    {% set list = taxonomylist('categories', { 'limit': 10, 'weighted': true } ) %}
    <nav class="tags">
    {% for item in list %}
            <a class="taxonomy-{{ item.slug }} weight-{{ item.weightclass }}" href="{{ item.link }}" title="{{ item.name }}">
                {{ item.name }}
                <small>{{ item.count }} items</small>
            </a>
    {% endfor %}
    </nav>

Weighted will return the tags with the most matches first, unweighted will return the tags in the original sortorder. Taxonomy terms without any records in the database will not be returned.

The weighted query also returns ``{{ item.weight }}`` and ``{{ item.weightclass }}``. The ``{{ item.weight }}`` is a percentage.
The ``{{ item.weightclass }}`` is one of xl, x, m, s, xs.
