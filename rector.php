<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;

return RectorConfig::configure()
    ->withPaths([__DIR__ . '/src', __DIR__ . '/tests/Unit', __DIR__ . '/tests/Integration', __DIR__ . '/tests/Functional', __DIR__ . '/tests/Behat', __DIR__ . '/tests/Support'])
    ->withSets([LevelSetList::UP_TO_PHP_83])
;
