/**
 * The one way a duration is written and read in the setlist module.
 *
 * Seconds stay the stored unit: the API takes and returns an integer number of seconds, and nothing
 * here changes that. What changes is that a human never has to do the arithmetic. Seeding a 40 song
 * repertoire, the module's very first task, used to mean converting every "3:47" into 227 by hand,
 * while every screen downstream already displayed minutes and seconds.
 *
 * Display used three formats for the same number: HH:MM:SS in the editor header, "97 min 30 s" in
 * the PDF header, which never rolled over to hours, and minutes with primes in the PDF total row.
 * There is now one, and it is deliberately the same notation the inputs accept, so a duration can
 * be read off a card and typed straight back into a field.
 *
 * Pure on purpose, so the rules are tested without a component. The PHP side of the same format
 * lives in src/Twig/DurationExtension.php for the PDF templates: the two must stay in step.
 *
 * See setlistDuration.test.js.
 */

/** The API's own range for a duration (Assert\Range on SongCreate and SetlistItemCreate). */
export const MIN_DURATION_SECONDS = 1
export const MAX_DURATION_SECONDS = 86400

export const DURATION_FORMAT_MESSAGE = 'Durée invalide. Utilisez le format mm:ss, par exemple 3:47.'
export const DURATION_RANGE_MESSAGE =
  'La durée doit être comprise entre 1 seconde et 24 heures (24:00:00).'

/**
 * Minutes are uncapped so a long break can be written 90:00, seconds are a real clock reading.
 * A single digit is allowed, 3:7 is unambiguous, but 3:75 is not a time at all and is refused
 * rather than quietly read as 4:15: a field whose point is that you can read it back must not
 * reinterpret what you typed.
 */
const MINUTES_SECONDS_PATTERN = /^(\d+):([0-5]?\d)$/

/**
 * Whatever formatDuration writes, this reads back, so a total displayed as 1:37:30 can be typed
 * into a field. Both trailing fields are two digits here, which is what keeps 3:7:9 out: a third
 * field is only a duration when it is written as a clock.
 */
const HOURS_MINUTES_SECONDS_PATTERN = /^(\d+):([0-5]\d):([0-5]\d)$/

/** A bare number is still read as seconds, which is what the field used to take. */
const BARE_SECONDS_PATTERN = /^\d+$/

const SECONDS_PER_MINUTE = 60
const SECONDS_PER_HOUR = 3600

function pad(value) {
  return String(value).padStart(2, '0')
}

/**
 * A duration for reading: `3:47`, `37:30`, `1:37:30`. Hours appear only once there are any, which
 * is the whole point of going through here instead of hardcoding HH:MM:SS or minutes only.
 *
 * An absent duration formats as an empty string, never as `0:00`, so a call site substitutes its own
 * placeholder when the string comes back empty. A real zero, the total of an empty setlist, does
 * format as `0:00`.
 *
 * That split only holds for a total. A per-item duration of exactly zero would render `0:00` where
 * the old per-component formatters rendered a placeholder, and it would seed an edit field with
 * `0:00` that then refuses to save, since zero is below MIN_DURATION_SECONDS. It never surfaces
 * because `Assert\Range(min: 1)` on SongCreate, SongResource, SetlistItemCreate and
 * SetlistItemResource stops a zero being persisted in the first place. Relax any of those bounds and
 * the per-item call sites have to start distinguishing zero from absent themselves.
 *
 * @param {number|null|undefined} seconds
 * @returns {string}
 */
export function formatDuration(seconds) {
  if (seconds === null || seconds === undefined || seconds === '') return ''

  const total = Math.round(Number(seconds))
  if (!Number.isFinite(total) || total < 0) return ''

  const hours = Math.floor(total / SECONDS_PER_HOUR)
  const minutes = Math.floor((total % SECONDS_PER_HOUR) / SECONDS_PER_MINUTE)
  const remainingSeconds = total % SECONDS_PER_MINUTE

  return hours > 0
    ? `${hours}:${pad(minutes)}:${pad(remainingSeconds)}`
    : `${minutes}:${pad(remainingSeconds)}`
}

/**
 * Reads what a member typed into a duration field.
 *
 * An empty field is not an error: it is how a duration is cleared, and how an optional one is left
 * unset, so it gives back null seconds and no message. Anything unreadable gives back null seconds
 * and the message to show, so a caller can refuse to submit without inventing a value.
 *
 * @param {string|number|null|undefined} value
 * @returns {{seconds: number|null, error: string|null}}
 */
export function parseDurationInput(value) {
  const raw = String(value ?? '').trim()
  if (raw === '') return { seconds: null, error: null }

  const longClock = raw.match(HOURS_MINUTES_SECONDS_PATTERN)
  if (longClock) {
    return withinRange(
      Number(longClock[1]) * SECONDS_PER_HOUR +
        Number(longClock[2]) * SECONDS_PER_MINUTE +
        Number(longClock[3])
    )
  }

  const clock = raw.match(MINUTES_SECONDS_PATTERN)
  if (clock) {
    return withinRange(Number(clock[1]) * SECONDS_PER_MINUTE + Number(clock[2]))
  }
  if (BARE_SECONDS_PATTERN.test(raw)) {
    return withinRange(Number(raw))
  }

  return { seconds: null, error: DURATION_FORMAT_MESSAGE }
}

/** Refuses what the API would refuse anyway, so a zero never travels as a duration. */
function withinRange(seconds) {
  if (seconds < MIN_DURATION_SECONDS || seconds > MAX_DURATION_SECONDS) {
    return { seconds: null, error: DURATION_RANGE_MESSAGE }
  }

  return { seconds, error: null }
}
