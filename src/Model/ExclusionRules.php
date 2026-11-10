<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Model;

final readonly class ExclusionRules
{
    /** @var list<string> */
    private array $taxonCodes;

    /** @var list<string> */
    private array $attributeCodes;

    /** @var array<string, list<string>> */
    private array $attributeValues;

    /**
     * @param list<scalar> $taxonCodes
     * @param list<scalar> $attributeCodes
     * @param array<string, list<scalar>> $attributeValues
     */
    public function __construct(array $taxonCodes = [], array $attributeCodes = [], array $attributeValues = [])
    {
        $this->taxonCodes = $this->strings($taxonCodes);
        $this->attributeCodes = $this->strings($attributeCodes);
        $this->attributeValues = array_map($this->strings(...), $attributeValues);
    }

    public function excludesTaxonCode(?string $code): bool
    {
        return [] !== $this->taxonCodes && \in_array((string) $code, $this->taxonCodes, true);
    }

    public function hasTaxonCodes(): bool
    {
        return [] !== $this->taxonCodes;
    }

    public function excludesAttributeCode(string $code): bool
    {
        return \in_array($code, $this->attributeCodes, true);
    }

    public function excludesAttributeValue(string $code, string $value): bool
    {
        return \in_array($value, $this->attributeValues[$code] ?? [], true);
    }

    /**
     * @param array<scalar> $values
     *
     * @return list<string>
     */
    private function strings(array $values): array
    {
        return array_values(array_map(static fn (int|float|string|bool $value): string => (string) $value, $values));
    }
}
