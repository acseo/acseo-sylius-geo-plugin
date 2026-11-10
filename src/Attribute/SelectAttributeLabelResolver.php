<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Attribute;

use Sylius\Component\Attribute\AttributeType\SelectAttributeType;
use Sylius\Component\Attribute\Model\AttributeInterface;
use Sylius\Component\Attribute\Model\AttributeValueInterface;

final class SelectAttributeLabelResolver
{
    /** @return list<string>|null */
    public function forLocale(AttributeValueInterface $attributeValue, string $localeCode): ?array
    {
        $choices = $this->choices($attributeValue);
        if (null === $choices) {
            return null;
        }

        $labels = [];
        foreach ($this->selectedKeys($attributeValue) as $key) {
            $translations = $choices[$key] ?? [];
            $labels[] = $this->label($translations, $localeCode) ?? $this->firstLabel($translations) ?? $key;
        }

        return $labels;
    }

    /** @return list<string> */
    public function inAnyLocale(AttributeValueInterface $attributeValue): array
    {
        $labels = [];
        foreach ($this->selectedKeys($attributeValue) as $key) {
            foreach ($this->choices($attributeValue)[$key] ?? [] as $label) {
                if (\is_string($label) && '' !== trim($label)) {
                    $labels[] = trim($label);
                }
            }
        }

        return array_values(array_unique($labels));
    }

    /** @return array<string, array<mixed>>|null */
    private function choices(AttributeValueInterface $attributeValue): ?array
    {
        $attribute = $attributeValue->getAttribute();
        if (!$attribute instanceof AttributeInterface || SelectAttributeType::TYPE !== $attribute->getType()) {
            return null;
        }

        $choices = $attribute->getConfiguration()['choices'] ?? [];
        $normalized = [];
        foreach (\is_array($choices) ? $choices : [] as $key => $translations) {
            if (\is_array($translations)) {
                $normalized[(string) $key] = $translations;
            }
        }

        return $normalized;
    }

    /** @return list<string> */
    private function selectedKeys(AttributeValueInterface $attributeValue): array
    {
        $value = $attributeValue->getValue();

        $selected = [];
        foreach (\is_array($value) ? $value : [$value] as $key) {
            if (\is_string($key) || \is_int($key)) {
                $selected[] = (string) $key;
            }
        }

        return $selected;
    }

    /** @param array<mixed> $translations */
    private function label(array $translations, string $localeCode): ?string
    {
        $label = $translations[$localeCode] ?? null;

        return \is_string($label) && '' !== trim($label) ? trim($label) : null;
    }

    /** @param array<mixed> $translations */
    private function firstLabel(array $translations): ?string
    {
        foreach ($translations as $label) {
            if (\is_string($label) && '' !== trim($label)) {
                return trim($label);
            }
        }

        return null;
    }
}
