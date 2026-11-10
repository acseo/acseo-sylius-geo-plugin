<?php

declare(strict_types=1);

namespace ACSEO\SyliusGeoPlugin\Exception;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class LocaleNotAvailableException extends NotFoundHttpException
{
}
