<?php

declare(strict_types=1);

namespace Tests\ACSEO\SyliusGeoPlugin\Unit\EventSubscriber;

use ACSEO\SyliusGeoPlugin\Channel\CurrentChannelProvider;
use ACSEO\SyliusGeoPlugin\EventSubscriber\GeoEndpointGuardSubscriber;
use ACSEO\SyliusGeoPlugin\Exception\EndpointDisabledException;
use ACSEO\SyliusGeoPlugin\Exception\LocaleNotAvailableException;
use ACSEO\SyliusGeoPlugin\Http\EndpointSettings;
use ACSEO\SyliusGeoPlugin\Locale\ChannelLocaleResolver;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Tests\ACSEO\SyliusGeoPlugin\Support\CatalogFactory;

final class GeoEndpointGuardSubscriberTest extends TestCase
{
    #[TestDox('A disabled endpoint answers 404')]
    public function test_disabled_endpoint_is_not_found(): void
    {
        $this->expectException(EndpointDisabledException::class);

        $this->guard(new EndpointSettings(llmTxt: false))->onKernelRequest($this->event(['_acseo_sylius_geo_plugin_endpoint' => 'llm_txt']));
    }

    #[TestDox('A locale that is not enabled on the channel answers 404')]
    public function test_unsupported_locale_is_not_found(): void
    {
        $this->expectException(LocaleNotAvailableException::class);

        $this->guard()->onKernelRequest($this->event(['_acseo_sylius_geo_plugin_endpoint' => 'sitemap', '_locale' => 'de_DE']));
    }

    #[TestDox('An enabled endpoint on a supported locale passes')]
    public function test_enabled_endpoint_passes(): void
    {
        $this->guard()->onKernelRequest($this->event(['_acseo_sylius_geo_plugin_endpoint' => 'llm_txt', '_locale' => 'en_US']));
        $this->guard()->onKernelRequest($this->event(['_acseo_sylius_geo_plugin_endpoint' => 'llm_txt']));

        $this->addToAssertionCount(1);
    }

    #[TestDox('A route that is not a GEO endpoint is ignored')]
    public function test_other_routes_are_ignored(): void
    {
        $this->guard(new EndpointSettings(false, false, false, false, false))->onKernelRequest($this->event(['_locale' => 'de_DE']));
        $this->guard(new EndpointSettings(false, false, false, false, false))->onKernelRequest($this->event(['_acseo_sylius_geo_plugin_endpoint' => 'unknown']));

        $this->addToAssertionCount(1);
    }

    #[TestDox('Sub requests are ignored')]
    public function test_sub_requests_are_ignored(): void
    {
        $this->guard(new EndpointSettings(llmTxt: false))->onKernelRequest($this->event(['_acseo_sylius_geo_plugin_endpoint' => 'llm_txt'], HttpKernelInterface::SUB_REQUEST));

        $this->addToAssertionCount(1);
    }

    /** @param array<string, string> $attributes */
    private function event(array $attributes, int $type = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        $request = new Request(attributes: $attributes);

        return new RequestEvent($this->createStub(HttpKernelInterface::class), $request, $type);
    }

    private function guard(?EndpointSettings $settings = null): GeoEndpointGuardSubscriber
    {
        $channelContext = $this->createStub(ChannelContextInterface::class);
        $channelContext->method('getChannel')->willReturn(CatalogFactory::channel());

        return new GeoEndpointGuardSubscriber($settings ?? new EndpointSettings(), new CurrentChannelProvider($channelContext), new ChannelLocaleResolver());
    }
}
