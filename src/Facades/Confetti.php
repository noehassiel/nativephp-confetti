<?php

namespace Noehassiel\Confetti\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static bool burst(?string $ref = null)
 *
 * @see \Noehassiel\Confetti\Confetti
 */
class Confetti extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Noehassiel\Confetti\Confetti::class;
    }
}
