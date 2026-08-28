## noehassiel/confetti

Ships a new EDGE element, `<native:confetti>` — a particle-burst celebration backed by Konfetti
(Jetpack Compose) on Android and a hand-rolled SwiftUI particle system on iOS. It exists because
EDGE has nothing that animates hundreds of independently-moving particles.

Requires a **rebuild** after install (`native:run`) — plugin renderers are registered by code
generated at build time, so a `composer update` alone leaves an element that serializes but
renders nothing.

### Usage

@verbatim
<code-snippet name="Confetti in a NativeComponent screen" lang="blade">
<native:stack class="w-full h-full">
    <native:column class="p-4 gap-4">
        {{-- your screen content --}}
    </native:column>

    <native:confetti
        :fire-token="$celebrateToken"
        preset="burst"
        _finished="confettiFinished"
        class="w-full h-full" />
</native:stack>
</code-snippet>
@endverbatim

@verbatim
<code-snippet name="The screen half" lang="php">
public int $celebrateToken = 0;

public function markComplete(): void
{
    $this->task->complete();
    $this->celebrateToken++;   // ANY change fires a fresh burst
}

public function confettiFinished(): void
{
    // every particle from the last burst has died
}
</code-snippet>
@endverbatim

### Attributes

`fire-token`, `preset` (`burst` default, `rain`, `cannon`, `explode`, `festive`, `corners`), `colors`
(tailwind names / hex / CSS names), `particle-count`, `duration-ms`, `angle`, `spread`, `speed`,
`max-speed`, `damping`, `position-x`, `position-y`, `time-to-live-ms`, `fade-out` (default
`true`), `_finished`, `ref` (default `'default'` — target for `Confetti::burst($ref)`). Every
preset expands to concrete numbers in PHP before either renderer sees the node — an explicit
attribute always overrides the preset's value for that one prop.

### Triggering from an action that doesn't hold the token

@verbatim
<code-snippet name="Confetti::burst() from an action method" lang="php">
use Noehassiel\Confetti\Facades\Confetti;

public function markComplete(): void
{
    $this->task->complete();

    Confetti::burst();   // hits the element with ref="default" — the default when unset
}
</code-snippet>
@endverbatim

Give the element an explicit `ref` when a screen has more than one, and pass the same string to
`burst($ref)`. It reaches the SAME mounted renderer `fire-token` would — its own `_finished`
callback still fires once the burst ends, and it works with any preset. `ConfettiBurstFailed`
(`ref`, `reason`) fires instead when no confetti element with that ref is mounted.

**Ordering caveat:** `burst()` fires whatever props were last *published* to the device, not a
change your own method makes moments earlier — a handler runs to completion before the framework
re-renders and publishes, so `$this->preset = 'corners'; Confetti::burst();` in ONE method still
fires the OLD preset. Change the preset through a normal render first; `burst()` on a LATER tap
picks up whatever is actually on screen by then.

### The `corners` preset

Fires two cannons simultaneously from the bottom corners, angled up and inward, converging over
the top-center — most other presets fire from one point. It rides a `groups` prop (a list of
`"x,y,angle,spread,particle_count"` strings) rather than the flat position/angle/spread fields;
resolved entirely in PHP, so a renderer only ever sees concrete numbers either way.

### JavaScript (Inertia Vue/React)

The element itself has no JS equivalent — it's compiled native UI, only reachable from a Blade
screen. `resources/js/` covers the one piece an SPA frontend can still use: triggering an
already-mounted element without a Livewire round trip.

@verbatim
<code-snippet name="Triggering a burst from Inertia" lang="js">
import { on } from '#nativephp';
import { burst, Events } from 'noehassiel-confetti';

await burst();          // hits ref="default"
await burst('task-list');

on(Events.ConfettiBurstFailed, ({ ref, reason }) => {
    console.warn('No confetti element mounted for ref', ref, reason);
});
</code-snippet>
@endverbatim

### Rules that matter

- **Fire with a token, or `Confetti::burst()` when the token isn't reachable.** There is no
  imperative channel into a mounted view — the same constraint `noehassiel/signature-pad`
  documents. `burst()` exists for exactly the case signature-pad's own `capture()` does: the
  caller that should trigger it doesn't hold the element's state.
- **Place it last inside a `<native:stack>`** so it layers over the screen content.
- **Never intercepts touches.** Both renderers are explicitly non-interactive.
- **An idle element costs nothing** — no particles and no animation loop until `fire-token`
  first changes after mount.
