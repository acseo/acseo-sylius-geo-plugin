<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Command\Diagnostic;

use Symfony\Component\Console\Input\InputInterface;
use Webmozart\Assert\Assert;

final class InputReader
{
    public function option(InputInterface $input, string $name): ?string
    {
        $value = $input->getOption($name);

        return \is_string($value) && '' !== trim($value) ? trim($value) : null;
    }

    public function slug(InputInterface $input): string
    {
        $slug = $input->getArgument('slug');
        Assert::string($slug);

        return $slug;
    }

    public function wantsMarkdown(InputInterface $input): bool
    {
        return true === $input->getOption('markdown');
    }
}
