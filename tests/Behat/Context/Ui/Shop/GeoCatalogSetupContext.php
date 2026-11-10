<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use Behat\Step\Given;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Behat\Service\SharedStorageInterface;
use Sylius\Component\Attribute\Factory\AttributeFactoryInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Product\Model\ProductAttributeInterface;
use Sylius\Component\Product\Model\ProductAttributeValueInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Webmozart\Assert\Assert;

final readonly class GeoCatalogSetupContext implements Context
{
    /**
     * @param AttributeFactoryInterface<ProductAttributeInterface> $attributeFactory
     * @param FactoryInterface<ProductAttributeValueInterface> $attributeValueFactory
     */
    public function __construct(
        private SharedStorageInterface $sharedStorage,
        private AttributeFactoryInterface $attributeFactory,
        private FactoryInterface $attributeValueFactory,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Given('this product has the text attribute :code with value :value')]
    public function thisProductHasTheTextAttributeWithValue(string $code, string $value): void
    {
        $product = $this->sharedStorage->get('product');
        Assert::isInstanceOf($product, ProductInterface::class);

        $attributeValue = $this->attributeValueFactory->createNew();
        $attributeValue->setAttribute($this->textAttribute($code));
        $attributeValue->setValue($value);
        $product->addAttribute($attributeValue);

        $this->entityManager->flush();
    }

    private function textAttribute(string $code): ProductAttributeInterface
    {
        $attribute = $this->entityManager->getRepository($this->attributeFactory->createTyped('text')::class)->findOneBy(['code' => $code]);
        if ($attribute instanceof ProductAttributeInterface) {
            return $attribute;
        }

        $attribute = $this->attributeFactory->createTyped('text');
        Assert::isInstanceOf($attribute, ProductAttributeInterface::class);
        $attribute->setCode($code);
        $attribute->setName($code);
        $attribute->setTranslatable(false);
        $this->entityManager->persist($attribute);

        return $attribute;
    }
}
