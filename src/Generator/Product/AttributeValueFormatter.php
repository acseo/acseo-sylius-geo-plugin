<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Generator\Product;

use ACSEO\SyliusGeoPlugin\Attribute\SelectAttributeLabelResolver;
use ACSEO\SyliusGeoPlugin\Translation\CrawlerTextTranslator;
use Sylius\Component\Attribute\Model\AttributeValueInterface;

final readonly class AttributeValueFormatter
{
    public function __construct(
        private SelectAttributeLabelResolver $labels,
        private CrawlerTextTranslator $texts,
    ) {
    }

    public function forLocale(AttributeValueInterface $attributeValue, string $localeCode): ?string
    {
        return $this->format($this->labels->forLocale($attributeValue, $localeCode) ?? $attributeValue->getValue());
    }

    private function format(mixed $value): ?string
    {
        return match (true) {
            null === $value => null,
            \is_bool($value) => $this->texts->translate($value ? 'acseo_sylius_geo_plugin.output.product.yes' : 'acseo_sylius_geo_plugin.output.product.no'),
            $value instanceof \DateTimeInterface => $value->format('Y-m-d'),
            \is_array($value) => $this->formatList($value),
            \is_scalar($value) || $value instanceof \Stringable => $this->filled(trim((string) $value)),
            default => null,
        };
    }

    /** @param array<mixed> $values */
    private function formatList(array $values): ?string
    {
        $parts = array_filter(array_map($this->format(...), $values), static fn (?string $part): bool => null !== $part);

        return [] === $parts ? null : implode(', ', $parts);
    }

    private function filled(string $value): ?string
    {
        return '' === $value ? null : $value;
    }
}
