/**
 * The arithmetic of a setlist's running order (#1061): what each row shows as its number, its
 * duration and the running total, and the summary over the whole set. Kept out of the components so
 * each rule is pinned by a test.
 */

export const INTERMISSION_TYPES = ['break', 'talk', 'interlude']

/** An item's own duration if set, else its song's, else null: nobody knows yet. */
export function itemDuration(item) {
  return item.duration_override ?? item.song?.reference_duration ?? null
}

/**
 * One row per item: songs are numbered 1, 2, 3 on their own, intermèdes are not. The running total
 * becomes uncertain from the first song with no duration on, which the cell shows as « +? »: an
 * intermède with no duration is a gap nobody measured, a missing song is a real hole in the sum.
 */
export function programmeRows(items) {
  let songNumber = 0
  let cumulative = 0
  let uncertain = false

  return items.map((item) => {
    const isSong = item.type === 'song'
    const duration = itemDuration(item)
    if (isSong) {
      songNumber += 1
      if (duration === null) uncertain = true
    }
    cumulative += duration ?? 0

    return {
      item,
      isSong,
      number: isSong ? songNumber : null,
      duration,
      missingDuration: isSong && duration === null,
      cumulative,
      uncertain
    }
  })
}

export function programmeSummary(items) {
  const songs = items.filter((item) => item.type === 'song')

  return {
    songs: songs.length,
    intermissions: items.length - songs.length,
    total: items.reduce((sum, item) => sum + (itemDuration(item) ?? 0), 0),
    missingDurations: songs.filter((item) => itemDuration(item) === null).length
  }
}

/** How far the set is into its target: `ratio` is capped at 1 for the bar, `remaining` goes negative. */
export function targetProgress(total, target) {
  if (!target) return null

  return {
    ratio: Math.min(1, total / target),
    remaining: target - total,
    isOver: total > target
  }
}

// ---------------------------------------------------------------------------------------------
// What a row shows, chosen by the viewer and remembered per browser.

export const COLUMNS = [
  { key: 'tonality', label: 'Tonalité' },
  { key: 'tempo', label: 'BPM' },
  { key: 'duration', label: 'Durée' },
  { key: 'cumulative', label: 'Cumul' },
  { key: 'details', label: 'Transitions et notes' }
]

export const DEFAULT_COLUMNS = {
  tonality: true,
  tempo: false,
  duration: true,
  cumulative: true,
  details: true
}

const COLUMNS_STORAGE_KEY = 'musicall.setlist.columns'

/** The stored choice over the defaults, so a column added later shows up with its default. */
export function readColumns(storage) {
  try {
    const stored = JSON.parse(storage?.getItem(COLUMNS_STORAGE_KEY) ?? 'null')
    if (stored && typeof stored === 'object') {
      const columns = { ...DEFAULT_COLUMNS }
      for (const { key } of COLUMNS) {
        if (typeof stored[key] === 'boolean') columns[key] = stored[key]
      }
      return columns
    }
  } catch {
    // Blocked or corrupt storage: the defaults are a fine answer.
  }
  return { ...DEFAULT_COLUMNS }
}

export function writeColumns(storage, columns) {
  try {
    storage?.setItem(COLUMNS_STORAGE_KEY, JSON.stringify(columns))
  } catch {
    // Not remembered, still applied for this visit.
  }
}
