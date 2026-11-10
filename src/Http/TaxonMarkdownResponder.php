<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Http;

use ACSEO\SyliusGeoPlugin\Exception\TaxonNotAvailableForGeoException;
use ACSEO\SyliusGeoPlugin\Generator\TaxonMarkdownGeneratorInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;
use Sylius\Component\Taxonomy\Repository\TaxonRepositoryInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class TaxonMarkdownResponder implements MarkdownResponderInterface
{
    /** @param TaxonRepositoryInterface<TaxonInterface> $taxonRepository */
    public function __construct(
        private TaxonRepositoryInterface $taxonRepository,
        private TaxonMarkdownGeneratorInterface $generator,
        private GeoResponseFactory $responseFactory,
    ) {
    }

    #[\Override]
    public function respond(ChannelInterface $channel, string $localeCode, string $slug): Response
    {
        $taxon = $this->taxonRepository->findOneBySlug($slug, $localeCode) ?? throw new NotFoundHttpException();

        try {
            $body = $this->generator->generate($taxon, $channel, $localeCode);
        } catch (TaxonNotAvailableForGeoException) {
            throw new NotFoundHttpException();
        }

        return $this->responseFactory->create($body, 'text/markdown; charset=utf-8');
    }
}
