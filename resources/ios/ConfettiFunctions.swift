import Foundation

/**
 * Bridge entry point for Confetti::burst() — triggers a mounted element from
 * outside the screen that renders it.
 *
 * Async by dispatch, like the rest of the plugin surface: a failure to find
 * the element arrives as ConfettiBurstFailed so the PHP runtime never blocks
 * waiting on the main thread.
 */
enum ConfettiFunctions {

    class Burst: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            let refParam = parameters["ref"] as? String
            let ref = (refParam?.isEmpty ?? true) ? "default" : refParam!

            // Triggering mutates SwiftUI state, so it has to run on the
            // main thread; the bridge may well call us from a background
            // queue.
            DispatchQueue.main.async {
                if !ConfettiRegistry.shared.trigger(ref: ref) {
                    LaravelBridge.shared.send?(
                        "Noehassiel\\Confetti\\Events\\ConfettiBurstFailed",
                        [
                            "ref": ref,
                            "reason": "No confetti element with that ref is mounted.",
                        ]
                    )
                }
            }

            return BridgeResponse.success(data: ["dispatched": true])
        }
    }
}
