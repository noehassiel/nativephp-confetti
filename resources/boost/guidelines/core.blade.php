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

`fire-token`, `preset` (`burst` default, `rain`, `cannon`, `explode`, `festive`), `colors`
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
callback still fires once the burst ends. `ConfettiBurstFailed` (`ref`, `reason`) fires instead
when no confetti element with that ref is mounted.

### Rules that matter

- **Fire with a token, or `Confetti::burst()` when the token isn't reachable.** There is no
  imperative channel into a mounted view — the same constraint `noehassiel/signature-pad`
  documents. `burst()` exists for exactly the case signature-pad's own `capture()` does: the
  caller that should trigger it doesn't hold the element's state.
- **Place it last inside a `<native:stack>`** so it layers over the screen content.
- **Never intercepts touches.** Both renderers are explicitly non-interactive.
- **An idle element costs nothing** — no particles and no animation loop until `fire-token`
  first changes after mount.
