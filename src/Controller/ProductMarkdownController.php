<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Controller;

use ACSEO\SyliusGeoPlugin\Http\ProductMarkdownResponder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ProductMarkdownController
{
    public function __construct(
        private readonly ProductMarkdownResponder $productMarkdownResponder,
        private readonly bool $enabled = true,
    ) {
    }

    public function __invoke(string $_locale, string $slug): Response
    {
        if (!$this->enabled) {
            throw new NotFoundHttpException('GEO product Markdown endpoint is disabled.');
        }

        return $this->productMarkdownResponder->createForRouteLocale($_locale, $slug);
    }
}
