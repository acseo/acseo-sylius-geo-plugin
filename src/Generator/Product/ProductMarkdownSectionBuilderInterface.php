<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Generator\Product;

use ACSEO\SyliusGeoPlugin\Model\MarkdownSection;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('acseo_sylius_geo_plugin.product_markdown_builtin_section')]
interface ProductMarkdownSectionBuilderInterface
{
    public function build(ProductMarkdownContext $context): ?MarkdownSection;
}
