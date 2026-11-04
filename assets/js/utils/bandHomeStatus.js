import { differenceInCalendarDays } from 'date-fns'
import { separator } from '../i18n/formatLocale.js'
import { i18n } from '../i18n/index.js'
import { toAgendaDate } from './agendaDate.js'
import { isAllDayItem } from './agendaItem.js'
import { formatAmount } from './currency.js'
import { formatDateCompact } from './date.js'
import { formatBytes } from './formatBytes.js'

/**
 * What the phone band home says about each module, one or two lines per tile.
 *
 * A port of the Android app's band home (`band_home_status.dart` in Cryde/musicall-app), so a band
 * reads the same on both phones: change a rule there, change it here. Pure, so unit tested (npm test).
 */

export const TONE = Object.freeze({ NEUTRAL: 'neutral', DANGER: 'danger' })

const MINUTE = 60 * 1000
const HOUR = 60 * MINUTE
const DAY = 24 * HOUR

// Translated when a line is built, never at import, so a language switch reaches the tiles.
function t(key, ...args) {
  return i18n.global.t(key, ...args)
}

function status(line1, line2 = null, tone = TONE.NEUTRAL) {
  return { line1, line2, tone }
}

function joined(...parts) {
  return parts.join(` ${separator()} `)
}

/** The day an agenda item is shown on: its written day when it covers one, its instant otherwise. */
export function shownDate(item) {
  return toAgendaDate(item?.datetime, isAllDayItem(item))
}

/** « 4 nov. » in French, "4 Nov" in English. */
export function shortDay(date) {
  return formatDateCompact(date)
}

/**
 * Whether an entered date is behind the band. A timed one is from the moment it starts, as in the
 * app; an all day one only once its day is over, where the app drops it at midnight UTC.
 */
function isPastDate(item, now) {
  const date = shownDate(item)
  if (date === null) return true
  return isAllDayItem(item) ? differenceInCalendarDays(date, now) < 0 : date < now
}

function byShownDate(a, b) {
  return shownDate(a) - shownDate(b)
}

/**
 * The dates somebody entered, still to come, soonest first. The agenda feed also carries task
 * deadlines, finance due dates and absences: none of them is a date for the band.
 */
export function upcomingDates(items, now = new Date()) {
  return items
    .filter((item) => item.source === 'manual' && !isPastDate(item, now))
    .sort(byShownDate)
}

/** The latest entered date already behind the band, or null. */
export function lastPastDate(items, now = new Date()) {
  const past = items
    .filter((item) => item.source === 'manual' && isPastDate(item, now))
    .sort(byShownDate)
  return past.at(-1) ?? null
}

export function agendaStatus(upcoming, lastPast = null) {
  const next = upcoming[0]
  if (!next) {
    return status(
      t('band_space.home.nothing_planned'),
      lastPast
        ? t('band_space.home.tiles.last_date', { date: shortDay(shownDate(lastPast)) })
        : null
    )
  }
  return status(
    t('band_space.home.tiles.upcoming_dates', upcoming.length),
    joined(shortDay(shownDate(next)), next.title)
  )
}

/**
 * The counters say how many; `tasks`, the live list, says whose. Without it the tile still says
 * what the counters say. « en retard » outranks everything, then the reader's own tasks.
 */
export function taskStatus(stats, tasks = null, userId = null) {
  const open = (tasks ?? []).filter((task) => task.status !== 'done')
  const mine = userId
    ? open.filter((task) => task.assignees?.some((a) => a.id === userId)).length
    : 0
  const unassigned = open.filter((task) => (task.assignees ?? []).length === 0).length
  const todo = stats?.todo ?? 0
  const inProgress = stats?.in_progress ?? 0
  const overdue = stats?.overdue ?? 0

  let line1
  let tone = TONE.NEUTRAL
  // « en cours » on the second line when « à faire » has the first: both on one line would be cut
  // short on a tile half a phone wide.
  let startedOnLine2 = false
  if (overdue > 0) {
    line1 = t('band_space.dashboard.tasks.overdue', { count: overdue })
    tone = TONE.DANGER
  } else if (mine > 0) {
    line1 = t('band_space.home.tiles.tasks_mine', { count: mine })
  } else if (todo > 0) {
    line1 = t('band_space.home.tiles.tasks_todo', { count: todo })
    startedOnLine2 = inProgress > 0
  } else if (inProgress > 0) {
    line1 = t('band_space.home.tiles.tasks_in_progress', { count: inProgress })
  } else {
    line1 = t('band_space.home.tiles.tasks_none')
  }

  let line2 = null
  if (startedOnLine2) {
    line2 = t('band_space.home.tiles.tasks_in_progress', { count: inProgress })
  } else if (unassigned > 0) {
    line2 = t('band_space.home.tiles.tasks_unassigned', { count: unassigned })
  } else if (mine > 0 && overdue > 0) {
    line2 = t('band_space.home.tiles.tasks_mine', { count: mine })
  }

  return status(line1, line2, tone)
}

/**
 * The most recently touched item, by `touchedAt`; the first of the list when none says when, since
 * the API sends them newest first.
 */
function latest(items, touchedAt) {
  let best = items[0]
  for (const item of items.slice(1)) {
    const at = touchedAt(item)
    const bestAt = touchedAt(best)
    if (at && (!bestAt || new Date(at) > new Date(bestAt))) best = item
  }
  return best
}

export function setlistStatus(setlists) {
  if (setlists.length === 0) return status(t('band_space.home.tiles.setlists_none'))

  const touched = latest(
    setlists,
    (setlist) => setlist.update_datetime ?? setlist.creation_datetime
  )
  const songs = (touched.items ?? []).filter((item) => item.type === 'song').length
  return status(
    t('band_space.home.tiles.setlists_count', setlists.length),
    joined(touched.name, t('band_space_setlist.sidebar.song_count', songs))
  )
}

export function techRiderStatus(riders, now = new Date()) {
  if (riders.length === 0) return status(t('band_space.home.tiles.riders_none'))

  const touched = latest(riders, (rider) => rider.update_datetime)
  return status(
    touched.name,
    touched.update_datetime
      ? t('band_space.home.tiles.rider_edited', {
          age: relativeAgeLabel(touched.update_datetime, now)
        })
      : null
  )
}

/** `quota` is optional: the count is the line the tile cannot do without. */
export function fileStatus(count, quota = null) {
  return status(
    t('band_space.home.tiles.files_count', count),
    quota && quota.quota_bytes > 0
      ? t('band_space.home.tiles.storage_used', {
          used: formatBytes(quota.used_bytes),
          total: formatBytes(quota.quota_bytes)
        })
      : null
  )
}

/**
 * This month's summary, every status counted, as the desktop widget has it: where the band will
 * stand. The amount alone on the first line, since a label before it would not fit half a phone.
 */
export function financeStatus(summary) {
  const income = summary?.total_income_all ?? 0
  const expense = summary?.total_expense_all ?? 0
  if (income === 0 && expense === 0) return status(t('band_space.home.tiles.finance_nothing'))

  const balance = income - expense
  return status(
    formatAmount(balance),
    t('band_space.home.tiles.finance_forecast'),
    balance < 0 ? TONE.DANGER : TONE.NEUTRAL
  )
}

export function noteStatus(notes, now = new Date()) {
  if (notes.length === 0) return status(t('band_space.home.tiles.notes_none'))

  const touched = notes
    .map((note) => note.update_datetime ?? note.creation_datetime)
    .filter(Boolean)
    .reduce((a, b) => (a === null || new Date(b) > new Date(a) ? b : a), null)
  return status(
    t('band_space.home.tiles.notes_count', notes.length),
    touched ? t('band_space.home.tiles.note_edited', { age: relativeAgeLabel(touched, now) }) : null
  )
}

/**
 * `invitations` is null for a member who is not admin: the API lists them to admins only, and only
 * an admin can invite, so the tile says nothing more.
 */
export function memberStatus(members, invitations = null) {
  const line1 = t('band_space.home.members', members.length)
  if (invitations === null) return status(line1)

  const pending = invitations.filter((invitation) => invitation.status === 'pending').length
  return status(
    line1,
    pending === 0
      ? t('band_space.home.tiles.invite_someone')
      : t('band_space.home.tiles.pending_invitations', pending)
  )
}

/** « 6 membres · 5 autres groupes », leaving out what is zero. Null when there is nothing to say. */
export function bandSubtitle(memberCount, otherBandCount) {
  const parts = []
  if (memberCount > 0) parts.push(t('band_space.home.members', memberCount))
  if (otherBandCount > 0) parts.push(t('band_space.home.other_bands', otherBandCount))
  return parts.length > 0 ? joined(...parts) : null
}

/** « Aujourd'hui », « Demain », « Dans 3 jours »: calendar days, so a clock change cannot skew it. */
export function whenLabel(date, now = new Date()) {
  const days = differenceInCalendarDays(date, now)
  if (days <= 0) return t('band_space.dashboard.agenda.today')
  if (days === 1) return t('band_space.dashboard.agenda.tomorrow')
  return t('band_space.home.next_date.in_days', days)
}

/**
 * How long ago, short enough for half a phone: « il y a 40 min », « il y a 5 h », « hier ». Elapsed
 * time rather than calendar days, as in the app; a date in the future reads as just now.
 */
export function relativeAgeLabel(value, now = new Date()) {
  const elapsed = now - new Date(value)
  if (elapsed < MINUTE) return t('band_space.home.age.just_now')
  if (elapsed < HOUR)
    return t('band_space.home.age.minutes', { count: Math.floor(elapsed / MINUTE) })
  if (elapsed < DAY) return t('band_space.home.age.hours', { count: Math.floor(elapsed / HOUR) })

  const days = Math.floor(elapsed / DAY)
  return days === 1 ? t('band_space.home.age.yesterday') : t('band_space.home.age.days', days)
}
