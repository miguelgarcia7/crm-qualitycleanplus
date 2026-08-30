<?php

use App\Providers\AppServiceProvider;

/**
 * The guard runs at boot, so it cannot be triggered by mutating config inside an
 * already-booted test. Call it directly against a provider bound to a throwaway
 * app whose config carries the domains under test.
 */
function bootGuardWith(?string $main, ?string $qcminute): void
{
    config()->set('domains.main', $main);
    config()->set('domains.qcminute', $qcminute);

    (new AppServiceProvider(app()))->assertSurfaceDomainsAreDistinct();
}

afterEach(function () {
    config()->set('domains.main', 'qcpminute.test');
    config()->set('domains.qcminute', 'qcminute.test');
});

it('allows two distinct hostnames', function () {
    bootGuardWith('qualitycleanplus.com', 'qcpstaffing.com');
})->throwsNoExceptions();

it('allows a placeholder while only one surface is deployed', function () {
    // Running just the back office: point the unused surface at a hostname that
    // never resolves rather than reusing the live one.
    bootGuardWith('crm.laravel.cloud', 'qcminute.invalid');
})->throwsNoExceptions();

it('refuses to boot when both surfaces share a hostname', function () {
    bootGuardWith('crm.laravel.cloud', 'crm.laravel.cloud');
})->throws(RuntimeException::class, 'must be two different, non-empty hostnames');

it('treats whitespace-only difference as the same hostname', function () {
    bootGuardWith('crm.laravel.cloud', '  crm.laravel.cloud  ');
})->throws(RuntimeException::class);

it('refuses to boot when either hostname is missing', function (?string $main, ?string $qcminute) {
    bootGuardWith($main, $qcminute);
})->throws(RuntimeException::class)->with([
    'main empty' => ['', 'qcpstaffing.com'],
    'qcminute empty' => ['qualitycleanplus.com', ''],
    'both null' => [null, null],
]);
