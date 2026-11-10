<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Http;

use Symfony\Component\HttpFoundation\AcceptHeader;

final class MarkdownAcceptNegotiator
{
    private const array HTML_TYPES = ['text/html', 'application/xhtml+xml'];

    public function negotiate(string $acceptHeader): Negotiation
    {
        $accepted = AcceptHeader::fromString($acceptHeader);
        $markdownQuality = ($accepted->all()['text/markdown'] ?? null)?->getQuality() ?? 0.0;
        $htmlQuality = max(array_map(static fn (string $type): float => $accepted->get($type)?->getQuality() ?? 0.0, self::HTML_TYPES));

        if ($markdownQuality <= 0.0 && $htmlQuality <= 0.0 && $this->acceptsSomething($accepted)) {
            return Negotiation::NotAcceptable;
        }

        return $markdownQuality > 0.0 && $markdownQuality >= $htmlQuality ? Negotiation::Markdown : Negotiation::Html;
    }

    private function acceptsSomething(AcceptHeader $accepted): bool
    {
        foreach ($accepted->all() as $item) {
            if ($item->getQuality() > 0.0) {
                return true;
            }
        }

        return false;
    }
}
