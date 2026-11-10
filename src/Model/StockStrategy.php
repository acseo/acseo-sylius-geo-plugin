<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Model;

enum StockStrategy: string
{
    case Hidden = 'hidden';
    case AvailabilityOnly = 'availability_only';
    case Detailed = 'detailed';
}
