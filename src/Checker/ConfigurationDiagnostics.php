<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Checker;

use ACSEO\SyliusGeoPlugin\Http\Endpoint;
use ACSEO\SyliusGeoPlugin\Http\EndpointSettings;
use Sylius\Component\Core\Model\ChannelInterface;

final readonly class ConfigurationDiagnostics
{
    private const array AUDITED_ENDPOINTS = [Endpoint::LlmTxt, Endpoint::ProductMarkdown, Endpoint::TaxonMarkdown];

    public function __construct(private EndpointSettings $settings)
    {
    }

    /** @return list<ExportIssue> */
    public function endpointIssues(Endpoint ...$endpoints): array
    {
        $issues = [];
        foreach ($endpoints as $endpoint) {
            if (!$this->settings->isEnabled($endpoint)) {
                $issues[] = new ExportIssue(ExportIssueType::EndpointDisabled, ['%endpoint%' => $endpoint->value]);
            }
        }

        return $issues;
    }

    /** @return list<ExportIssue> */
    public function warnings(ChannelInterface $channel): array
    {
        $issues = $this->endpointIssues(...self::AUDITED_ENDPOINTS);
        if ('' === trim((string) $channel->getHostname())) {
            $issues[] = new ExportIssue(ExportIssueType::EmptyHostname);
        }

        return $issues;
    }
}
