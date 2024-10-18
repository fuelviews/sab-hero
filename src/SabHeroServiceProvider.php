<?php

namespace Fuelviews\SabHero;

use Fuelviews\SabHero\Commands\SabHeroCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class SabHeroServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('sab-hero')
            ->hasCommand(SabHeroCommand::class);
    }
}
