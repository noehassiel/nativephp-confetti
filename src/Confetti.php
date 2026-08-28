<?php

namespace Noehassiel\Confetti;

/**
 * Trigger a burst on a mounted `<native:confetti>` element from anywhere —
 * an action method that doesn't hold (or want to hold) the element's own
 * `fire-token` property.
 *
 * Second-choice API, same billing as `SignaturePad::capture()`: the
 * element's own `fire-token` prop is the first-choice way to fire a burst
 * when the screen already owns that state. This exists for the case where
 * the action that should celebrate can't see the token — a service class,
 * a listener, a shell-level action bar acting on a child component's screen.
 *
 * Async, like every other native call that has to reach the UI thread:
 * dispatches ConfettiBurstFailed if no confetti element with that ref is
 * mounted. On success, the element's own burst fires exactly as if
 * `fire-token` had been bumped — including its own `on_finished` callback,
 * since this reaches the SAME mounted renderer rather than a separate path.
 */
class Confetti
{
    /**
     * @param  string|null  $ref  The target element's `ref` attribute.
     *                            Omit it to hit the element with the default
     *                            ref ('default') — what an element gets when
     *                            no `ref` attribute is set at all, so a
     *                            screen with a single confetti element needs
     *                            no ref anywhere.
     * @return bool whether the request reached the native layer at all
     */
    public function burst(?string $ref = null): bool
    {
        if (! function_exists('nativephp_call')) {
            return false;
        }

        $result = nativephp_call('Confetti.Burst', json_encode([
            'ref' => $ref ?? 'default',
        ]));

        if (! $result) {
            return false;
        }

        $decoded = json_decode($result);

        return ($decoded->status ?? null) !== 'error';
    }
}
