<?php

namespace Fuelviews\SabHero\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Fuelviews\SabHero\SabHero
 */
class SabHero extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Fuelviews\SabHero\SabHero::class;
    }
}
