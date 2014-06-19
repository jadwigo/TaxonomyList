TaxonomyList
============

This extension adds a twig tag for Taxonomy Listings.

Usage with quick lookup:

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

Usage with full lookup:

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

Limit and weight with full lookup (it does not make sense to do a quick lookup with weighted tags, or limits):

    {% set list = taxonomylist('categories', { 'limit': 10, 'weighted': true } ) %}
    <nav class="tags">
    {% for item in list %}
            <a class="taxonomy-{{ item.slug }} weight-{{ item.weight }}" href="{{ item.link }}" title="{{ item.name }}">
                {{ item.name }}
                <small>{{ item.count }}</small>
            </a>
    {% endfor %}
    </nav>

Weighted will return the tags with the most matches first, unweighted will return the tags in the original sortorder.
