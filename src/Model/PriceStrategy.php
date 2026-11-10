<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Model;

enum PriceStrategy: string
{
    case Calculated = 'calculated';
    case Base = 'base';
    case Hidden = 'hidden';
}
