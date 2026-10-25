import { onMounted, onUnmounted, watch } from 'vue'
import { createTargetedPresence } from '../utils/presenceSession.js'

const PRESENCE_INTERVAL_MS = 30000
const PRESENCE_HIDDEN_GRACE_MS = 15000

/**
 * « En ligne » in whichever conversation `target()` names (#1040, #1246): only while this page is
 * open and on screen. A tab hidden for longer than the grace says it has gone, and beats again, at
 * once, when it is shown. `null` means there is nothing to be present in.
 *
 * @param {object} options
 * @param {() => string | null} options.target
 * @param {(target: string) => unknown} options.beat
 * @param {(target: string) => unknown} options.leave
 */
export function useChatPresence({ target, beat, leave }) {
  const presence = createTargetedPresence({
    beat,
    leave,
    intervalMs: PRESENCE_INTERVAL_MS,
    graceMs: PRESENCE_HIDDEN_GRACE_MS
  })

  const isVisible = () => document.visibilityState === 'visible'

  function syncWithVisibility() {
    if (isVisible()) presence.shown()
    else presence.hidden()
  }

  // A page restored from the browser's back-forward cache fires no visibilitychange, only pageshow.
  function resumeOnPageShow(event) {
    if (event.persisted) syncWithVisibility()
  }

  watch(target, (next) => presence.target(next, isVisible()))

  onMounted(() => {
    presence.target(target(), isVisible())
    document.addEventListener('visibilitychange', syncWithVisibility)
    window.addEventListener('pagehide', presence.leaveNow)
    window.addEventListener('pageshow', resumeOnPageShow)
  })

  onUnmounted(() => {
    document.removeEventListener('visibilitychange', syncWithVisibility)
    window.removeEventListener('pagehide', presence.leaveNow)
    window.removeEventListener('pageshow', resumeOnPageShow)
    presence.leaveNow()
  })
}
