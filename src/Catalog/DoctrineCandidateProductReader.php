<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Catalog;

use Doctrine\ORM\EntityRepository;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;

final readonly class DoctrineCandidateProductReader implements CandidateProductReaderInterface
{
    private const array VISIBILITY_PREFETCHES = [
        [['product.productTaxons', 'productTaxon'], ['productTaxon.taxon', 'taxon']],
        [['product.attributes', 'attribute']],
        [['product.channels', 'channel']],
    ];

    private const array VARIANT_PREFETCHES = [
        [['product.variants', 'variant'], ['variant.channelPricings', 'channelPricing']],
    ];

    /** @param EntityRepository<ProductInterface> $productRepository */
    public function __construct(private EntityRepository $productRepository)
    {
    }

    #[\Override]
    public function page(ChannelInterface $channel, string $localeCode, int $offset, int $size, bool $withVariants = false): array
    {
        /** @var list<ProductInterface> $products */
        $products = $this->productRepository->createQueryBuilder('product')
            ->addSelect('translation')
            ->innerJoin('product.translations', 'translation', 'WITH', 'translation.locale = :locale')
            ->andWhere(':channel MEMBER OF product.channels')
            ->andWhere('product.enabled = true')
            ->addOrderBy('product.createdAt', 'DESC')
            ->addOrderBy('product.id', 'DESC')
            ->setParameter('channel', $channel)
            ->setParameter('locale', $localeCode)
            ->setFirstResult($offset)
            ->setMaxResults($size)
            ->getQuery()
            ->getResult();

        if ([] === $products) {
            return [];
        }

        foreach ($withVariants ? [...self::VISIBILITY_PREFETCHES, ...self::VARIANT_PREFETCHES] : self::VISIBILITY_PREFETCHES as $joins) {
            $this->prefetch($products, $joins);
        }

        return $products;
    }

    /**
     * @param list<ProductInterface> $products
     * @param list<array{string, string}> $joins
     */
    private function prefetch(array $products, array $joins): void
    {
        $queryBuilder = $this->productRepository->createQueryBuilder('product');
        foreach ($joins as [$path, $alias]) {
            $queryBuilder->leftJoin($path, $alias)->addSelect($alias);
        }

        $queryBuilder
            ->andWhere('product IN (:products)')
            ->setParameter('products', $products)
            ->getQuery()
            ->getResult();
    }
}
