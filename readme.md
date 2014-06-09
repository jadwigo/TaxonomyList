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

