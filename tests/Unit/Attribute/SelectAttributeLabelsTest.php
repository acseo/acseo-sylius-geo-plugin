<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\Attribute;

use ACSEO\SyliusGeoPlugin\Attribute\SelectAttributeLabels;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Attribute\Model\AttributeInterface;
use Sylius\Component\Attribute\Model\AttributeValueInterface;

final class SelectAttributeLabelsTest extends TestCase
{
    public function testItResolvesTheLabelOfTheRequestedLocale(): void
    {
        $value = $this->createSelectValue('3f2a', ['3f2a' => ['en_US' => 'Cotton', 'fr_FR' => 'Coton']]);

        self::assertSame(['Coton'], SelectAttributeLabels::forLocale($value, 'fr_FR'));
    }

    public function testItFallsBackToTheFirstLabelThenToTheKey(): void
    {
        $value = $this->createSelectValue(['3f2a', 'b7c1'], ['3f2a' => ['en_US' => 'Cotton'], 'b7c1' => ['en_US' => '']]);

        self::assertSame(['Cotton', 'b7c1'], SelectAttributeLabels::forLocale($value, 'fr_FR'));
    }

    public function testItIgnoresAttributesThatAreNotSelects(): void
    {
        $attribute = $this->createMock(AttributeInterface::class);
        $attribute->method('getType')->willReturn('text');
        $value = $this->createMock(AttributeValueInterface::class);
        $value->method('getAttribute')->willReturn($attribute);
        $value->method('getValue')->willReturn('ceramic');

        self::assertNull(SelectAttributeLabels::forLocale($value, 'en_US'));
        self::assertSame([], SelectAttributeLabels::inAnyLocale($value));
    }

    public function testItListsTheLabelsOfEveryLocale(): void
    {
        $value = $this->createSelectValue(['private'], ['private' => ['en_US' => 'Private', 'fr_FR' => 'Privé'], 'public' => ['en_US' => 'Public']]);

        self::assertSame(['Private', 'Privé'], SelectAttributeLabels::inAnyLocale($value));
    }

    /** @param array<string, array<string, string>> $choices */
    private function createSelectValue(mixed $selected, array $choices): AttributeValueInterface
    {
        $attribute = $this->createMock(AttributeInterface::class);
        $attribute->method('getType')->willReturn('select');
        $attribute->method('getConfiguration')->willReturn(['choices' => $choices, 'multiple' => \is_array($selected)]);
        $value = $this->createMock(AttributeValueInterface::class);
        $value->method('getAttribute')->willReturn($attribute);
        $value->method('getValue')->willReturn($selected);

        return $value;
    }
}
