/**
 * noehassiel/confetti — JavaScript bridge for Inertia (Vue/React) apps.
 *
 * The `<native:confetti>` EDGE element itself has no JS equivalent — it's a
 * compiled native view (SwiftUI/Compose), not a web component, so it only
 * exists in NativePHP's native-UI screens. This module exists for the one
 * piece of the plugin an SPA frontend CAN reach: triggering an
 * already-mounted element from JavaScript instead of a Livewire action,
 * the same way `Noehassiel\Confetti\Facades\Confetti::burst()` does from
 * PHP.
 */

const BRIDGE_URL = '/_native/api/call';

async function bridgeCall(method, params = {}) {
    const response = await fetch(BRIDGE_URL, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
        },
        body: JSON.stringify({ method, params }),
    });

    const result = await response.json();

    if (result.status === 'error') {
        throw new Error(result.message || 'Native call failed');
    }

    return result.data?.data !== undefined ? result.data.data : result.data;
}

/**
 * Trigger a burst on a mounted `<native:confetti>` element.
 *
 * Asynchronous: resolves once the request reached the native layer, not
 * once the burst finishes. Fires whatever props are CURRENTLY published on
 * the target element — the same ordering caveat as the PHP facade: a
 * preset/prop change your own code just made isn't visible to this call
 * until the screen has actually re-rendered.
 *
 * @param {string} [ref] The element's `ref` attribute. Omit to hit the
 *        element with the default ref ('default') — what an element gets
 *        when no `ref` attribute is set at all.
 */
export async function burst(ref) {
    return bridgeCall('Confetti.Burst', { ref: ref ?? 'default' });
}

/** Event names, so callers never hardcode the namespaced string. */
export const Events = {
    ConfettiBurstFailed: 'Noehassiel\\Confetti\\Events\\ConfettiBurstFailed',
};

export const Confetti = { burst };

export default Confetti;
