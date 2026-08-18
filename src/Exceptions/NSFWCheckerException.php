<?php

namespace CodyPChristian\NSFWChecker\Exceptions;

use RuntimeException;

class NSFWCheckerException extends RuntimeException
{
    public static function unavailable(): self
    {
        return new self(
            'The NSFWChecker.CheckImage bridge function is not available. '
            .'Check the plugin is registered in NativeServiceProvider::plugins() '
            .'and that the app has been rebuilt with `php artisan native:install --force`.'
        );
    }

    public static function fromBridge(string $code, string $message): self
    {
        return new self("NSFWChecker bridge error [{$code}]: {$message}");
    }

    public static function badResponse(string $raw): self
    {
        return new self('NSFWChecker received a malformed bridge response: '.$raw);
    }
}
