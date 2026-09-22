<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\Model;

use ACSEO\SyliusGeoPlugin\Model\LlmTxtOptions;
use PHPUnit\Framework\TestCase;

final class LlmTxtOptionsTest extends TestCase
{
    public function testNoHintsByDefault(): void
    {
        self::assertSame([], (new LlmTxtOptions())->crawlerHintsFor('en_US'));
    }

    public function testAFlatListAppliesToEveryLocale(): void
    {
        $options = new LlmTxtOptions(crawlerPolicyHints: ['One.', 'Two.']);

        self::assertSame(['One.', 'Two.'], $options->crawlerHintsFor('fr_FR'));
    }

    public function testAMapUsesTheLocaleThenTheDefaultKey(): void
    {
        $options = new LlmTxtOptions(crawlerPolicyHints: [
            'default' => ['Default.'],
            'fr_FR' => ['Francais.'],
        ]);

        self::assertSame(['Francais.'], $options->crawlerHintsFor('fr_FR'));
        self::assertSame(['Default.'], $options->crawlerHintsFor('de_DE'));
    }

    public function testNonStringHintsAreDropped(): void
    {
        $options = new LlmTxtOptions(crawlerPolicyHints: ['default' => ['Kept.', 42, true]]);

        self::assertSame(['Kept.'], $options->crawlerHintsFor('en_US'));
    }

    public function testAMapWithoutMatchingKeyHasNoHints(): void
    {
        $options = new LlmTxtOptions(crawlerPolicyHints: ['fr_FR' => ['Francais.']]);

        self::assertSame([], $options->crawlerHintsFor('en_US'));
    }
}
