<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Event;

use ACSEO\SyliusGeoPlugin\Model\MarkdownSection;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Contracts\EventDispatcher\Event;

final class LlmTxtGenerationEvent extends Event
{
    /** @var list<MarkdownSection> */
    private array $sections = [];

    public function __construct(
        private readonly ChannelInterface $channel,
        private readonly string $localeCode,
    ) {
    }

    public function getChannel(): ChannelInterface
    {
        return $this->channel;
    }

    public function getLocaleCode(): string
    {
        return $this->localeCode;
    }

    public function addSection(MarkdownSection $section): void
    {
        $this->sections[] = $section;
    }

    /** @return list<MarkdownSection> */
    public function getSections(): array
    {
        return $this->sections;
    }
}
