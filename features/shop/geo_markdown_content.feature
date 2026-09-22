@acseo_geo @ui
Feature: GEO Markdown content and HTTP behaviour
    In order to give AI crawlers accurate and cacheable catalog facts
    As an AI crawler
    I want Markdown pages to expose real prices, taxons and cache headers

    Background:
        Given the store operates on a single channel in "United States"

    Scenario: The product price is the cheapest enabled variant
        Given the store has a product "PHP Mug" priced at "$30.00"
        And this product has "Small" variant priced at "$10.00"
        When I request the GEO Markdown page for the "PHP Mug" product
        Then the GEO response status code should be 200
        And the GEO response should contain "$10.00"

    Scenario: The Markdown page links the taxons of the product
        Given the store classifies its products as "Mugs"
        And the store has a product "PHP Mug" belonging to the "Mugs" taxon
        When I request the GEO Markdown page for the "PHP Mug" product
        Then the GEO response should contain "## Taxons"
        And the GEO response should contain "/taxons/"

    Scenario: Public GEO responses are cacheable and validated by an ETag
        Given the store has a product "PHP Mug"
        When I request the GEO Markdown page for the "PHP Mug" product
        Then the GEO response header "Cache-Control" should contain "public"
        And the GEO response header "Cache-Control" should contain "must-revalidate"
        And the GEO response should have an ETag header

    Scenario: The canonical product URL serves Markdown when it is preferred
        Given the store has a product "PHP Mug"
        When I request the canonical page of the "PHP Mug" product accepting "text/markdown"
        Then the GEO response status code should be 200
        And the GEO response content type should contain "text/markdown"
        And the GEO response header "Vary" should contain "Accept"

    Scenario: The canonical product URL rejects unsupported media types
        Given the store has a product "PHP Mug"
        When I request the canonical page of the "PHP Mug" product accepting "application/json"
        Then the GEO response status code should be 406

    Scenario: A conditional request with the current ETag is answered with 304
        Given the store has a product "PHP Mug"
        When I request the GEO Markdown page for the "PHP Mug" product
        And I request the same GEO page again with its ETag
        Then the GEO response status code should be 304

    Scenario: A product in an excluded taxon is neither exported nor listed
        Given the store classifies its products as "Private" with "geo-private" code
        And the store has a product "Secret Mug" belonging to the "Private" taxon
        When I request the GEO Markdown page for the "Secret Mug" product
        Then the GEO response status code should be 404

    Scenario: An excluded product is not listed in llm.txt nor in the sitemap
        Given the store classifies its products as "Private" with "geo-private" code
        And the store has a product "Secret Mug" belonging to the "Private" taxon
        When I request the public GEO llm.txt index for the "en_US" locale
        Then the GEO response should not contain "Secret Mug"
        When I request the public GEO sitemap for the "en_US" locale
        Then the GEO response should not contain "/geo/products/"

    Scenario: A product not sold on the current channel is not exported
        Given the store has a product "PHP Mug"
        And this product is unavailable in "United States" channel
        When I request the GEO Markdown page for the "PHP Mug" product
        Then the GEO response status code should be 404

    Scenario: A product with an excluded attribute code is not exported
        Given the store has a product "Secret Mug"
        And this product has the text attribute "geo_hidden" with value "yes"
        When I request the GEO Markdown page for the "Secret Mug" product
        Then the GEO response status code should be 404

    Scenario: A product with an excluded attribute value is not exported nor listed
        Given the store has a product "Secret Mug"
        And this product has the text attribute "geo_visibility" with value "private"
        When I request the GEO Markdown page for the "Secret Mug" product
        Then the GEO response status code should be 404
        When I request the public GEO llm.txt index for the "en_US" locale
        Then the GEO response should not contain "Secret Mug"

    Scenario: A product with another value of the filtered attribute is exported
        Given the store has a product "Public Mug"
        And this product has the text attribute "geo_visibility" with value "public"
        When I request the GEO Markdown page for the "Public Mug" product
        Then the GEO response status code should be 200

    Scenario: A product sold on another channel is exported only through that channel
        Given the store also operates on another channel named "Europe"
        And the store has a product "PHP Mug"
        And this product is unavailable in "United States" channel
        And this product is available in "Europe" channel
        When I browse the shop through the "United States" channel
        And I request the GEO Markdown page for the "PHP Mug" product
        Then the GEO response status code should be 404
        When I browse the shop through the "Europe" channel
        And I request the GEO Markdown page for the "PHP Mug" product
        Then the GEO response status code should be 200

    Scenario: llm.txt only lists the products of the current channel
        Given the store also operates on another channel named "Europe"
        And the store has a product "PHP Mug"
        And this product is unavailable in "United States" channel
        And this product is available in "Europe" channel
        When I browse the shop through the "United States" channel
        And I request the public GEO llm.txt index for the "en_US" locale
        Then the GEO response should not contain "PHP Mug"
        When I browse the shop through the "Europe" channel
        And I request the public GEO llm.txt index for the "en_US" locale
        Then the GEO response should contain "PHP Mug"
