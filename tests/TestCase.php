<?php

declare(strict_types=1);

namespace Tests;

use Anodyne\TablerIcons\BladeTablerIconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;

abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    protected function getPackageProviders($app)
    {
        return [
            BladeIconsServiceProvider::class,
            BladeTablerIconsServiceProvider::class,
        ];
    }
}
