/**
 * « X écrit… » (#1040), kept out of the store and the composer so each rule is pinned by a test.
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

/** The typists with this one seen now: a plain object from user id to when they stop showing. */
export function withTypist(typists, userId, now, ttlMs = TYPIST_TTL_MS) {
  return { ...typists, [userId]: now + ttlMs }
}

export function withoutTypist(typists, userId) {
  const { [userId]: _gone, ...rest } = typists
  return rest
}

/** Who is still typing, in the order they started. */
export function activeTypists(typists, now) {
  return Object.entries(typists)
    .filter(([, until]) => until > now)
    .map(([userId]) => userId)
}

/** How the chat names members, typists or online, from the band's roster; somebody not on it yet is « Un membre ». */
export function memberNames(userIds, members) {
  return userIds.map(
    (userId) => members.find((member) => member.user_id === userId)?.display_name ?? 'Un membre'
  )
}

/** Named up to two, counted past that. */
export function typingSentence(names) {
  if (names.length === 0) return ''
  if (names.length === 1) return `${names[0]} écrit…`
  if (names.length === 2) return `${names[0]} et ${names[1]} écrivent…`
  return `${names.length} personnes écrivent…`
}
