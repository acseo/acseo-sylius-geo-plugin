<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Translation;

use ACSEO\SyliusGeoPlugin\Checker\ExportIssue;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class DiagnosticTranslator
{
    public function __construct(private TranslatorInterface $translator)
    {
    }

    /** @param array<string, string|int> $parameters */
    public function text(string $key, array $parameters = []): string
    {
        return $this->translator->trans($key, $parameters);
    }

    /**
     * @param list<ExportIssue> $issues
     *
     * @return list<string>
     */
    public function issues(array $issues): array
    {
        return array_map(fn (ExportIssue $issue): string => $this->text($issue->type->value, $issue->parameters), $issues);
    }
}
