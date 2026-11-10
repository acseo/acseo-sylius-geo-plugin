<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Locale;

use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;
use Sylius\Resource\Model\TranslatableInterface;

final class ResourceLocalizer
{
    public function localize(TranslatableInterface $resource, string $localeCode): void
    {
        $resource->setCurrentLocale($localeCode);
        $resource->setFallbackLocale($localeCode);
    }

    public function productSlug(ProductInterface $product, string $localeCode): ?string
    {
        $this->localize($product, $localeCode);

        return $this->filled($product->getSlug());
    }

    public function taxonSlug(TaxonInterface $taxon, string $localeCode): ?string
    {
        $this->localize($taxon, $localeCode);

        return $this->filled($taxon->getSlug());
    }

    public function productName(ProductInterface $product, string $localeCode): string
    {
        $this->localize($product, $localeCode);

        return $this->filled($product->getName()) ?? (string) $product->getCode();
    }

    public function taxonName(TaxonInterface $taxon, string $localeCode): string
    {
        $this->localize($taxon, $localeCode);

        return $this->filled($taxon->getName()) ?? (string) $taxon->getCode();
    }

    private function filled(?string $value): ?string
    {
        return null === $value || '' === trim($value) ? null : $value;
    }
}
