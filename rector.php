<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Class_\InlineConstructorDefaultToPropertyRector;
use Rector\Config\RectorConfig;
use Rector\Core\ValueObject\PhpVersion;
use Rector\Set\ValueObject\DowngradeLevelSetList;

return static function (RectorConfig $rectorConfig): void {
  // get parameters
    $rectorConfig->paths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ]);

    // here we can define, what sets of rules will be applied
    $rectorConfig->phpVersion(PhpVersion::PHP_81);
    $rectorConfig->sets([
        DowngradeLevelSetList::DOWN_TO_PHP_81,
        DowngradeLevelSetList::DOWN_TO_PHP_80,
        DowngradeLevelSetList::DOWN_TO_PHP_74,
        DowngradeLevelSetList::DOWN_TO_PHP_73,
    ]);
};
