package com.noehassiel.plugins.confetti

import androidx.fragment.app.FragmentActivity
import com.nativephp.mobile.bridge.BridgeFunction
import com.nativephp.mobile.bridge.BridgeResponse
import com.nativephp.mobile.utils.NativeActionCoordinator
import org.json.JSONObject

/**
 * Bridge entry point for Confetti::burst() — triggers a mounted element from
 * outside the screen that renders it.
 *
 * Async by dispatch, like the rest of the plugin surface: a failure to find
 * the element arrives as ConfettiBurstFailed, so a caller never blocks the
 * PHP runtime waiting on the UI thread.
 */
object ConfettiFunctions {

    class Burst(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val refParam = parameters["ref"] as? String
            val ref = if (refParam.isNullOrEmpty()) "default" else refParam

            // Triggering mutates Compose state, so it has to run on the main
            // thread; the bridge may well call us from a worker.
            activity.runOnUiThread {
                if (! ConfettiRegistry.trigger(ref)) {
                    val payload = JSONObject().apply {
                        put("ref", ref)
                        put("reason", "No confetti element with that ref is mounted.")
                    }

                    NativeActionCoordinator.dispatchEvent(
                        activity,
                        "Noehassiel\\Confetti\\Events\\ConfettiBurstFailed",
                        payload.toString(),
                    )
                }
            }

            return BridgeResponse.success(mapOf("dispatched" to true))
        }
    }
}
