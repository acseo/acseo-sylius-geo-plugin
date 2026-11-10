<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Generator\Product;

use ACSEO\SyliusGeoPlugin\Url\GeoUrlBuilderInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;

final readonly class ProductImageUrls
{
    private const int LIMIT = 5;

    public function __construct(private GeoUrlBuilderInterface $urlBuilder)
    {
    }

    /** @return list<string> */
    public function forProduct(ProductInterface $product, ChannelInterface $channel): array
    {
        $urls = [];
        foreach ($product->getImages() as $image) {
            if (null !== $image->getPath()) {
                $urls[] = $this->absolute($channel, $image->getPath());
            }
        }

        return \array_slice(array_values(array_unique($urls)), 0, self::LIMIT);
    }

    private function absolute(ChannelInterface $channel, string $path): string
    {
        $path = trim($path);
        if (false !== filter_var($path, \FILTER_VALIDATE_URL) && \in_array(parse_url($path, \PHP_URL_SCHEME), ['http', 'https'], true)) {
            return $path;
        }

        return $this->urlBuilder->absolute($channel, '/media/image/' . ltrim($path, '/'));
    }
}
