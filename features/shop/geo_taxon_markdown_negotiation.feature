@acseo_geo @ui
Feature: Markdown negotiation on canonical taxon URLs
    In order to read category facts without parsing HTML
    As an AI crawler
    I want the canonical taxon URL to serve Markdown when I prefer it

    Background:
        Given the store operates on a single channel in "United States"
        And the store classifies its products as "Category"
        And the "Category" taxon has child taxon "Mugs"
        And channel "United States" has menu taxon "Category"

    Scenario: The canonical taxon URL serves Markdown when it is preferred
        When I request the canonical page of the "Mugs" taxon accepting "text/markdown"
        Then the GEO response status code should be 200
        And the GEO response content type should contain "text/markdown"
        And the GEO response header "Vary" should contain "Accept"
        And the GEO response header "Cache-Control" should contain "public"
        And the GEO response should contain "Mugs"

    Scenario: The canonical taxon URL rejects unsupported media types
        When I request the canonical page of the "Mugs" taxon accepting "application/json"
        Then the GEO response status code should be 406
        And the GEO response header "Vary" should contain "Accept"

    Scenario: A taxon outside the channel menu is not exported
        Given the store classifies its products as "Outlet"
        When I request the canonical page of the "Outlet" taxon accepting "text/markdown"
        Then the GEO response status code should be 404

    Scenario: A disabled taxon is not exported
        Given the "Mugs" taxon is disabled
        When I request the canonical page of the "Mugs" taxon accepting "text/markdown"
        Then the GEO response status code should be 404

    Scenario: A taxon in an excluded subtree is not exported
        Given the "Category" taxon has child taxon "GEO private"
        When I request the canonical page of the "GEO private" taxon accepting "text/markdown"
        Then the GEO response status code should be 404
