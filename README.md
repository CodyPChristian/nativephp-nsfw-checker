# NSFW Checker for NativePHP Mobile

A NativePHP Mobile plugin that checks whether a local image file contains
sensitive (nude or sexually explicit) content, entirely on the device. No
image data leaves the phone and no API keys are involved.

The two platforms are not equivalent, and the difference matters:

| | iOS | Android |
|---|---|---|
| Backend | Apple SensitiveContentAnalysis (`SCSensitivityAnalyzer`) | ML Kit image labelling |
| What it is | Apple's purpose-built nudity classifier | A general-purpose image labeller |
| Reliability | Good — this is the same engine behind Communication Safety | Weak proxy signal only |
| Requires | iOS 17+, entitlement, user has Sensitive Content Warning enabled | Play Services |

Android is a fallback, not a peer. See [Android caveats](#android-caveats).

## Requirements

- PHP 8.3+
- `nativephp/mobile` 4.x
- iOS 17.0+ / Android API 24+

## Installation

```bash
composer require codypchristian/nativephp-nsfw-checker
```

Plugins are opt-in: only providers listed in your `NativeServiceProvider` are
compiled into a build. If you have not published that provider yet:

```bash
php artisan vendor:publish --tag=nativephp-plugins-provider
```

Then register the plugin:

```bash
php artisan native:plugin:register codypchristian/nativephp-nsfw-checker
```

which adds it to the `plugins()` array:

```php
public function plugins(): array
{
    return [
        \CodyPChristian\NSFWChecker\NSFWCheckerServiceProvider::class,
    ];
}
```

Rebuild so the native code is compiled in:

```bash
php artisan native:install --force
```

You can confirm the bridge function was picked up with
`php artisan native:plugin:list` — `NSFWChecker.CheckImage` should be listed
under registered plugins, not under "Unregistered".

### iOS entitlement

The manifest declares
`com.apple.developer.sensitivecontentanalysis.client` and NativePHP merges it
into the app's entitlements file during `native:install`. The entitlement also
has to exist on your App ID in the Apple Developer portal, otherwise the build
will fail to sign. It is a free entitlement — enable "Sensitive Content
Analysis" for the App ID.

## Usage

```php
use CodyPChristian\NSFWChecker\Facades\NSFWChecker;

if (NSFWChecker::isNSFW($imagePath)) {
    return back()->withErrors('That image cannot be uploaded.');
}
```

`$imagePath` must be an absolute path to a file on the device — not a URL and
not a relative path. Paths returned by the camera and gallery plugins are
already absolute.

### Knowing whether the check actually ran

On iOS the analyser is only available when the user has Sensitive Content
Warning switched on (Settings → Privacy & Security → Sensitive Content
Warning). When it is off, the OS will not analyse anything, and no plugin can
work around that. `checkImage()` reports this honestly rather than pretending
the image was checked and found clean:

```php
$result = NSFWChecker::checkImage($imagePath);

// [
//     'isNSFW'      => bool,        // alias of isSensitive
//     'isSensitive' => bool,
//     'available'   => bool,        // false => the check did NOT run
//     'reason'      => ?string,     // 'policy_disabled' | 'os_unsupported' | null
// ]

if (! $result['available']) {
    // Decide your own policy: block, allow, or fall back to a server-side check.
}
```

`isNSFW()` collapses that into a boolean and **fails closed** — if the check
could not run it returns `true`, so an unavailable analyser is never silently
treated as a clean image. Pass `default: false` if you would rather let
unchecked images through:

```php
NSFWChecker::isNSFW($imagePath);                 // unavailable => true  (block)
NSFWChecker::isNSFW($imagePath, default: false); // unavailable => false (allow)
```

### Checking availability up front

```php
if (! NSFWChecker::isSupported()) {
    // Bridge function is not in the build at all — e.g. running on the
    // desktop dev server, or the plugin was never registered.
}
```

This asks the native bridge registry, not the container. Do not gate on
`class_exists()` of a facade or service provider: those come from the Composer
package and are true even when no native code was compiled into the app.

## Errors

`checkImage()` throws `CodyPChristian\NSFWChecker\Exceptions\NSFWCheckerException` when:

- the bridge function is not present in the build (plugin not registered, or app not rebuilt);
- the file does not exist or cannot be decoded;
- the native analyser fails or times out (15s).

An unavailable *analyser* is not an error — that is the `available: false` case above.

## Android caveats

Android has no free, on-device model for explicit content. ML Kit's default
labeller returns general scene and object labels; its label set contains no
nudity or pornography classes at all. What this plugin does on Android is match
apparel and exposed-skin labels (`brassiere`, `swimwear`, `bikini`,
`undergarment`, `barechested`, `abdomen`, …) above a confidence threshold.

That will miss most explicit imagery and will flag innocuous beach photos.
Android results carry `heuristic: true`. Treat Android as a cheap first pass in
front of a server-side check, not as a content-safety control on its own.

The Android path has not been compile-tested or run — see [Status](#status).

## Status

This is an early release. What has actually been verified:

- iOS Swift type-checks against the iOS SDK together with the registration code
  NativePHP generates.
- The PHP layer is covered by tests against `Native\Mobile\Testing\FakeBridge`.
- `php artisan native:plugin:validate` passes.

Not yet verified: the Android/Kotlin build, and on-device behaviour of the
analyser on either platform. Bug reports welcome.

## Testing

```bash
composer install
composer test
```

## License

MIT. See [LICENSE](LICENSE).
