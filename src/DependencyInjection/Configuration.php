<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('acseo_sylius_geo_plugin');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->arrayNode('http_cache')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('max_age')->min(0)->defaultValue(3600)->end()
                        ->booleanNode('application_cache')->defaultFalse()->end()
                    ->end()
                ->end()
                ->arrayNode('llm_txt')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('product_limit')->min(0)->defaultValue(50)->end()
                        ->integerNode('taxon_limit')->min(0)->defaultValue(20)->end()
                        ->integerNode('product_scan_limit')->min(1)->defaultValue(2000)->end()
                    ->end()
                ->end()
                ->arrayNode('product_markdown')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('include_brand')->defaultTrue()->end()
                        ->enumNode('stock_strategy')->values(['hidden', 'availability_only', 'detailed'])->defaultValue('detailed')->end()
                        ->enumNode('price_strategy')->values(['calculated', 'base', 'hidden'])->defaultValue('calculated')->end()
                        ->booleanNode('include_images')->defaultTrue()->end()
                        ->booleanNode('include_attributes')->defaultTrue()->end()
                        ->booleanNode('include_variants')->defaultTrue()->end()
                        ->booleanNode('include_taxons')->defaultTrue()->end()
                        ->booleanNode('include_json_ld')->defaultTrue()->end()
                    ->end()
                ->end()
                ->arrayNode('taxon_markdown')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('include_description')->defaultTrue()->end()
                        ->booleanNode('include_children')->defaultTrue()->end()
                    ->end()
                ->end()
                ->arrayNode('product_exclusion')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('taxon_codes')
                            ->scalarPrototype()->end()
                        ->end()
                        ->arrayNode('attribute_codes')
                            ->scalarPrototype()->end()
                        ->end()
                        ->arrayNode('attribute_values')
                            ->useAttributeAsKey('code')
                            ->arrayPrototype()
                                ->scalarPrototype()->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('channel_info')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                        ->scalarNode('shop_name')->defaultNull()->end()
                        ->scalarNode('base_url')->defaultNull()->end()
                        ->arrayNode('currencies')
                            ->scalarPrototype()->end()
                        ->end()
                        ->arrayNode('locales')
                            ->scalarPrototype()->end()
                        ->end()
                        ->arrayNode('served_countries')
                            ->scalarPrototype()->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('endpoints')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('llm_txt')->defaultTrue()->end()
                        ->booleanNode('product_markdown')->defaultTrue()->end()
                        ->booleanNode('taxon_markdown')->defaultTrue()->end()
                        ->booleanNode('sitemap')->defaultTrue()->end()
                        ->booleanNode('api')->defaultTrue()->end()
                    ->end()
                ->end()
                ->arrayNode('crawler_policy')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                        ->booleanNode('allow_ai_crawlers')->defaultTrue()->end()
                        ->arrayNode('hints')
                            ->defaultValue([
                                'default' => [
                                    'robots.txt and X-Robots-Tag remain authoritative.',
                                    'Use Markdown pages only for enabled, public catalog facts.',
                                    'Do not use hidden, disabled, cart, checkout, account, or admin pages for product facts.',
                                ],
                            ])
                            ->beforeNormalization()
                                ->ifTrue(static fn (array $value): bool => [] !== $value && array_is_list($value))
                                ->then(static fn (array $value): array => ['default' => $value])
                            ->end()
                            ->arrayPrototype()
                                ->scalarPrototype()->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
