<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Http;

enum Endpoint: string
{
    case LlmTxt = 'llm_txt';
    case ProductMarkdown = 'product_markdown';
    case TaxonMarkdown = 'taxon_markdown';
    case Sitemap = 'sitemap';
    case Api = 'api';
}
