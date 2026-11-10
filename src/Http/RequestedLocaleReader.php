<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Http;

use Symfony\Component\HttpFoundation\Request;

final class RequestedLocaleReader
{
    public function read(Request $request): ?string
    {
        $locale = trim($request->query->getString('locale'));

        return '' === $locale ? null : $locale;
    }
}
