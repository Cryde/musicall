import { i18n } from '../i18n/index.js'
import { browserTimers } from './browserTimers.js'

/**
 * « X écrit… » (#1040), kept out of the stores and the composers so each rule is pinned by a test. The
 * band chat and a direct conversation (#1242) share all of it but the names.
 */

/** How often a member's browser says it is still typing, at most. */
export const TYPING_SIGNAL_INTERVAL_MS = 3000

/**
 * How long a typist is shown after their last signal. A little over the interval, so continuous typing
 * never blinks, and short, because nothing ever says « stopped »: a closed laptop sends nothing, and
 * an indicator waiting for a stop event would stick forever.
 */
export const TYPIST_TTL_MS = 5000

/**
 * Calls `send` at most once per interval however often `notify` is called, which is once per keystroke.
 * `reset` after a message goes, so typing the next one is announced at once. A `send` that returns
 * false did not go out, and does not start the interval.
 */
export function createTypingNotifier({
  send,
  intervalMs = TYPING_SIGNAL_INTERVAL_MS,
  now = () => Date.now()
}) {
  let lastSentAt = null

  return {
    notify() {
      const at = now()
      if (lastSentAt !== null && at - lastSentAt < intervalMs) return
      if (send() !== false) lastSentAt = at
    },
    reset() {
      lastSentAt = null
    }
  }
}

/**
 * Who is writing, in the order they started (#1040, #1242). Each typist fades on their own timer, since
 * nothing ever says « stopped », and goes at once when `forget` is told their message landed.
 * `onChange` hears the new list on every change.
 */
export function createTypistTracker({ onChange, ttlMs = TYPIST_TTL_MS, timers = browserTimers() }) {
  // Insertion ordered, so a typist who keeps typing keeps their place.
  const fades = new Map()

  function publish() {
    onChange([...fades.keys()])
  }

  return {
    seen(userId) {
      const isNew = !fades.has(userId)
      if (!isNew) timers.clear(fades.get(userId))
      fades.set(
        userId,
        timers.set(() => {
          fades.delete(userId)
          publish()
        }, ttlMs)
      )
      if (isNew) publish()
    },
    forget(userId) {
      if (!fades.has(userId)) return
      timers.clear(fades.get(userId))
      fades.delete(userId)
      publish()
    },
    clear() {
      if (fades.size === 0) return
      for (const fade of fades.values()) timers.clear(fade)
      fades.clear()
      publish()
    }
  }
}

/** How the chat names members, typists or online, from the band's roster; somebody not on it yet is « Un membre ». */
export function memberNames(userIds, members) {
  return userIds.map(
    (userId) =>
      members.find((member) => member.user_id === userId)?.display_name ??
      i18n.global.t('band_space_chat.typing.unknown_member')
  )
}

/** Named up to two, counted past that. */
export function typingSentence(names) {
  if (names.length === 0) return ''
  if (names.length === 1) return i18n.global.t('shared.typing.one', { name: names[0] })
  if (names.length === 2) {
    return i18n.global.t('shared.typing.two', { first: names[0], second: names[1] })
  }
  return i18n.global.t('shared.typing.many', { count: names.length })
}
