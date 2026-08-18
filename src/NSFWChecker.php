<?php

namespace CodyPChristian\NSFWChecker;

use CodyPChristian\NSFWChecker\Exceptions\NSFWCheckerException;

class NSFWChecker
{
    /**
     * The bridge function this plugin registers on the native side.
     */
    public const BRIDGE_FUNCTION = 'NSFWChecker.CheckImage';

    /**
     * Is the native side of this plugin actually reachable?
     *
     * This asks the bridge, not the container. A service provider or facade
     * being autoloadable proves only that a Composer package is installed —
     * the native handler ships in the compiled app, so the bridge registry is
     * the only honest source of truth.
     */
    public function isSupported(): bool
    {
        if (! function_exists('nativephp_call')) {
            return false;
        }

        if (function_exists('nativephp_can')) {
            return nativephp_can(self::BRIDGE_FUNCTION);
        }

        return true;
    }

    /**
     * Analyse an image for sensitive (nude / sexually explicit) content.
     *
     * @param  string  $imagePath  Absolute path to an image file on the device.
     * @return array{isNSFW: bool, isSensitive: bool, available: bool, reason: string|null}
     *
     * @throws NSFWCheckerException when the bridge is unreachable or errors.
     */
    public function checkImage(string $imagePath): array
    {
        if (! $this->isSupported()) {
            throw NSFWCheckerException::unavailable();
        }

        $raw = nativephp_call(self::BRIDGE_FUNCTION, json_encode([
            'imagePath' => $imagePath,
        ]));

        // The router returns null when the function is not in the registry.
        if ($raw === null) {
            throw NSFWCheckerException::unavailable();
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            throw NSFWCheckerException::badResponse($raw);
        }

        if (($decoded['status'] ?? null) === 'error') {
            throw NSFWCheckerException::fromBridge(
                (string) ($decoded['code'] ?? 'UNKNOWN_ERROR'),
                (string) ($decoded['message'] ?? 'Unknown error'),
            );
        }

        $available = (bool) ($decoded['available'] ?? false);
        $isSensitive = (bool) ($decoded['isSensitive'] ?? false);

        return [
            'isNSFW' => $isSensitive,
            'isSensitive' => $isSensitive,
            'available' => $available,
            'reason' => $decoded['reason'] ?? null,
        ];
    }

    /**
     * Boolean convenience wrapper.
     *
     * Note the fail-closed default: when on-device analysis is unavailable
     * (see the README for when that happens on iOS), this returns the value of
     * $default rather than a bare false, so callers do not silently treat
     * "could not check" as "checked and safe".
     *
     * @throws NSFWCheckerException when the bridge is unreachable or errors.
     */
    public function isNSFW(string $imagePath, bool $default = true): bool
    {
        $result = $this->checkImage($imagePath);

        if (! $result['available']) {
            return $default;
        }

        return $result['isNSFW'];
    }
}
