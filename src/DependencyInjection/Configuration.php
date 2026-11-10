<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\DependencyInjection;

use ACSEO\SyliusGeoPlugin\Model\CrawlerPolicyOptions;
use ACSEO\SyliusGeoPlugin\Model\PriceStrategy;
use ACSEO\SyliusGeoPlugin\Model\StockStrategy;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public const string ROOT = 'acseo_sylius_geo_plugin';

    #[\Override]
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(self::ROOT);

        $treeBuilder->getRootNode()
            ->children()
                ->append($this->httpCache())
                ->append($this->catalog())
                ->append($this->productMarkdown())
                ->append($this->taxonMarkdown())
                ->append($this->productExclusion())
                ->append($this->channelInfo())
                ->append($this->endpoints())
                ->append($this->crawlerPolicy())
            ->end()
        ;

        return $treeBuilder;
    }

    private function node(string $name): ArrayNodeDefinition
    {
        return (new TreeBuilder($name))->getRootNode()->addDefaultsIfNotSet();
    }

    private function httpCache(): ArrayNodeDefinition
    {
        $node = $this->node('http_cache');
        $node->children()
            ->integerNode('max_age')->min(0)->defaultValue(3600)->end()
        ->end();

        return $node;
    }

    private function catalog(): ArrayNodeDefinition
    {
        $node = $this->node('catalog');
        $node->children()
            ->integerNode('product_limit')->min(0)->defaultValue(50)->end()
            ->integerNode('taxon_limit')->min(0)->defaultValue(20)->end()
            ->integerNode('product_scan_limit')->min(1)->defaultValue(2000)->end()
        ->end();

        return $node;
    }

    private function productMarkdown(): ArrayNodeDefinition
    {
        $node = $this->node('product_markdown');
        $node->children()
            ->booleanNode('include_brand')->defaultTrue()->end()
            ->enumNode('stock_strategy')->enumFqcn(StockStrategy::class)->defaultValue(StockStrategy::Detailed)->end()
            ->enumNode('price_strategy')->enumFqcn(PriceStrategy::class)->defaultValue(PriceStrategy::Calculated)->end()
            ->booleanNode('include_images')->defaultTrue()->end()
            ->booleanNode('include_attributes')->defaultTrue()->end()
            ->booleanNode('include_variants')->defaultTrue()->end()
            ->booleanNode('include_taxons')->defaultTrue()->end()
            ->booleanNode('include_json_ld')->defaultTrue()->end()
        ->end();

        return $node;
    }

    private function taxonMarkdown(): ArrayNodeDefinition
    {
        $node = $this->node('taxon_markdown');
        $node->children()
            ->booleanNode('include_description')->defaultTrue()->end()
            ->booleanNode('include_children')->defaultTrue()->end()
        ->end();

        return $node;
    }

    private function productExclusion(): ArrayNodeDefinition
    {
        $node = $this->node('product_exclusion');
        $node->children()
            ->arrayNode('taxon_codes')->scalarPrototype()->end()->end()
            ->arrayNode('attribute_codes')->scalarPrototype()->end()->end()
            ->arrayNode('attribute_values')
                ->useAttributeAsKey('code')
                ->arrayPrototype()->scalarPrototype()->end()->end()
            ->end()
        ->end();

        return $node;
    }

    private function channelInfo(): ArrayNodeDefinition
    {
        $node = $this->node('channel_info');
        $node->children()
            ->booleanNode('enabled')->defaultTrue()->end()
            ->booleanNode('include_channel_code')->defaultFalse()->end()
            ->scalarNode('shop_name')->defaultNull()->end()
            ->scalarNode('base_url')->defaultNull()->end()
            ->arrayNode('currencies')->scalarPrototype()->end()->end()
            ->arrayNode('locales')->scalarPrototype()->end()->end()
            ->arrayNode('served_countries')->scalarPrototype()->end()->end()
        ->end();

        return $node;
    }

    private function endpoints(): ArrayNodeDefinition
    {
        $node = $this->node('endpoints');
        $node->children()
            ->booleanNode('llm_txt')->defaultTrue()->end()
            ->booleanNode('product_markdown')->defaultTrue()->end()
            ->booleanNode('taxon_markdown')->defaultTrue()->end()
            ->booleanNode('sitemap')->defaultTrue()->end()
            ->booleanNode('api')->defaultTrue()->end()
        ->end();

        return $node;
    }

    private function crawlerPolicy(): ArrayNodeDefinition
    {
        $node = $this->node('crawler_policy');
        $node->children()
            ->booleanNode('enabled')->defaultTrue()->end()
            ->booleanNode('allow_ai_crawlers')->defaultTrue()->end()
            ->arrayNode('hints')
                ->defaultValue(CrawlerPolicyOptions::DEFAULT_HINTS)
                ->beforeNormalization()
                    ->ifTrue(static fn (mixed $value): bool => \is_array($value) && [] !== $value && array_is_list($value))
                    ->then(static fn (array $value): array => ['default' => $value])
                ->end()
                ->arrayPrototype()->scalarPrototype()->end()->end()
            ->end()
        ->end();

        return $node;
    }
}
