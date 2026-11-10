<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Integration\Translation;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

final class TranslationCatalogTest extends TestCase
{
    private const array SHIPPED_LOCALES = ['en', 'fr'];

    private const string KEY_PATTERN = '/[\'"](acseo_sylius_geo_plugin\.[a-z0-9_]+(?:\.[a-z0-9_]+)+)[\'"]/';

    #[TestDox('Every shipped locale defines the same console translation keys')]
    public function test_shipped_locales_define_the_same_keys(): void
    {
        $reference = $this->keys('messages', 'en');

        foreach (self::SHIPPED_LOCALES as $locale) {
            self::assertSame($reference, $this->keys('messages', $locale), \sprintf('Locale "%s".', $locale));
        }
    }

    #[TestDox('Every translation key used by the plugin code exists in the console or the crawler catalog')]
    public function test_used_keys_exist(): void
    {
        $available = [...$this->keys('messages', 'en'), ...$this->keys('acseo_sylius_geo_plugin_output', 'en')];

        foreach ((new Finder())->files()->in($this->pluginDir() . '/src')->name('*.php') as $file) {
            preg_match_all(self::KEY_PATTERN, $file->getContents(), $matches);

            foreach ($matches[1] as $key) {
                self::assertContains($key, $available, \sprintf('Key "%s" used in "%s" is not translated.', $key, $file->getRelativePathname()));
            }
        }
    }

    #[TestDox('Every console and crawler translation key is used by the plugin code')]
    public function test_declared_keys_are_used(): void
    {
        $code = '';
        foreach ((new Finder())->files()->in($this->pluginDir() . '/src')->name('*.php') as $file) {
            $code .= $file->getContents();
        }

        foreach ([...$this->keys('messages', 'en'), ...$this->keys('acseo_sylius_geo_plugin_output', 'en')] as $key) {
            self::assertStringContainsString("'" . $key . "'", $code, \sprintf('Key "%s" is declared but never used.', $key));
        }
    }

    /** @return list<string> */
    private function keys(string $domain, string $locale): array
    {
        $file = \sprintf('%s/translations/%s.%s.yaml', $this->pluginDir(), $domain, $locale);
        self::assertFileExists($file);

        $keys = $this->flatten((array) Yaml::parseFile($file));
        sort($keys);

        return $keys;
    }

    /**
     * @param array<array-key, mixed> $tree
     *
     * @return list<string>
     */
    private function flatten(array $tree, string $prefix = ''): array
    {
        $keys = [];
        foreach ($tree as $name => $value) {
            $key = '' === $prefix ? (string) $name : $prefix . '.' . $name;
            $keys = \is_array($value) ? [...$keys, ...$this->flatten($value, $key)] : [...$keys, $key];
        }

        return $keys;
    }

    private function pluginDir(): string
    {
        return \dirname(__DIR__, 3);
    }
}
