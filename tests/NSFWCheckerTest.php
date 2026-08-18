<?php

use CodyPChristian\NSFWChecker\Exceptions\NSFWCheckerException;
use CodyPChristian\NSFWChecker\NSFWChecker;
use Native\Mobile\Testing\FakeBridge;

afterEach(fn () => FakeBridge::disable());

it('reports unsupported when the bridge does not offer the function', function () {
    FakeBridge::enable()->withoutCapability(NSFWChecker::BRIDGE_FUNCTION);

    expect((new NSFWChecker)->isSupported())->toBeFalse();
});

it('reports supported when the bridge offers the function', function () {
    FakeBridge::enable();

    expect((new NSFWChecker)->isSupported())->toBeTrue();
});

it('calls the bridge with the image path and maps a sensitive result', function () {
    $bridge = FakeBridge::enable();
    $bridge->respondTo(NSFWChecker::BRIDGE_FUNCTION, [
        'available' => true,
        'isSensitive' => true,
    ]);

    $result = (new NSFWChecker)->checkImage('/tmp/photo.jpg');

    expect($result['isNSFW'])->toBeTrue()
        ->and($result['isSensitive'])->toBeTrue()
        ->and($result['available'])->toBeTrue();

    $bridge->assertCalled(
        NSFWChecker::BRIDGE_FUNCTION,
        fn (array $params) => $params['imagePath'] === '/tmp/photo.jpg',
    );
});

it('maps a clean result', function () {
    FakeBridge::enable()->respondTo(NSFWChecker::BRIDGE_FUNCTION, [
        'available' => true,
        'isSensitive' => false,
    ]);

    expect((new NSFWChecker)->isNSFW('/tmp/photo.jpg'))->toBeFalse();
});

it('fails closed when the device cannot analyse the image', function () {
    FakeBridge::enable()->respondTo(NSFWChecker::BRIDGE_FUNCTION, [
        'available' => false,
        'isSensitive' => false,
        'reason' => 'policy_disabled',
    ]);

    $checker = new NSFWChecker;

    // Default is fail-closed: "could not check" must not read as "safe".
    expect($checker->isNSFW('/tmp/photo.jpg'))->toBeTrue()
        ->and($checker->isNSFW('/tmp/photo.jpg', default: false))->toBeFalse()
        ->and($checker->checkImage('/tmp/photo.jpg')['reason'])->toBe('policy_disabled');
});

it('throws when the bridge returns an error envelope', function () {
    FakeBridge::enable()->respondTo(NSFWChecker::BRIDGE_FUNCTION, [
        'status' => 'error',
        'code' => 'INVALID_PARAMETERS',
        'message' => 'Image file not found: /tmp/nope.jpg',
    ]);

    (new NSFWChecker)->checkImage('/tmp/nope.jpg');
})->throws(NSFWCheckerException::class, 'INVALID_PARAMETERS');

it('throws when the native function is not registered', function () {
    FakeBridge::enable()->withoutCapability(NSFWChecker::BRIDGE_FUNCTION);

    (new NSFWChecker)->checkImage('/tmp/photo.jpg');
})->throws(NSFWCheckerException::class);
