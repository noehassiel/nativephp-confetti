package com.noehassiel.plugins.confetti

/**
 * Maps a confetti element's `ref` to a closure that starts a burst using
 * that element's own current props.
 *
 * Bridge functions get an Activity, not a view, so `Confetti::burst()` has
 * no way to reach a specific rendered element without something like this.
 * Entries are added when an element mounts (or its `ref` changes) and
 * dropped when it leaves — a burst() against a dismissed element must fail
 * rather than silently do nothing.
 */
object ConfettiRegistry {

    private val triggers = mutableMapOf<String, () -> Unit>()

    @Synchronized
    fun register(ref: String, trigger: () -> Unit) {
        triggers[ref] = trigger
    }

    @Synchronized
    fun unregister(ref: String) {
        triggers.remove(ref)
    }

    /** Returns whether an element with that ref was found and triggered. */
    @Synchronized
    fun trigger(ref: String): Boolean {
        val trigger = triggers[ref] ?: return false
        trigger()

        return true
    }
}
