<?php

namespace AlexanderPoellmann\LaravelDpd;

use AlexanderPoellmann\LaravelDpd\Contracts\DpdTransport;
use AlexanderPoellmann\LaravelDpd\Services\RestTransport;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelDpdServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-dpd')
            ->hasConfigFile('dpd');
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(DpdTransport::class, RestTransport::class);
        $this->app->singleton(LaravelDpd::class);
    }
}
