<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Attribute;

use Sylius\Component\Attribute\AttributeType\SelectAttributeType;
use Sylius\Component\Attribute\Model\AttributeInterface;
use Sylius\Component\Attribute\Model\AttributeValueInterface;

final class SelectAttributeLabels
{
    /** @return list<string>|null */
    public static function forLocale(AttributeValueInterface $attributeValue, string $localeCode): ?array
    {
        $choices = self::choices($attributeValue);
        if (null === $choices) {
            return null;
        }

        $labels = [];
        foreach (self::selectedKeys($attributeValue) as $key) {
            $translations = $choices[$key] ?? [];
            $label = self::label($translations, $localeCode) ?? self::firstLabel($translations) ?? $key;
            $labels[] = $label;
        }

        return $labels;
    }

    /** @return list<string> */
    public static function inAnyLocale(AttributeValueInterface $attributeValue): array
    {
        $choices = self::choices($attributeValue);
        if (null === $choices) {
            return [];
        }

        $labels = [];
        foreach (self::selectedKeys($attributeValue) as $key) {
            foreach ($choices[$key] ?? [] as $label) {
                if (\is_string($label) && '' !== trim($label)) {
                    $labels[] = trim($label);
                }
            }
        }

        return array_values(array_unique($labels));
    }

    /** @return array<string, array<mixed>>|null */
    private static function choices(AttributeValueInterface $attributeValue): ?array
    {
        $attribute = $attributeValue->getAttribute();
        if (!$attribute instanceof AttributeInterface || SelectAttributeType::TYPE !== $attribute->getType()) {
            return null;
        }

        $choices = $attribute->getConfiguration()['choices'] ?? [];
        if (!\is_array($choices)) {
            return [];
        }

        $normalized = [];
        foreach ($choices as $key => $translations) {
            if (\is_array($translations)) {
                $normalized[(string) $key] = $translations;
            }
        }

        return $normalized;
    }

    /** @return list<string> */
    private static function selectedKeys(AttributeValueInterface $attributeValue): array
    {
        $value = $attributeValue->getValue();
        $keys = \is_array($value) ? $value : [$value];

        $selected = [];
        foreach ($keys as $key) {
            if (\is_string($key) || \is_int($key)) {
                $selected[] = (string) $key;
            }
        }

        return $selected;
    }

    /** @param array<mixed> $translations */
    private static function label(array $translations, string $localeCode): ?string
    {
        $label = $translations[$localeCode] ?? null;

        return \is_string($label) && '' !== trim($label) ? trim($label) : null;
    }

    /** @param array<mixed> $translations */
    private static function firstLabel(array $translations): ?string
    {
        foreach ($translations as $label) {
            if (\is_string($label) && '' !== trim($label)) {
                return trim($label);
            }
        }

        return null;
    }
}
