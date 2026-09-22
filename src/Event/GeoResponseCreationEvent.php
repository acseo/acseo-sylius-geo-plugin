<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Event;

use Symfony\Contracts\EventDispatcher\Event;

final class GeoResponseCreationEvent extends Event
{
    public function __construct(
        private string $body,
        private string $contentType,
        private array $headers = [],
    ) {
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $body): void
    {
        $this->body = $body;
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }

    public function setContentType(string $contentType): void
    {
        $this->contentType = $contentType;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function setHeader(string $name, string $value): void
    {
        $this->headers[$name] = $value;
    }
}
