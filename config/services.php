<?php

declare(strict_types=1);

use ACSEO\SyliusGeoPlugin\Catalog\ChannelProductCatalog;
use ACSEO\SyliusGeoPlugin\Catalog\ChannelProductCatalogInterface;
use ACSEO\SyliusGeoPlugin\Catalog\ChannelTaxonCatalog;
use ACSEO\SyliusGeoPlugin\Catalog\ChannelTaxonCatalogInterface;
use ACSEO\SyliusGeoPlugin\Checker\ProductGeoExportDiagnostics;
use ACSEO\SyliusGeoPlugin\Checker\ProductGeoExportDiagnosticsInterface;
use ACSEO\SyliusGeoPlugin\Checker\ProductGeoVisibilityChecker;
use ACSEO\SyliusGeoPlugin\Checker\ProductGeoVisibilityCheckerInterface;
use ACSEO\SyliusGeoPlugin\Checker\TaxonGeoVisibilityChecker;
use ACSEO\SyliusGeoPlugin\Checker\TaxonGeoVisibilityCheckerInterface;
use ACSEO\SyliusGeoPlugin\Command\AuditCommand;
use ACSEO\SyliusGeoPlugin\Command\DebugProductCommand;
use ACSEO\SyliusGeoPlugin\Command\DebugTaxonCommand;
use ACSEO\SyliusGeoPlugin\Controller\Api\LlmTxtApiController;
use ACSEO\SyliusGeoPlugin\Controller\Api\ProductMarkdownApiController;
use ACSEO\SyliusGeoPlugin\Controller\LlmTxtController;
use ACSEO\SyliusGeoPlugin\Controller\LlmTxtRootRedirectController;
use ACSEO\SyliusGeoPlugin\Controller\ProductMarkdownController;
use ACSEO\SyliusGeoPlugin\Controller\SitemapController;
use ACSEO\SyliusGeoPlugin\EventSubscriber\GeoResponseCookieSubscriber;
use ACSEO\SyliusGeoPlugin\EventSubscriber\GeoLocalizedEndpointLocaleSubscriber;
use ACSEO\SyliusGeoPlugin\EventSubscriber\ProductMarkdownRequestSubscriber;
use ACSEO\SyliusGeoPlugin\EventSubscriber\TaxonMarkdownRequestSubscriber;
use ACSEO\SyliusGeoPlugin\Generator\LlmTxtChannelInfo;
use ACSEO\SyliusGeoPlugin\Generator\LlmTxtGenerator;
use ACSEO\SyliusGeoPlugin\Generator\LlmTxtGeneratorInterface;
use ACSEO\SyliusGeoPlugin\Generator\ProductMarkdownGenerator;
use ACSEO\SyliusGeoPlugin\Generator\ProductMarkdownGeneratorInterface;
use ACSEO\SyliusGeoPlugin\Generator\ProductPricing;
use ACSEO\SyliusGeoPlugin\Generator\SitemapGenerator;
use ACSEO\SyliusGeoPlugin\Generator\SitemapGeneratorInterface;
use ACSEO\SyliusGeoPlugin\Generator\TaxonMarkdownGenerator;
use ACSEO\SyliusGeoPlugin\Generator\TaxonMarkdownGeneratorInterface;
use ACSEO\SyliusGeoPlugin\Http\GeoResponseFactory;
use ACSEO\SyliusGeoPlugin\Http\MarkdownAcceptNegotiator;
use ACSEO\SyliusGeoPlugin\Http\ProductMarkdownResponder;
use ACSEO\SyliusGeoPlugin\Http\TaxonMarkdownResponder;
use ACSEO\SyliusGeoPlugin\Locale\ChannelLocaleResolver;
use ACSEO\SyliusGeoPlugin\Locale\ChannelLocaleResolverInterface;
use ACSEO\SyliusGeoPlugin\Markdown\MarkdownSanitizer;
use ACSEO\SyliusGeoPlugin\Model\LlmTxtOptions;
use ACSEO\SyliusGeoPlugin\Model\ProductMarkdownOptions;
use ACSEO\SyliusGeoPlugin\Provider\CmsPageProviderInterface;
use ACSEO\SyliusGeoPlugin\Provider\LlmTxtCmsPagesSectionProvider;
use ACSEO\SyliusGeoPlugin\Provider\LlmTxtSectionProviderInterface;
use ACSEO\SyliusGeoPlugin\Provider\ProductMarkdownSectionProviderInterface;
use ACSEO\SyliusGeoPlugin\Provider\TaxonMarkdownSectionProviderInterface;
use ACSEO\SyliusGeoPlugin\Url\GeoUrlBuilder;
use ACSEO\SyliusGeoPlugin\Url\GeoUrlBuilderInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->instanceof(ProductMarkdownSectionProviderInterface::class)
        ->tag('acseo_sylius_geo_plugin.product_markdown_section_provider');
    $services->instanceof(TaxonMarkdownSectionProviderInterface::class)
        ->tag('acseo_sylius_geo_plugin.taxon_markdown_section_provider');
    $services->instanceof(LlmTxtSectionProviderInterface::class)
        ->tag('acseo_sylius_geo_plugin.llm_txt_section_provider');
    $services->instanceof(CmsPageProviderInterface::class)
        ->tag('acseo_sylius_geo_plugin.cms_page_provider');

    $services->set(ProductGeoVisibilityChecker::class)
        ->autowire()
        ->autoconfigure()
        ->arg('$excludedTaxonCodes', param('acseo_sylius_geo_plugin.product_exclusion.taxon_codes'))
        ->arg('$excludedAttributeCodes', param('acseo_sylius_geo_plugin.product_exclusion.attribute_codes'))
        ->arg('$excludedAttributeValues', param('acseo_sylius_geo_plugin.product_exclusion.attribute_values'));
    $services->alias(ProductGeoVisibilityCheckerInterface::class, ProductGeoVisibilityChecker::class);

    $services->set(ProductGeoExportDiagnostics::class)->autowire()->autoconfigure();
    $services->alias(ProductGeoExportDiagnosticsInterface::class, ProductGeoExportDiagnostics::class);

    $services->set(TaxonGeoVisibilityChecker::class)
        ->autowire()
        ->autoconfigure()
        ->arg('$excludedTaxonCodes', param('acseo_sylius_geo_plugin.product_exclusion.taxon_codes'));
    $services->alias(TaxonGeoVisibilityCheckerInterface::class, TaxonGeoVisibilityChecker::class);

    $services->set(ChannelLocaleResolver::class)->autowire()->autoconfigure();
    $services->alias(ChannelLocaleResolverInterface::class, ChannelLocaleResolver::class);

    $services->set(GeoUrlBuilder::class)
        ->autowire()
        ->autoconfigure()
        ->arg('$llmTxtPath', param('acseo_sylius_geo_plugin.routing.llm_txt_path'))
        ->arg('$geoPrefix', param('acseo_sylius_geo_plugin.routing.geo_prefix'))
        ->arg('$geoProductsPrefix', param('acseo_sylius_geo_plugin.routing.geo_products_prefix'));
    $services->alias(GeoUrlBuilderInterface::class, GeoUrlBuilder::class);

    $services->set(MarkdownSanitizer::class)->autowire()->autoconfigure();

    $services->set(GeoResponseFactory::class)
        ->autowire()
        ->autoconfigure()
        ->arg('$httpCacheMaxAge', param('acseo_sylius_geo_plugin.http_cache.max_age'));

    $services->set(ProductMarkdownResponder::class)
        ->autowire()
        ->autoconfigure()
        ->arg('$productRepository', service('sylius.repository.product'));

    $services->set(TaxonMarkdownResponder::class)
        ->autowire()
        ->autoconfigure()
        ->arg('$taxonRepository', service('sylius.repository.taxon'));

    $services->set(ChannelProductCatalog::class)
        ->autowire()
        ->autoconfigure()
        ->arg('$productRepository', service('sylius.repository.product'))
        ->arg('$productLimit', param('acseo_sylius_geo_plugin.llm_txt.product_limit'))
        ->arg('$maxScannedProducts', param('acseo_sylius_geo_plugin.llm_txt.product_scan_limit'));
    $services->alias(ChannelProductCatalogInterface::class, ChannelProductCatalog::class);

    $services->set(ChannelTaxonCatalog::class)
        ->autowire()
        ->autoconfigure()
        ->arg('$taxonRepository', service('sylius.repository.taxon'))
        ->arg('$taxonLimit', param('acseo_sylius_geo_plugin.llm_txt.taxon_limit'));
    $services->alias(ChannelTaxonCatalogInterface::class, ChannelTaxonCatalog::class);

    $services->set(LlmTxtOptions::class)
        ->args([
            '$includeChannelInfo' => param('acseo_sylius_geo_plugin.channel_info.enabled'),
            '$shopName' => param('acseo_sylius_geo_plugin.channel_info.shop_name'),
            '$baseUrl' => param('acseo_sylius_geo_plugin.channel_info.base_url'),
            '$currencies' => param('acseo_sylius_geo_plugin.channel_info.currencies'),
            '$locales' => param('acseo_sylius_geo_plugin.channel_info.locales'),
            '$servedCountries' => param('acseo_sylius_geo_plugin.channel_info.served_countries'),
            '$includeCrawlerPolicy' => param('acseo_sylius_geo_plugin.crawler_policy.enabled'),
            '$allowAiCrawlers' => param('acseo_sylius_geo_plugin.crawler_policy.allow_ai_crawlers'),
            '$crawlerPolicyHints' => param('acseo_sylius_geo_plugin.crawler_policy.hints'),
        ]);

    $services->set(LlmTxtChannelInfo::class)->autowire()->autoconfigure();

    $services->set(LlmTxtGenerator::class)
        ->autowire()
        ->autoconfigure()
        ->arg('$sectionProviders', tagged_iterator('acseo_sylius_geo_plugin.llm_txt_section_provider'));
    $services->alias(LlmTxtGeneratorInterface::class, LlmTxtGenerator::class);

    $services->set(ProductMarkdownOptions::class)
        ->args([
            '$includeBrand' => param('acseo_sylius_geo_plugin.product_markdown.include_brand'),
            '$stockStrategy' => param('acseo_sylius_geo_plugin.product_markdown.stock_strategy'),
            '$priceStrategy' => param('acseo_sylius_geo_plugin.product_markdown.price_strategy'),
            '$includeImages' => param('acseo_sylius_geo_plugin.product_markdown.include_images'),
            '$includeAttributes' => param('acseo_sylius_geo_plugin.product_markdown.include_attributes'),
            '$includeVariants' => param('acseo_sylius_geo_plugin.product_markdown.include_variants'),
            '$includeTaxons' => param('acseo_sylius_geo_plugin.product_markdown.include_taxons'),
            '$includeJsonLd' => param('acseo_sylius_geo_plugin.product_markdown.include_json_ld'),
        ]);

    $services->set(ProductPricing::class)
        ->autowire()
        ->autoconfigure()
        ->arg('$priceCalculator', service('sylius.calculator.product_variant_price'))
        ->arg('$moneyFormatter', service('sylius.formatter.money'));

    $services->set(ProductMarkdownGenerator::class)
        ->autowire()
        ->autoconfigure()
        ->arg('$sectionProviders', tagged_iterator('acseo_sylius_geo_plugin.product_markdown_section_provider'));
    $services->alias(ProductMarkdownGeneratorInterface::class, ProductMarkdownGenerator::class);

    $services->set(TaxonMarkdownGenerator::class)
        ->autowire()
        ->autoconfigure()
        ->arg('$includeDescription', param('acseo_sylius_geo_plugin.taxon_markdown.include_description'))
        ->arg('$includeChildren', param('acseo_sylius_geo_plugin.taxon_markdown.include_children'))
        ->arg('$sectionProviders', tagged_iterator('acseo_sylius_geo_plugin.taxon_markdown_section_provider'));
    $services->alias(TaxonMarkdownGeneratorInterface::class, TaxonMarkdownGenerator::class);

    $services->set(SitemapGenerator::class)->autowire()->autoconfigure();
    $services->alias(SitemapGeneratorInterface::class, SitemapGenerator::class);

    $services->set(LlmTxtCmsPagesSectionProvider::class)
        ->autowire()
        ->autoconfigure()
        ->arg('$cmsPageProviders', tagged_iterator('acseo_sylius_geo_plugin.cms_page_provider'));

    $services->set(LlmTxtController::class)
        ->autowire()
        ->autoconfigure()
        ->arg('$enabled', param('acseo_sylius_geo_plugin.endpoints.llm_txt'))
        ->public();

    $services->set(ProductMarkdownController::class)
        ->autowire()
        ->autoconfigure()
        ->public()
        ->arg('$enabled', param('acseo_sylius_geo_plugin.endpoints.product_markdown'));

    $services->set(MarkdownAcceptNegotiator::class)->autowire()->autoconfigure();

    $services->set(GeoResponseCookieSubscriber::class)->autowire()->autoconfigure();

    $services->set(GeoLocalizedEndpointLocaleSubscriber::class)
        ->autowire()
        ->autoconfigure()
        ->arg('$llmTxtPath', param('acseo_sylius_geo_plugin.routing.llm_txt_path'))
        ->arg('$geoPrefix', param('acseo_sylius_geo_plugin.routing.geo_prefix'))
        ->arg('$geoProductsPrefix', param('acseo_sylius_geo_plugin.routing.geo_products_prefix'));

    $services->set(ProductMarkdownRequestSubscriber::class)
        ->autowire()
        ->autoconfigure()
        ->arg('$enabled', param('acseo_sylius_geo_plugin.endpoints.product_markdown'));

    $services->set(TaxonMarkdownRequestSubscriber::class)
        ->autowire()
        ->autoconfigure()
        ->arg('$enabled', param('acseo_sylius_geo_plugin.endpoints.taxon_markdown'));

    $services->set(LlmTxtRootRedirectController::class)
        ->autowire()
        ->autoconfigure()
        ->arg('$enabled', param('acseo_sylius_geo_plugin.endpoints.llm_txt'))
        ->public();

    $services->set(SitemapController::class)
        ->autowire()
        ->autoconfigure()
        ->arg('$enabled', param('acseo_sylius_geo_plugin.endpoints.sitemap'))
        ->public();

    $services->set(DebugProductCommand::class)
        ->autowire()
        ->autoconfigure()
        ->arg('$productRepository', service('sylius.repository.product'))
        ->arg('$productMarkdownRouteEnabled', param('acseo_sylius_geo_plugin.endpoints.product_markdown'));

    $services->set(DebugTaxonCommand::class)
        ->autowire()
        ->autoconfigure()
        ->arg('$taxonRepository', service('sylius.repository.taxon'))
        ->arg('$taxonMarkdownRouteEnabled', param('acseo_sylius_geo_plugin.endpoints.taxon_markdown'));

    $services->set(AuditCommand::class)
        ->autowire()
        ->autoconfigure()
        ->arg('$channelRepository', service('sylius.repository.channel'))
        ->arg('$llmTxtRouteEnabled', param('acseo_sylius_geo_plugin.endpoints.llm_txt'))
        ->arg('$productMarkdownRouteEnabled', param('acseo_sylius_geo_plugin.endpoints.product_markdown'))
        ->arg('$taxonMarkdownRouteEnabled', param('acseo_sylius_geo_plugin.endpoints.taxon_markdown'));

    $services->set(LlmTxtApiController::class)
        ->autowire()
        ->autoconfigure()
        ->arg('$enabled', param('acseo_sylius_geo_plugin.endpoints.api'))
        ->public();

    $services->set(ProductMarkdownApiController::class)
        ->autowire()
        ->autoconfigure()
        ->public()
        ->arg('$enabled', param('acseo_sylius_geo_plugin.endpoints.api'));
};
