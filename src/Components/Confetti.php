<?php

namespace Noehassiel\Confetti\Components;

use Native\Mobile\Edge\Components\Native\NativeBladeComponent;

/**
 * Maps <native:confetti> onto the `confetti` element.
 *
 * $isSelfClosing must agree with `self_closing` in nativephp.json: a leaf
 * that reports otherwise waits for a closing tag that never arrives.
 */
class Confetti extends NativeBladeComponent
{
    protected bool $isSelfClosing = true;

    protected function elementType(): string
    {
        return 'confetti';
    }
}
