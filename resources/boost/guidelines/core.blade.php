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
`true`), `_finished`. Every preset expands to concrete numbers in PHP before either renderer sees
the node — an explicit attribute always overrides the preset's value for that one prop.

### Rules that matter

- **Fire with a token, not a method call.** There is no imperative channel into a mounted view —
  the same constraint `noehassiel/signature-pad` documents. Bump `fire-token`; any change fires.
- **Place it last inside a `<native:stack>`** so it layers over the screen content.
- **Never intercepts touches.** Both renderers are explicitly non-interactive.
- **An idle element costs nothing** — no particles and no animation loop until `fire-token`
  first changes after mount.
