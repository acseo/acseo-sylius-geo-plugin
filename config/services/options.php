<?php

declare(strict_types=1);

use ACSEO\SyliusGeoPlugin\Http\EndpointSettings;
use ACSEO\SyliusGeoPlugin\Model\ChannelInfoOptions;
use ACSEO\SyliusGeoPlugin\Model\CrawlerPolicyOptions;
use ACSEO\SyliusGeoPlugin\Model\ExclusionRules;
use ACSEO\SyliusGeoPlugin\Model\ProductMarkdownOptions;
use ACSEO\SyliusGeoPlugin\Model\TaxonMarkdownOptions;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(ProductMarkdownOptions::class)->args([
        '$includeBrand' => param('acseo_sylius_geo_plugin.product_markdown.include_brand'),
        '$stockStrategy' => param('acseo_sylius_geo_plugin.product_markdown.stock_strategy'),
        '$priceStrategy' => param('acseo_sylius_geo_plugin.product_markdown.price_strategy'),
        '$includeImages' => param('acseo_sylius_geo_plugin.product_markdown.include_images'),
        '$includeAttributes' => param('acseo_sylius_geo_plugin.product_markdown.include_attributes'),
        '$includeVariants' => param('acseo_sylius_geo_plugin.product_markdown.include_variants'),
        '$includeTaxons' => param('acseo_sylius_geo_plugin.product_markdown.include_taxons'),
        '$includeJsonLd' => param('acseo_sylius_geo_plugin.product_markdown.include_json_ld'),
    ]);

    $services->set(TaxonMarkdownOptions::class)->args([
        '$includeDescription' => param('acseo_sylius_geo_plugin.taxon_markdown.include_description'),
        '$includeChildren' => param('acseo_sylius_geo_plugin.taxon_markdown.include_children'),
    ]);

    $services->set(ChannelInfoOptions::class)->args([
        '$enabled' => param('acseo_sylius_geo_plugin.channel_info.enabled'),
        '$includeChannelCode' => param('acseo_sylius_geo_plugin.channel_info.include_channel_code'),
        '$shopName' => param('acseo_sylius_geo_plugin.channel_info.shop_name'),
        '$baseUrl' => param('acseo_sylius_geo_plugin.channel_info.base_url'),
        '$currencies' => param('acseo_sylius_geo_plugin.channel_info.currencies'),
        '$locales' => param('acseo_sylius_geo_plugin.channel_info.locales'),
        '$servedCountries' => param('acseo_sylius_geo_plugin.channel_info.served_countries'),
    ]);

    $services->set(CrawlerPolicyOptions::class)->args([
        '$enabled' => param('acseo_sylius_geo_plugin.crawler_policy.enabled'),
        '$allowAiCrawlers' => param('acseo_sylius_geo_plugin.crawler_policy.allow_ai_crawlers'),
        '$hints' => param('acseo_sylius_geo_plugin.crawler_policy.hints'),
    ]);

    $services->set(ExclusionRules::class)->args([
        '$taxonCodes' => param('acseo_sylius_geo_plugin.product_exclusion.taxon_codes'),
        '$attributeCodes' => param('acseo_sylius_geo_plugin.product_exclusion.attribute_codes'),
        '$attributeValues' => param('acseo_sylius_geo_plugin.product_exclusion.attribute_values'),
    ]);

    $services->set(EndpointSettings::class)->args([
        '$llmTxt' => param('acseo_sylius_geo_plugin.endpoints.llm_txt'),
        '$productMarkdown' => param('acseo_sylius_geo_plugin.endpoints.product_markdown'),
        '$taxonMarkdown' => param('acseo_sylius_geo_plugin.endpoints.taxon_markdown'),
        '$sitemap' => param('acseo_sylius_geo_plugin.endpoints.sitemap'),
        '$api' => param('acseo_sylius_geo_plugin.endpoints.api'),
    ]);
};
