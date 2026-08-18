<?php

namespace CodyPChristian\NSFWChecker;

use Illuminate\Support\ServiceProvider;

class NSFWCheckerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(NSFWChecker::class, fn () => new NSFWChecker);
    }
}
