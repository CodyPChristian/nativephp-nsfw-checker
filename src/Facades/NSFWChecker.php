<?php

namespace CodyPChristian\NSFWChecker\Facades;

use CodyPChristian\NSFWChecker\NSFWChecker as NSFWCheckerService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static bool isSupported()
 * @method static array checkImage(string $imagePath)
 * @method static bool isNSFW(string $imagePath, bool $default = true)
 *
 * @see NSFWCheckerService
 */
class NSFWChecker extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return NSFWCheckerService::class;
    }
}
