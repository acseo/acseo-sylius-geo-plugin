<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Controller;

use ACSEO\SyliusGeoPlugin\Channel\CurrentChannelProvider;
use ACSEO\SyliusGeoPlugin\Generator\LlmTxtGeneratorInterface;
use ACSEO\SyliusGeoPlugin\Http\GeoResponseFactory;
use Symfony\Component\HttpFoundation\Response;

final readonly class LlmTxtController
{
    public function __construct(
        private LlmTxtGeneratorInterface $generator,
        private CurrentChannelProvider $channels,
        private GeoResponseFactory $responseFactory,
    ) {
    }

    public function __invoke(string $_locale): Response
    {
        return $this->responseFactory->create(
            $this->generator->generate($this->channels->current(), $_locale),
            'text/plain; charset=UTF-8',
        );
    }
}
