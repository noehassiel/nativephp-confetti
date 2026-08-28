# noehassiel/confetti

Native confetti celebrations for [NativePHP Mobile](https://nativephp.com/docs/mobile/4/introduction)
v4 — a custom EDGE element, `<native:confetti>`, backed by
[Konfetti](https://github.com/DanielMartinus/Konfetti) (Jetpack Compose) on Android and a
hand-rolled SwiftUI particle system on iOS.

EDGE has nothing that animates hundreds of independently-moving particles, so this is a genuinely
new element type rather than an arrangement of existing ones — the case the
[UI Component Plugin](https://nativephp.com/docs/mobile/4/plugins/ui-components) mechanism exists
for.

## Why not [ConfettiKit](https://github.com/vinceglb/ConfettiKit)?

ConfettiKit is a great library, but it's Compose *Multiplatform* — its iOS target is Compose-on-iOS,
not SwiftUI, and NativePHP's iOS renderer tree is SwiftUI. Using it would mean bundling the entire
Compose Multiplatform runtime into the IPA just to bridge Compose into a SwiftUI tree — no smaller
or simpler than writing SwiftUI directly. ConfettiKit is itself a Kotlin Multiplatform port of
Konfetti, so this package uses the original Konfetti on Android (same `Party`/`Emitter`/`Position`
API, no Compose Multiplatform runtime, no Kotlin 2.1+ floor) and a from-scratch SwiftUI
`TimelineView` + `Canvas` particle system on iOS, tuned to the same physics model.

## Install

```shell
composer require noehassiel/confetti
php artisan vendor:publish --tag=nativephp-plugins-provider   # first plugin only
php artisan native:plugin:register noehassiel/confetti
php artisan native:plugin:list                                # confirm it's registered
```

Then rebuild — `php artisan native:run ios` / `android`. **A rebuild is required**: renderer
registration is generated at build time, so `composer update` alone leaves an element that
serializes but renders nothing.

## Usage

```blade
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
```

```php
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
```

Place `<native:confetti>` **last** inside a [`<native:stack>`](https://nativephp.com/docs/mobile/4/edge-components/stack)
so it layers on top of the screen content — it never intercepts touches, so nothing beneath it
needs to change.

## Attributes

| Attribute | Type | Default | Notes |
|---|---|---|---|
| `fire-token` | int\|string | — | **Any change fires a burst.** The value itself is arbitrary. |
| `preset` | string | `burst` | `burst` \| `rain` \| `cannon` \| `explode` \| `festive` |
| `colors` | string[] | preset palette | Tailwind palette names, hex, or CSS color names |
| `particle-count` | int | preset | |
| `duration-ms` | int | preset | How long the burst spreads its particle spawns over |
| `angle` | int (deg) | preset | Emission direction; 270 = straight up |
| `spread` | int (deg) | preset | Cone width around `angle`; 360 = every direction |
| `speed` / `max-speed` | float | preset | Random speed range per particle |
| `damping` | float | preset | Per-frame velocity multiplier; closer to 1.0 drifts longer |
| `position-x` / `position-y` | float 0–1 | preset | Emission origin, relative to the element's own bounds |
| `time-to-live-ms` | int | preset | |
| `fade-out` | bool | `true` | |
| `_finished` | callback | — | Fires once every particle from the burst has died |

Every preset is a PHP-only concept — `Confetti::resolveProps()` expands it into concrete numbers
before the node reaches either renderer, so the two platforms can never drift on what a preset
means. An attribute you set explicitly always overrides the preset's value for that one prop.

## Rules that matter

- **Fire with a token, not a method call.** There is no imperative channel into a mounted view —
  the same constraint [noehassiel/signature-pad](https://github.com/noehassiel/nativephp-signature-pad)
  documents. Bump `fire-token`; both renderers watch for the value changing.
- **An idle element costs nothing.** No particles exist, and no animation loop runs, until
  `fire-token` first changes after mount.
- **Never intercepts touches.** Both renderers are explicitly non-interactive — confetti is meant
  to sit over live UI, not block it.

## License

MIT.
