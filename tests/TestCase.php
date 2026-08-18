<?php

namespace Tests;

use CodyPChristian\NSFWChecker\NSFWCheckerServiceProvider;
use Native\Mobile\NativeServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            NativeServiceProvider::class,
            NSFWCheckerServiceProvider::class,
        ];
    }
}
