<?php

declare(strict_types=1);

use ACSEO\SyliusGeoPlugin\EventSubscriber\GeoEndpointGuardSubscriber;
use ACSEO\SyliusGeoPlugin\EventSubscriber\GeoResponseCookieSubscriber;
use ACSEO\SyliusGeoPlugin\EventSubscriber\MarkdownNegotiationSubscriber;
use ACSEO\SyliusGeoPlugin\Http\Endpoint;
use ACSEO\SyliusGeoPlugin\Http\ProductMarkdownResponder;
use ACSEO\SyliusGeoPlugin\Http\TaxonMarkdownResponder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()->defaults()->autowire()->autoconfigure();

    $services->set(GeoEndpointGuardSubscriber::class);
    $services->set(GeoResponseCookieSubscriber::class);

    $services->set('acseo_sylius_geo_plugin.subscriber.product_markdown_negotiation', MarkdownNegotiationSubscriber::class)->args([
        '$route' => 'sylius_shop_product_show',
        '$endpoint' => Endpoint::ProductMarkdown,
        '$responder' => service(ProductMarkdownResponder::class),
    ]);

    $services->set('acseo_sylius_geo_plugin.subscriber.taxon_markdown_negotiation', MarkdownNegotiationSubscriber::class)->args([
        '$route' => 'sylius_shop_product_index',
        '$endpoint' => Endpoint::TaxonMarkdown,
        '$responder' => service(TaxonMarkdownResponder::class),
    ]);
};
