<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Checker;

use ACSEO\SyliusGeoPlugin\Locale\ResourceLocalizer;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;

final readonly class ProductGeoExportDiagnostics implements ProductGeoExportDiagnosticsInterface
{
    public function __construct(
        private ProductGeoVisibilityCheckerInterface $visibilityChecker,
        private ResourceLocalizer $localizer,
    ) {
    }

    #[\Override]
    public function exclusionReasons(ProductInterface $product, ChannelInterface $channel, string $localeCode): array
    {
        $channelCode = (string) $channel->getCode();

        return array_values(array_filter([
            $product->isEnabled() ? null : new ExportIssue(ExportIssueType::ProductDisabled),
            $product->hasChannel($channel) ? null : new ExportIssue(ExportIssueType::ProductNotInChannel, ['%channel%' => $channelCode]),
            $this->visibilityChecker->isVisible($product, $channel) ? null : new ExportIssue(ExportIssueType::ProductRejectedByChecker),
            null !== $this->localizer->productSlug($product, $localeCode) ? null : new ExportIssue(ExportIssueType::ProductWithoutSlug, ['%locale%' => $localeCode]),
            $product->getEnabledVariants()->isEmpty() ? new ExportIssue(ExportIssueType::ProductWithoutEnabledVariants) : null,
        ]));
    }

    #[\Override]
    public function warnings(ProductInterface $product, ChannelInterface $channel): array
    {
        $warnings = [];
        foreach ($product->getEnabledVariants() as $variant) {
            if ($variant instanceof ProductVariantInterface && null === $variant->getChannelPricingForChannel($channel)?->getPrice()) {
                $warnings[] = new ExportIssue(ExportIssueType::VariantWithoutPricing, [
                    '%variant%' => (string) $variant->getCode(),
                    '%channel%' => (string) $channel->getCode(),
                ]);
            }
        }

        return $warnings;
    }
}
