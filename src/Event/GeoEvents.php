<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Event;

final class GeoEvents
{
    public const BEFORE_LLM_TXT_GENERATION = 'acseo_sylius_geo_plugin.before_llm_txt_generation';

    public const BEFORE_PRODUCT_MARKDOWN_GENERATION = 'acseo_sylius_geo_plugin.before_product_markdown_generation';

    public const BEFORE_TAXON_MARKDOWN_GENERATION = 'acseo_sylius_geo_plugin.before_taxon_markdown_generation';

    public const BEFORE_RESPONSE_CREATION = 'acseo_sylius_geo_plugin.before_response_creation';

    private function __construct()
    {
    }
}
