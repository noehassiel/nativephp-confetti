<?php

namespace Noehassiel\Confetti\Elements;

use Native\Mobile\Edge\CallbackRegistry;
use Native\Mobile\Edge\Element;
use Native\Mobile\Edge\TailwindParser;

/**
 * A full-screen (or element-bounded) particle burst — confetti.
 *
 * EDGE has nothing that animates hundreds of independently-moving particles,
 * so this is a genuinely new element type rather than an arrangement of
 * existing ones: Konfetti (Jetpack Compose) drives Android, a hand-rolled
 * SwiftUI TimelineView + Canvas particle system drives iOS. Both read the
 * same prop surface, resolved to concrete numbers here in PHP so the two
 * renderers can never drift apart on what a preset means.
 *
 * ## Firing
 *
 * There is no imperative channel into a mounted view (see
 * noehassiel/signature-pad for the same constraint, worked out first).
 * `fireToken()` is the only way to trigger a burst: bump it — any change,
 * the value itself is arbitrary — and both renderers watch for that change
 * to start a fresh emission. This also means an idle `<native:confetti>` is
 * inert: no particles exist until the token first changes.
 */
class Confetti extends Element
{
    protected string $type = 'confetti';

    /** @var array<string, mixed> */
    protected array $componentProps = [];

    /**
     * Concrete values for every named preset. `resolveProps()` starts from
     * one of these and overlays whatever the caller set explicitly, so a
     * preset is just a set of defaults — never special-cased in the
     * renderers.
     *
     * @var array<string, array<string, mixed>>
     */
    private const PRESETS = [
        'burst' => [
            'angle' => 270, 'spread' => 170, 'speed' => 14.0, 'max_speed' => 42.0,
            'damping' => 0.94, 'particle_count' => 150, 'duration_ms' => 350,
            'position_x' => 0.5, 'position_y' => 0.4, 'time_to_live_ms' => 3500,
        ],
        'rain' => [
            'angle' => 270, 'spread' => 45, 'speed' => 4.0, 'max_speed' => 8.0,
            'damping' => 0.98, 'particle_count' => 150, 'duration_ms' => 3000,
            'position_x' => 0.5, 'position_y' => 0.0, 'time_to_live_ms' => 4000,
        ],
        'cannon' => [
            'angle' => 270, 'spread' => 20, 'speed' => 25.0, 'max_speed' => 45.0,
            'damping' => 0.92, 'particle_count' => 60, 'duration_ms' => 200,
            'position_x' => 0.5, 'position_y' => 0.9, 'time_to_live_ms' => 2000,
        ],
        'explode' => [
            'angle' => 270, 'spread' => 360, 'speed' => 12.0, 'max_speed' => 35.0,
            'damping' => 0.88, 'particle_count' => 120, 'duration_ms' => 150,
            'position_x' => 0.5, 'position_y' => 0.4, 'time_to_live_ms' => 2500,
        ],
        'festive' => [
            'angle' => 270, 'spread' => 70, 'speed' => 8.0, 'max_speed' => 20.0,
            'damping' => 0.95, 'particle_count' => 200, 'duration_ms' => 4000,
            'position_x' => 0.5, 'position_y' => 0.0, 'time_to_live_ms' => 5000,
        ],
    ];

    /** Fallback palette when no `colors` prop is set. */
    private const DEFAULT_COLORS = ['#FCE18A', '#FF726D', '#F4306D', '#B48DEF'];

    public static function make(): static
    {
        return new static;
    }

    /** Any change fires a fresh emission. The value itself is arbitrary. */
    public function fireToken(int|string $token): static
    {
        $this->componentProps['fire_token'] = (string) $token;

        return $this;
    }

    /** One of the named presets in self::PRESETS; unset props fall back to it. */
    public function preset(string $preset): static
    {
        $this->componentProps['preset'] = $preset;

        return $this;
    }

    /** @param  string[]  $colors  Tailwind palette names, hex, or CSS color names. */
    public function colors(array $colors): static
    {
        $this->componentProps['colors'] = $colors;

        return $this;
    }

    public function particleCount(int $count): static
    {
        $this->componentProps['particle_count'] = $count;

        return $this;
    }

    public function durationMs(int $ms): static
    {
        $this->componentProps['duration_ms'] = $ms;

        return $this;
    }

    /** Emission direction in degrees; 270 fires upward (screen coordinates). */
    public function angle(int $degrees): static
    {
        $this->componentProps['angle'] = $degrees;

        return $this;
    }

    /** Cone width in degrees around `angle`. 360 emits in every direction. */
    public function spread(int $degrees): static
    {
        $this->componentProps['spread'] = $degrees;

        return $this;
    }

    public function speed(float $speed): static
    {
        $this->componentProps['speed'] = $speed;

        return $this;
    }

    public function maxSpeed(float $speed): static
    {
        $this->componentProps['max_speed'] = $speed;

        return $this;
    }

    /** Per-frame velocity multiplier; closer to 1.0 drifts longer. */
    public function damping(float $damping): static
    {
        $this->componentProps['damping'] = $damping;

        return $this;
    }

    /** Emission origin as a fraction of the element's own bounds (0–1). */
    public function position(float $x, float $y): static
    {
        $this->componentProps['position_x'] = $x;
        $this->componentProps['position_y'] = $y;

        return $this;
    }

    public function timeToLiveMs(int $ms): static
    {
        $this->componentProps['time_to_live_ms'] = $ms;

        return $this;
    }

    public function fadeOut(bool $fadeOut = true): static
    {
        $this->componentProps['fade_out'] = $fadeOut;

        return $this;
    }

    /** Method to call once every particle from the current burst has died. */
    public function onFinished(string $method): static
    {
        $this->componentProps['on_finished'] = $method;

        return $this;
    }

    /**
     * Stable id so Confetti::burst() can find this element from outside the
     * component that renders it. Defaults to 'default' (not the node id, the
     * way signature-pad's ref does) so a screen with a single confetti
     * element needs no ref at all — Confetti::burst() with no argument
     * already matches it.
     */
    public function ref(string $ref): static
    {
        $this->componentProps['ref'] = $ref;

        return $this;
    }

    /**
     * Blade attributes → props.
     *
     * Plugin elements get no `instanceof` chain in the collector, so an
     * attribute not read here is dropped in silence — every documented
     * attribute must appear below.
     *
     * @param  array<string, mixed>  $attrs
     */
    public function applyAttributes(array $attrs): void
    {
        if (isset($attrs['fire-token'])) {
            $this->fireToken($attrs['fire-token']);
        }

        if (! empty($attrs['preset'])) {
            $this->preset((string) $attrs['preset']);
        }

        if (isset($attrs['colors']) && is_array($attrs['colors'])) {
            $this->colors($attrs['colors']);
        }

        if (isset($attrs['particle-count'])) {
            $this->particleCount((int) $attrs['particle-count']);
        }

        if (isset($attrs['duration-ms'])) {
            $this->durationMs((int) $attrs['duration-ms']);
        }

        if (isset($attrs['angle'])) {
            $this->angle((int) $attrs['angle']);
        }

        if (isset($attrs['spread'])) {
            $this->spread((int) $attrs['spread']);
        }

        if (isset($attrs['speed'])) {
            $this->speed((float) $attrs['speed']);
        }

        if (isset($attrs['max-speed'])) {
            $this->maxSpeed((float) $attrs['max-speed']);
        }

        if (isset($attrs['damping'])) {
            $this->damping((float) $attrs['damping']);
        }

        if (isset($attrs['position-x']) || isset($attrs['position-y'])) {
            $this->position(
                isset($attrs['position-x']) ? (float) $attrs['position-x'] : ($this->componentProps['position_x'] ?? 0.5),
                isset($attrs['position-y']) ? (float) $attrs['position-y'] : ($this->componentProps['position_y'] ?? 0.5),
            );
        }

        if (isset($attrs['time-to-live-ms'])) {
            $this->timeToLiveMs((int) $attrs['time-to-live-ms']);
        }

        if (isset($attrs['fade-out'])) {
            $this->fadeOut(filter_var($attrs['fade-out'], FILTER_VALIDATE_BOOLEAN));
        }

        foreach (['_finished', 'finished', 'on-finished'] as $key) {
            if (! empty($attrs[$key])) {
                $this->onFinished((string) $attrs[$key]);
                break;
            }
        }

        if (! empty($attrs['ref'])) {
            $this->ref((string) $attrs['ref']);
        }
    }

    /** @return array<string, mixed> */
    protected function defaults(): array
    {
        return array_merge(self::PRESETS['burst'], [
            'preset' => 'burst',
            'colors' => self::DEFAULT_COLORS,
            'fade_out' => true,
            'ref' => 'default',
        ]);
    }

    /**
     * Expand the preset, overlay explicit props, normalize colors to hex, and
     * register the callback. Both renderers only ever see fully concrete
     * numbers — a preset is a PHP-only concept.
     *
     * @return array<string, mixed>
     */
    protected function resolveProps(CallbackRegistry $registry): array
    {
        $preset = self::PRESETS[$this->componentProps['preset'] ?? 'burst'] ?? self::PRESETS['burst'];

        $props = array_merge(
            ['preset' => 'burst', 'colors' => self::DEFAULT_COLORS, 'fade_out' => true],
            $preset,
            $this->componentProps,
        );

        $props['colors'] = array_map(
            fn (string $color) => TailwindParser::resolveColorValue($color) ?? $color,
            $props['colors'],
        );

        if (isset($props['on_finished'])) {
            $props['on_finished'] = $registry->register($props['on_finished']);
        }

        return $props;
    }
}
