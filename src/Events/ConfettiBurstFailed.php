<?php

namespace Noehassiel\Confetti\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when Confetti::burst() names a ref no mounted <native:confetti>
 * element claims — usually because the screen holding it isn't the one
 * currently on screen, or the ref attribute doesn't match.
 */
class ConfettiBurstFailed
{
    use Dispatchable;

    public function __construct(
        public string $ref,
        public string $reason = 'No confetti element with that ref is mounted.',
    ) {}
}
