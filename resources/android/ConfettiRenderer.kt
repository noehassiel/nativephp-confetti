package com.noehassiel.plugins.confetti.ui

import androidx.compose.foundation.layout.Box
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.SideEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.key
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import com.noehassiel.plugins.confetti.ConfettiRegistry
import com.nativephp.mobile.ui.nativerender.ColorParser
import com.nativephp.mobile.ui.nativerender.NativeUIBridge
import com.nativephp.mobile.ui.nativerender.NativeUINode
import nl.dionsegijn.konfetti.compose.KonfettiView
import nl.dionsegijn.konfetti.compose.OnParticleSystemUpdateListener
import nl.dionsegijn.konfetti.core.Party
import nl.dionsegijn.konfetti.core.PartySystem
import nl.dionsegijn.konfetti.core.Position
import nl.dionsegijn.konfetti.core.emitter.Emitter
import java.util.concurrent.TimeUnit

/**
 * A confetti burst backed by Konfetti's Compose renderer.
 *
 * All physics numbers (angle, spread, speed, damping, ...) arrive already
 * resolved — PHP expands whatever `preset` was chosen and overlays explicit
 * props before the node ever reaches the wire, so this renderer never has
 * to know what a preset means. Same for `colors`: already normalized to
 * `#RRGGBB`/`#AARRGGBB` by TailwindParser on the PHP side, so parsing here
 * is just hex, never a tailwind name.
 *
 * `KonfettiView` is a plain `Canvas` with no gesture modifiers, so it never
 * consumes touches on its own — no extra pass-through guard is needed for
 * confetti to sit over live UI on Android.
 *
 * Two ways to trigger a burst: bump `fire_token` (the element's own screen
 * owns that state), or call `Confetti::burst($ref)` from anywhere else,
 * which reaches [ConfettiRegistry] instead. Both funnel through the same
 * `startBurst()` so neither path can drift from the other.
 */
object ConfettiRenderer {

    @Composable
    fun Render(node: NativeUINode, modifier: Modifier) {
        val p = node.props

        val fireToken = p.getString("fire_token", "")
        val ref = p.getString("ref", "default")

        // getCallbackId() returns a non-nullable Int with 0 as the "absent"
        // sentinel — compare `!= 0`, never `!= null`.
        val onFinishedCb = p.getCallbackId("on_finished")

        var parties by remember { mutableStateOf<List<Party>>(emptyList()) }

        // Bumped on every burst, from either trigger path, and used to key
        // the KonfettiView below — see the note on that key() for why a
        // fresh identity is required per burst rather than updating
        // `parties` on an already-mounted instance.
        var burstGeneration by remember { mutableStateOf(0) }

        fun startBurst() {
            val colors = p.getStringList("colors")
                .map { ColorParser.parse(it) }
                .ifEmpty { DEFAULT_COLORS }

            parties = listOf(
                Party(
                    angle = p.getInt("angle", 270),
                    spread = p.getInt("spread", 90),
                    speed = p.getFloat("speed", 10f),
                    maxSpeed = p.getFloat("max_speed", 30f),
                    damping = p.getFloat("damping", 0.9f),
                    colors = colors,
                    timeToLive = p.getInt("time_to_live_ms", 2500).toLong(),
                    fadeOutEnabled = p.getBool("fade_out", true),
                    position = Position.Relative(
                        p.getFloat("position_x", 0.5f).toDouble(),
                        p.getFloat("position_y", 0.3f).toDouble(),
                    ),
                    emitter = Emitter(p.getInt("duration_ms", 300).toLong(), TimeUnit.MILLISECONDS)
                        .max(p.getInt("particle_count", 80)),
                ),
            )
            burstGeneration++
        }

        // Seeded from whatever fire_token already is at mount — NOT "" —
        // so an element whose initial token happens to be non-empty (e.g.
        // PHP's default int property stringifies to "0") does not fire on
        // first composition. Only a change AFTER mount fires.
        var lastFireToken by remember { mutableStateOf(fireToken) }

        // Any change to fire_token starts a fresh emission — there is no
        // imperative channel into a mounted view, so a token is the only
        // way PHP reaches this renderer BY DEFAULT. An idle element (token
        // never changed) never builds a Party, so it costs nothing.
        LaunchedEffect(fireToken) {
            if (fireToken == lastFireToken) return@LaunchedEffect
            lastFireToken = fireToken
            startBurst()
        }

        // Registered on every committed composition (not just mount), so
        // Confetti::burst() always triggers using this element's CURRENT
        // props rather than whatever they were the first time it appeared —
        // props like preset/colors rarely change at runtime, but SideEffect
        // costs nothing extra to get this right regardless. Only teardown
        // (on ref change or unmount) needs the disposable half.
        SideEffect {
            ConfettiRegistry.register(ref) { startBurst() }
        }
        DisposableEffect(ref) {
            onDispose { ConfettiRegistry.unregister(ref) }
        }

        Box(modifier = modifier) {
            // Konfetti's own Compose sample only ever mounts KonfettiView
            // fresh for each burst (toggling an Idle/Started state) rather
            // than updating `parties` on an already-mounted instance —
            // its internal `LaunchedEffect(Unit)` captures `parties` once
            // and never re-reads it. `key(burstGeneration)` reproduces
            // that: a new generation forces Compose to dispose the old
            // composable and mount a brand new one, so every burst — from
            // either trigger path — gets its own effect.
            if (parties.isNotEmpty()) {
                key(burstGeneration) {
                    KonfettiView(
                        modifier = Modifier,
                        parties = parties,
                        updateListener = object : OnParticleSystemUpdateListener {
                            override fun onParticleSystemEnded(system: PartySystem, activeSystems: Int) {
                                if (activeSystems > 0) return

                                parties = emptyList()

                                if (onFinishedCb != 0) {
                                    NativeUIBridge.sendSheetDismissEvent(onFinishedCb, node.id)
                                }
                            }
                        },
                    )
                }
            }
        }
    }

    /** Matches the default palette Confetti::defaults() sets in PHP. */
    private val DEFAULT_COLORS = listOf(
        ColorParser.parse("#FCE18A"),
        ColorParser.parse("#FF726D"),
        ColorParser.parse("#F4306D"),
        ColorParser.parse("#B48DEF"),
    )
}
