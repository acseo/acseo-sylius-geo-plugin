<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Integration\Catalog;

use ACSEO\SyliusGeoPlugin\Catalog\ChannelProductCatalog;
use ACSEO\SyliusGeoPlugin\Checker\ProductGeoVisibilityCheckerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Sylius\Component\Currency\Model\Currency;
use Sylius\Component\Locale\Model\Locale;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ChannelProductCatalogIntegrationTest extends KernelTestCase
{
    private const LOCALE = 'en_US';

    private EntityManagerInterface $entityManager;

    private ChannelInterface $channel;

    private int $sequence = 0;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::$kernel->getContainer()->get('doctrine.orm.entity_manager');
        $this->entityManager->getConnection()->beginTransaction();

        $suffix = strtoupper(uniqid());
        $locale = new Locale();
        $locale->setCode(self::LOCALE);
        $currency = new Currency();
        $currency->setCode('USD');
        $this->channel = new Channel();
        $this->channel->setCode('GEO_IT_' . $suffix);
        $this->channel->setName('GEO integration');
        $this->channel->setTaxCalculationStrategy('order_items_based');
        $this->channel->setDefaultLocale($this->entityManager->getRepository(Locale::class)->findOneBy(['code' => self::LOCALE]) ?? $locale);
        $this->channel->setBaseCurrency($this->entityManager->getRepository(Currency::class)->findOneBy(['code' => 'USD']) ?? $currency);

        $this->entityManager->persist($this->channel->getDefaultLocale());
        $this->entityManager->persist($this->channel->getBaseCurrency());
        $this->entityManager->persist($this->channel);
        $this->entityManager->flush();
    }

    protected function tearDown(): void
    {
        $this->entityManager->getConnection()->rollBack();
        $this->entityManager->clear();

        parent::tearDown();
    }

    public function testItSkipsHiddenProductsBeforeApplyingTheLimit(): void
    {
        $this->createProducts('VISIBLE', 3, '-2 days');
        $this->createProducts('HIDDEN', 40, '-1 day');
        $this->entityManager->flush();

        $products = $this->catalog(limit: 3)->findEnabledProducts($this->channel, self::LOCALE);

        self::assertCount(3, $products);
        foreach ($products as $product) {
            self::assertStringStartsWith('VISIBLE', (string) $product->getCode());
        }
    }

    public function testCandidatesAreTheLatestProductsWhateverTheirVisibility(): void
    {
        $this->createProducts('VISIBLE', 3, '-2 days');
        $this->createProducts('HIDDEN', 40, '-1 day');
        $this->entityManager->flush();

        $candidates = $this->catalog(limit: 5)->findCandidateProducts($this->channel, self::LOCALE);

        self::assertCount(5, $candidates);
        foreach ($candidates as $product) {
            self::assertStringStartsWith('HIDDEN', (string) $product->getCode());
        }
    }

    public function testItIgnoresDisabledProductsAndProductsOfOtherChannels(): void
    {
        $this->createProducts('VISIBLE', 2, '-1 day');
        $disabled = $this->createProducts('VISIBLE_DISABLED', 1, '-1 day')[0];
        $disabled->setEnabled(false);
        $foreign = $this->createProducts('VISIBLE_FOREIGN', 1, '-1 day')[0];
        $foreign->removeChannel($this->channel);
        $this->entityManager->flush();

        $codes = array_map(
            static fn (ProductInterface $product): string => (string) $product->getCode(),
            $this->catalog(limit: 10)->findEnabledProducts($this->channel, self::LOCALE),
        );
        sort($codes);

        self::assertSame(['VISIBLE_0', 'VISIBLE_1'], $codes);
    }

    public function testItStopsScanningAfterTheMaximumNumberOfProducts(): void
    {
        $this->createProducts('VISIBLE', 3, '-3 days');
        $this->createProducts('HIDDEN', 2100, '-1 day');
        $this->entityManager->flush();

        $products = $this->catalog(limit: 3)->findEnabledProducts($this->channel, self::LOCALE);

        self::assertSame([], $products, 'Visible products older than the 2000 most recent ones are out of the scan window.');
    }

    private function catalog(int $limit): ChannelProductCatalog
    {
        $repository = self::$kernel->getContainer()->get('sylius.repository.product');
        \assert($repository instanceof ProductRepositoryInterface);

        return new ChannelProductCatalog($repository, $limit, new class() implements ProductGeoVisibilityCheckerInterface {
            public function isVisible(ProductInterface $product, ChannelInterface $channel): bool
            {
                return str_starts_with((string) $product->getCode(), 'VISIBLE');
            }
        });
    }

    /** @return list<Product> */
    private function createProducts(string $prefix, int $count, string $createdAt): array
    {
        $products = [];
        $baseTime = new \DateTimeImmutable($createdAt);
        for ($i = 0; $i < $count; ++$i) {
            $product = new Product();
            $product->setCode($prefix . '_' . $i);
            $product->setCurrentLocale(self::LOCALE);
            $product->setFallbackLocale(self::LOCALE);
            $product->setName($prefix . ' ' . $i);
            $product->setSlug(strtolower($prefix) . '-' . $this->channel->getCode() . '-' . ++$this->sequence);
            $product->addChannel($this->channel);
            $product->setCreatedAt(\DateTime::createFromImmutable($baseTime->modify(\sprintf('+%d seconds', $i))));
            $this->entityManager->persist($product);
            $products[] = $product;
        }

        return $products;
    }
}
