import SwiftUI

/**
 * A confetti burst — a hand-rolled SwiftUI particle system.
 *
 * There is no Compose-Multiplatform-on-iOS story that plugs cleanly into a
 * SwiftUI renderer tree, so unlike the Android half (which reuses Konfetti)
 * this is bespoke physics tuned to match it: gravity, a per-frame `damping`
 * multiplier on velocity, particles staggered across `duration_ms` rather
 * than all spawning at once, a fade over the tail of each particle's
 * `time_to_live`, spawn points jittered across an AREA rather than one
 * pixel, and the same width-flattening "3D flip" illusion Konfetti's own
 * engine uses (`Confetti.kt`'s `rotationWidth`/`scaleX`) so a flat shape
 * reads as tumbling paper rather than a plain dot. All the numbers driving
 * it — angle, spread, speed, damping, colors, ... — arrive already
 * resolved: PHP expands whatever `preset` was chosen and overlays explicit
 * props before the node reaches the wire, so this renderer never has to
 * know what a preset means, and both platforms read the identical prop
 * surface.
 *
 * `.allowsHitTesting(false)` matters here in the way it does NOT for
 * `SignaturePadRenderer` — that pad needs touches, confetti must never
 * steal them from the UI it sits over.
 *
 * Two ways to trigger a burst: bump `fire-token` (the element's own screen
 * owns that state), or call `Confetti::burst($ref)` from anywhere else,
 * which reaches `ConfettiRegistry` instead. Both funnel through `fire()` so
 * neither path can drift from the other. The registry entry is refreshed on
 * every render (not just mount) so a burst() always uses this element's
 * CURRENT props — preset/colors rarely change at runtime, but this costs
 * nothing to get right regardless.
 */
struct ConfettiRenderer: View {
    let node: NativeUINode

    @State private var particles: [Particle] = []
    @State private var lastFireToken: String?
    @State private var isIdle = true

    private enum ParticleShape {
        case square, circle
    }

    private struct Particle: Identifiable {
        let id = UUID()

        /// Relative (0–1) at creation; `step()` resolves it to absolute
        /// canvas points the first time it sees `isOriginResolved == false`,
        /// once the canvas size is known. Each particle carries its OWN
        /// origin (already jittered by `fire()`) rather than sharing one
        /// pending point, so a burst reads as coming from an AREA.
        var x: CGFloat
        var y: CGFloat
        var isOriginResolved = false

        var vx: CGFloat
        var vy: CGFloat
        var rotation: Double
        var rotationSpeed: Double

        /// Flips per second for the width-flattening illusion below —
        /// varied per particle so a burst doesn't flutter in unison.
        let flipSpeed: Double

        var color: Color
        var size: CGFloat
        let shape: ParticleShape
        let spawnAt: Date
        let timeToLive: Double
        let fadeOut: Bool

        func hasSpawned(at now: Date) -> Bool {
            now >= spawnAt
        }

        func isDead(at now: Date) -> Bool {
            now.timeIntervalSince(spawnAt) >= timeToLive
        }

        func opacity(at now: Date) -> Double {
            guard fadeOut else { return 1 }

            // Fade over the last third of life, not the whole thing —
            // closer to Konfetti's own fadeOutEnabled feel than a linear
            // fade from birth.
            let fadeStart = timeToLive * (2.0 / 3.0)
            let elapsed = now.timeIntervalSince(spawnAt)
            guard elapsed > fadeStart else { return 1 }

            return max(0, 1 - (elapsed - fadeStart) / (timeToLive - fadeStart))
        }

        /// The "3D flip": Konfetti continuously shrinks a shape's drawn
        /// width to zero and back (`rotationWidth` in `Confetti.kt`), which
        /// combined with a 2D rotation makes a flat square read as a piece
        /// of paper tumbling edge-on rather than a static dot. Derived
        /// straight from elapsed time (a triangle wave 1→0→1) instead of
        /// integrating a mutable field every frame — same visual result,
        /// no extra per-frame state to carry.
        func flutterScaleX(at now: Date) -> CGFloat {
            let elapsed = now.timeIntervalSince(spawnAt)
            let cycle = (elapsed * flipSpeed).truncatingRemainder(dividingBy: 1)

            return CGFloat(abs(cycle - 0.5) * 2)
        }
    }

    /// Gravity in points/second², tuned to read the same as Konfetti's own
    /// default fall speed at similar `speed`/`maxSpeed` values.
    private let gravity: CGFloat = 60

    /// Konfetti's own engine treats a Party's `speed`/`maxSpeed` as points
    /// displaced per RENDERED FRAME, not per second — its position update
    /// multiplies velocity by `deltaTime * frameRate`, and frameRate is
    /// recomputed as `1 / deltaTime` every frame, so those cancel out and
    /// the raw prop value is applied once per frame regardless of the
    /// actual frame rate. This renderer integrates true points-per-second
    /// instead (deltaTime-scaled, not frame-count-scaled), so the SAME
    /// preset numbers (a `speed` of 10, say) need converting to match —
    /// otherwise iOS moves particles ~60x slower than Android's Konfetti
    /// for the identical prop value, which is what made early builds look
    /// like a tiny, barely-moving clump instead of a burst.
    private static let speedToPointsPerSecond: CGFloat = 60

    /// How far a particle's spawn point is jittered from `position_x` /
    /// `position_y`, as a fraction of the canvas. A wide `spread` angle
    /// alone still reads as fireworks from one pixel; real confetti effects
    /// fire from an AREA, not a point.
    private static let originJitterX: ClosedRange<CGFloat> = -0.3...0.3
    private static let originJitterY: ClosedRange<CGFloat> = -0.05...0.05

    var body: some View {
        let p = node.props

        let fireToken = p.getString("fire_token", default: "")
        let ref = p.getString("ref", default: "default")
        let onFinishedCb = p.getCallbackId("on_finished")

        // See the type doc: registered on every render, not just mount, so
        // Confetti::burst() always fires using the element's current props.
        // `let _ =` (not a bare statement) because `body` is @ViewBuilder —
        // an unassigned Void-returning call there gets swept into the
        // builder DSL and fails to typecheck as a View.
        let _ = ConfettiRegistry.shared.register(ref: ref) {
            fire(props: p)
        }

        GeometryReader { geometry in
            TimelineView(.animation(minimumInterval: 1.0 / 60, paused: isIdle)) { context in
                Canvas { canvasContext, _ in
                    let now = context.date

                    for particle in particles where particle.hasSpawned(at: now) && !particle.isDead(at: now) {
                        var resolved = canvasContext
                        resolved.opacity = particle.opacity(at: now)

                        // Squares are drawn as strips (wider than tall), not
                        // squares at rest — closer to real confetti paper
                        // than a perfect square even before it flutters.
                        let width = particle.shape == .square ? particle.size * 1.8 : particle.size
                        let height = particle.size
                        let rect = CGRect(
                            x: particle.x - width / 2,
                            y: particle.y - height / 2,
                            width: width,
                            height: height
                        )

                        resolved.translateBy(x: rect.midX, y: rect.midY)
                        resolved.rotate(by: .radians(particle.rotation))
                        resolved.scaleBy(x: particle.flutterScaleX(at: now), y: 1)
                        resolved.translateBy(x: -rect.midX, y: -rect.midY)

                        let path: Path = particle.shape == .circle
                            ? Path(ellipseIn: rect)
                            : Path(rect)

                        resolved.fill(path, with: .color(particle.color))
                    }
                }
                .onChange(of: context.date) { now in
                    step(deltaTime: 1.0 / 60, canvasSize: geometry.size, now: now)

                    if particles.isEmpty, !isIdle {
                        isIdle = true

                        if onFinishedCb != 0 {
                            NativeElementBridge.sendSheetDismissEvent(onFinishedCb, nodeId: node.id)
                        }
                    }
                }
            }
        }
        .allowsHitTesting(false)
        .onAppear {
            // Seeded to whatever the token already is at mount — NOT nil —
            // so an element whose initial token happens to be non-empty
            // (a PHP int property defaults to 0, which stringifies to "0")
            // does not fire on first composition. Only a later change does.
            lastFireToken = fireToken
        }
        .onChange(of: fireToken) { token in
            guard token != lastFireToken else { return }
            lastFireToken = token

            fire(props: p)
        }
        .onDisappear {
            // A burst() against a dismissed element must fail rather than
            // silently do nothing.
            ConfettiRegistry.shared.unregister(ref: ref)
        }
    }

    private func fire(props p: GenericProps) {
        let angle = Double(p.getInt("angle", default: 270))
        let spread = Double(p.getInt("spread", default: 90))
        let speed = CGFloat(p.getFloat("speed", default: 10))
        let maxSpeed = CGFloat(p.getFloat("max_speed", default: 30))
        let particleCount = max(0, p.getInt("particle_count", default: 80))
        let durationSeconds = Double(p.getInt("duration_ms", default: 300)) / 1000.0
        let timeToLive = Double(p.getInt("time_to_live_ms", default: 2500)) / 1000.0
        let fadeOut = p.getBool("fade_out", default: true)
        let positionX = CGFloat(p.getFloat("position_x", default: 0.5))
        let positionY = CGFloat(p.getFloat("position_y", default: 0.3))

        let colorStrings = p.getStringList("colors")
        let colors: [Color] = (colorStrings.isEmpty ? Self.defaultColors : colorStrings)
            .map { colorFromARGB(ColorParser.parse($0)) }

        let now = Date()

        isIdle = false

        particles = (0..<particleCount).map { _ in
            let particleAngle = (angle + Double.random(in: -spread / 2...spread / 2)) * .pi / 180
            let particleSpeed = CGFloat.random(in: speed...max(speed, maxSpeed)) * Self.speedToPointsPerSecond
            // Staggered across duration_ms rather than all at once, so a
            // "rain"/"festive" preset trickles the way Konfetti's Emitter
            // does on Android instead of dumping every particle at frame 0.
            let spawnDelay = durationSeconds > 0 ? Double.random(in: 0...durationSeconds) : 0

            return Particle(
                x: min(max(positionX + CGFloat.random(in: Self.originJitterX), 0), 1),
                y: min(max(positionY + CGFloat.random(in: Self.originJitterY), 0), 1),
                vx: cos(particleAngle) * particleSpeed,
                // Konfetti's angle convention is already measured clockwise
                // in SCREEN space (TOP = 270°, BOTTOM = 90°) — sin(270°) is
                // already -1, i.e. already "up" on a y-grows-down screen,
                // so this needs NO extra sign flip. An earlier version
                // negated it here, which fired every burst downward instead
                // of up and made gravity win almost immediately.
                vy: sin(particleAngle) * particleSpeed,
                rotation: Double.random(in: 0..<(2 * .pi)),
                rotationSpeed: Double.random(in: -6...6),
                flipSpeed: Double.random(in: 2...6),
                color: colors.randomElement() ?? .yellow,
                size: CGFloat.random(in: 7...13),
                shape: Bool.random() ? .square : .circle,
                spawnAt: now.addingTimeInterval(spawnDelay),
                timeToLive: timeToLive,
                fadeOut: fadeOut
            )
        }
    }

    private func step(deltaTime: CGFloat, canvasSize: CGSize, now: Date) {
        guard !particles.isEmpty else { return }

        let dampingFactor = CGFloat(pow(Double(dampingValue), Double(deltaTime * 60)))

        for index in particles.indices {
            if !particles[index].isOriginResolved {
                particles[index].x *= canvasSize.width
                particles[index].y *= canvasSize.height
                particles[index].isOriginResolved = true
            }

            guard particles[index].hasSpawned(at: now) else { continue }

            particles[index].vy += gravity * deltaTime
            particles[index].x += particles[index].vx * deltaTime
            particles[index].y += particles[index].vy * deltaTime
            particles[index].rotation += particles[index].rotationSpeed * deltaTime
            particles[index].vx *= dampingFactor
            particles[index].vy *= dampingFactor
        }

        particles.removeAll { $0.isDead(at: now) }
    }

    private var dampingValue: CGFloat {
        CGFloat(node.props.getFloat("damping", default: 0.9))
    }

    private static let defaultColors = ["#FCE18A", "#FF726D", "#F4306D", "#B48DEF"]
}
