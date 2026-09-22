<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Http;

use ACSEO\SyliusGeoPlugin\Exception\TaxonNotAvailableForGeoException;
use ACSEO\SyliusGeoPlugin\Generator\TaxonMarkdownGeneratorInterface;
use ACSEO\SyliusGeoPlugin\Locale\ChannelLocaleResolverInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;
use Sylius\Component\Taxonomy\Repository\TaxonRepositoryInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Webmozart\Assert\Assert;

final class TaxonMarkdownResponder
{
    /** @param TaxonRepositoryInterface<TaxonInterface> $taxonRepository */
    public function __construct(
        private readonly TaxonRepositoryInterface $taxonRepository,
        private readonly TaxonMarkdownGeneratorInterface $taxonMarkdownGenerator,
        private readonly ChannelContextInterface $channelContext,
        private readonly ChannelLocaleResolverInterface $localeResolver,
        private readonly GeoResponseFactory $geoResponseFactory,
    ) {
    }

    public function createForRouteLocale(string $localeCode, string $slug, bool $varyAccept = false): Response
    {
        $channel = $this->channelContext->getChannel();
        Assert::isInstanceOf($channel, ChannelInterface::class);

        if (!$this->localeResolver->supports($channel, $localeCode)) {
            throw new NotFoundHttpException(\sprintf('Locale "%s" is not available on this channel.', $localeCode));
        }

        $taxon = $this->taxonRepository->findOneBySlug($slug, $localeCode);
        if (!$taxon instanceof TaxonInterface) {
            throw new NotFoundHttpException(\sprintf('Taxon "%s" not found.', $slug));
        }

        try {
            $body = $this->taxonMarkdownGenerator->generate($taxon, $channel, $localeCode);
        } catch (TaxonNotAvailableForGeoException) {
            throw new NotFoundHttpException(\sprintf('Taxon "%s" is not available for GEO export.', $slug));
        }

        $response = $this->geoResponseFactory->create($body, 'text/markdown; charset=utf-8');
        if ($varyAccept) {
            $response->setVary('Accept');
        }

        return $response;
    }
}
