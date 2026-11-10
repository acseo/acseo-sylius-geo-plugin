<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Http;

enum Negotiation
{
    case Markdown;
    case Html;
    case NotAcceptable;
}
