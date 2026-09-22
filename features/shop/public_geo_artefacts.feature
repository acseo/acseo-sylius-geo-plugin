@acseo_geo @ui
Feature: Public GEO artefacts for AI crawlers
    In order to let generative engines discover the shop catalog
    As an AI crawler
    I want to read llm.txt and product Markdown without authentication

    Background:
        Given the store operates on a single channel in "United States"
        And the store has a product "PHP T-Shirt"

    Scenario: llm.txt is publicly readable as plain text after root redirect
        When I request the public GEO llm.txt index
        Then the GEO response status code should be 200
        And the GEO response content type should contain "text/plain"
        And the GEO response should contain "PHP T-Shirt"

    Scenario: Markdown page is available for an enabled product
        When I request the GEO Markdown page for the "PHP T-Shirt" product
        Then the GEO response status code should be 200
        And the GEO response content type should contain "text/markdown"
        And the GEO response should contain "PHP T-Shirt"

    Scenario: Markdown page is not available for a disabled product
        Given the product "PHP T-Shirt" has been disabled
        When I request the GEO Markdown page for the "PHP T-Shirt" product
        Then the GEO response status code should be 404

    Scenario: llm.txt is readable for an explicit locale
        When I request the public GEO llm.txt index for the "en_US" locale
        Then the GEO response status code should be 200
        And the GEO response content type should contain "text/plain"
        And the GEO response should contain "PHP T-Shirt"

    Scenario: llm.txt does not list a disabled product
        Given the product "PHP T-Shirt" has been disabled
        When I request the public GEO llm.txt index for the "en_US" locale
        Then the GEO response status code should be 200
        And the GEO response should not contain "PHP T-Shirt"

    Scenario: Markdown page is not available for an unknown product
        When I request the GEO Markdown page for the unknown "does-not-exist" product
        Then the GEO response status code should be 404

    Scenario: The GEO sitemap is publicly readable as XML
        When I request the public GEO sitemap for the "en_US" locale
        Then the GEO response status code should be 200
        And the GEO response content type should contain "xml"
        And the GEO response should contain "/geo/products/"

    Scenario: The GEO sitemap does not list a disabled product
        Given the product "PHP T-Shirt" has been disabled
        When I request the public GEO sitemap for the "en_US" locale
        Then the GEO response status code should be 200
        And the GEO response should not contain "/geo/products/"
