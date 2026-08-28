<?php

namespace Noehassiel\Confetti;

use Illuminate\Support\ServiceProvider;

/**
 * The element and its Blade tag are registered by the framework from
 * nativephp.json's `components` array — there is nothing to register here.
 * Confetti has no facade and no bridge functions: firing is purely
 * declarative (bump `fire-token`), so there is no PHP-side API surface to
 * bind.
 */
class ConfettiServiceProvider extends ServiceProvider
{
    //
}
