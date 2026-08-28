import Foundation

/**
 * Maps a confetti element's `ref` to a closure that starts a burst using
 * that element's own current props.
 *
 * Bridge functions have no handle on a rendered SwiftUI view, so a bridge
 * call needs an indirection like this to reach a specific element.
 * Registered whenever the element commits a render (see
 * `ConfettiRenderer.body`) and dropped on `onDisappear` — a burst() against
 * a dismissed element must fail rather than silently do nothing.
 */
final class ConfettiRegistry {
    static let shared = ConfettiRegistry()

    private var triggers: [String: () -> Void] = [:]
    private let lock = NSLock()

    private init() {}

    func register(ref: String, trigger: @escaping () -> Void) {
        lock.lock(); defer { lock.unlock() }
        triggers[ref] = trigger
    }

    func unregister(ref: String) {
        lock.lock(); defer { lock.unlock() }
        triggers.removeValue(forKey: ref)
    }

    /// Returns whether an element with that ref was found and triggered.
    @discardableResult
    func trigger(ref: String) -> Bool {
        lock.lock()
        let trigger = triggers[ref]
        lock.unlock()

        guard let trigger else { return false }
        trigger()

        return true
    }
}
