<?php

namespace Noehassiel\Confetti;

use Illuminate\Support\ServiceProvider;

/**
 * The element and its Blade tag are registered by the framework from
 * nativephp.json's `components` array — there is nothing to register here
 * for the element itself. Only the Confetti::burst() facade's binding is
 * ours.
 */
class ConfettiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Confetti::class, fn () => new Confetti);
    }
}
