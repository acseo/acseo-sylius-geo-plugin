<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Checker;

enum ExportIssueType: string
{
    case ProductDisabled = 'acseo_sylius_geo_plugin.diagnostic.product_disabled';
    case ProductNotInChannel = 'acseo_sylius_geo_plugin.diagnostic.product_not_in_channel';
    case ProductRejectedByChecker = 'acseo_sylius_geo_plugin.diagnostic.product_rejected_by_checker';
    case ProductWithoutSlug = 'acseo_sylius_geo_plugin.diagnostic.product_without_slug';
    case ProductWithoutEnabledVariants = 'acseo_sylius_geo_plugin.diagnostic.product_without_enabled_variants';
    case VariantWithoutPricing = 'acseo_sylius_geo_plugin.diagnostic.variant_without_pricing';
    case TaxonDisabled = 'acseo_sylius_geo_plugin.diagnostic.taxon_disabled';
    case TaxonRejectedByChecker = 'acseo_sylius_geo_plugin.diagnostic.taxon_rejected_by_checker';
    case TaxonWithoutSlug = 'acseo_sylius_geo_plugin.diagnostic.taxon_without_slug';
    case EndpointDisabled = 'acseo_sylius_geo_plugin.diagnostic.endpoint_disabled';
    case EmptyHostname = 'acseo_sylius_geo_plugin.diagnostic.empty_hostname';
}
