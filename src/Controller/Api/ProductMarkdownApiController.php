<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Controller\Api;

use ACSEO\SyliusGeoPlugin\Http\ProductMarkdownResponder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ProductMarkdownApiController
{
    public function __construct(
        private readonly ProductMarkdownResponder $productMarkdownResponder,
        private readonly bool $enabled = true,
    ) {
    }

    public function __invoke(Request $request, string $slug): Response
    {
        if (!$this->enabled) {
            throw new NotFoundHttpException('GEO API endpoints are disabled.');
        }

        return $this->productMarkdownResponder->createForRequestedLocale(
            $request->query->getString('locale') !== '' ? $request->query->getString('locale') : null,
            $slug,
        );
    }
}
