@acseo_geo @ui
Feature: Public GEO shop API artefacts
    In order to consume GEO content from a headless client
    As an API consumer
    I want public shop GEO endpoints without authentication

    Background:
        Given the store operates on a single channel in "United States"
        And the store has a product "PHP T-Shirt"

    Scenario: Shop API llm-txt is publicly readable as plain text
        When I request the public GEO shop API llm-txt endpoint
        Then the GEO response status code should be 200
        And the GEO response content type should contain "text/plain"
        And the GEO response should contain "PHP T-Shirt"

    Scenario: Shop API Markdown is available for an enabled product
        When I request the GEO shop API Markdown for the "PHP T-Shirt" product
        Then the GEO response status code should be 200
        And the GEO response content type should contain "text/markdown"
        And the GEO response should contain "PHP T-Shirt"

    Scenario: Shop API Markdown is not available for a disabled product
        Given the product "PHP T-Shirt" has been disabled
        When I request the GEO shop API Markdown for the "PHP T-Shirt" product
        Then the GEO response status code should be 404

    Scenario: Shop API llm-txt does not list a disabled product
        Given the product "PHP T-Shirt" has been disabled
        When I request the public GEO shop API llm-txt endpoint
        Then the GEO response status code should be 200
        And the GEO response should not contain "PHP T-Shirt"

    Scenario: Shop API Markdown is not available for an unknown product
        When I request the GEO shop API Markdown for the unknown "does-not-exist" product
        Then the GEO response status code should be 404
