import { forActiveLocale } from '../i18n/formatLocale.js'
import { i18n } from '../i18n/index.js'
import { formatEurosFromCentsTrimmed } from './currency.js'

/**
 * The pretend Band Space a visitor configures on /band-space. Nothing here is saved: the visitor
 * plays with it, then creates the real one. Everything is keyed by member index rather than name so
 * that changing the band size keeps every row pointing at someone who is still in the band.
 */

export const DEMO_SIZES = Object.freeze([2, 3, 4, 5, 6])

// How many fake dates or expenses a visitor can add: enough to see the list react, not a real tool.
export const DEMO_ADD_LIMIT = 2

// The visitor comes first, under a translated « Toi »; the others are first names.
const BAND_MATES = ['Léa', 'Tom', 'Sam', 'Nico', 'Alex']

const t = (key, params) => i18n.global.t(key, params)

// Keys match BAND_SPACE_MODULES, so a picked pain is the module it lights up.
export const DEMO_PAINS = Object.freeze([
  {
    key: 'agenda',
    get label() {
      return t('home.band_space_presentation.pains.agenda')
    }
  },
  {
    key: 'chat',
    get label() {
      return t('home.band_space_presentation.pains.chat')
    }
  },
  {
    key: 'taches',
    get label() {
      return t('home.band_space_presentation.pains.tasks')
    }
  },
  {
    key: 'setlists',
    get label() {
      return t('home.band_space_presentation.pains.setlists')
    }
  },
  {
    key: 'finances',
    get label() {
      return t('home.band_space_presentation.pains.finances')
    }
  },
  {
    key: 'files',
    get label() {
      return t('home.band_space_presentation.pains.files')
    }
  }
])

const DASHBOARD_SLOTS = 4

const BASE_EVENTS = [
  {
    id: 'rehearsal-1',
    dayKey: 'home.band_space_presentation.demo.days.thu',
    date: 1,
    nameKey: 'home.band_space_presentation.demo.events.rehearsal',
    detailKey: 'home.band_space_presentation.demo.events.rehearsal_detail',
    isGig: false
  },
  {
    id: 'gig',
    dayKey: 'home.band_space_presentation.demo.days.sat',
    date: 10,
    nameKey: 'home.band_space_presentation.demo.events.gig',
    detailKey: 'home.band_space_presentation.demo.events.gig_detail',
    isGig: true
  },
  {
    id: 'rehearsal-2',
    dayKey: 'home.band_space_presentation.demo.days.thu',
    date: 15,
    nameKey: 'home.band_space_presentation.demo.events.rehearsal',
    detailKey: 'home.band_space_presentation.demo.events.rehearsal_detail',
    isGig: false
  }
]

const EXTRA_EVENTS = [
  {
    id: 'extra-rehearsal',
    dayKey: 'home.band_space_presentation.demo.days.tue',
    date: 6,
    nameKey: 'home.band_space_presentation.demo.events.run_through',
    detailKey: 'home.band_space_presentation.demo.events.run_through_detail',
    isGig: false
  },
  {
    id: 'extra-studio',
    dayKey: 'home.band_space_presentation.demo.days.sat',
    date: 24,
    nameKey: 'home.band_space_presentation.demo.events.recording',
    detailKey: 'home.band_space_presentation.demo.events.recording_detail',
    isGig: false
  }
]

// A negative assignee counts from the end of the band, so « the last one » stays the last one.
const DEMO_TASKS = [
  {
    id: 'van',
    labelKey: 'home.band_space_presentation.demo.tasks.van',
    detailKey: 'home.band_space_presentation.demo.tasks.van_detail',
    assignee: 1
  },
  {
    id: 'rider',
    labelKey: 'home.band_space_presentation.demo.tasks.rider',
    detailKey: 'home.band_space_presentation.demo.tasks.rider_detail',
    assignee: 0
  },
  {
    id: 'strings',
    labelKey: 'home.band_space_presentation.demo.tasks.strings',
    detailKey: 'home.band_space_presentation.demo.tasks.strings_detail',
    assignee: -1
  },
  {
    id: 'rent',
    labelKey: 'home.band_space_presentation.demo.tasks.rent',
    detailKey: 'home.band_space_presentation.demo.tasks.rent_detail',
    assignee: 2
  }
]

export const INITIAL_DONE_TASK_IDS = Object.freeze(['rent'])

const DEMO_SONGS = [
  { id: 'midnight', title: 'Concrete Midnight', seconds: 214 },
  { id: 'asphalt', title: 'Asphalt', seconds: 262 },
  { id: 'train', title: 'Last Train Home', seconds: 305, isNew: true },
  { id: 'neon', title: 'Neon Lights', seconds: 238 },
  { id: 'shockwave', title: 'Shockwave', seconds: 418 }
]

export const INITIAL_SETLIST_ORDER = Object.freeze(DEMO_SONGS.map((song) => song.id))

const DEMO_FILES = [
  {
    id: 'rider',
    nameKey: 'home.band_space_presentation.demo.files.rider',
    folderKey: 'home.band_space_presentation.demo.folders.gig',
    type: 'PDF',
    addedBy: 0
  },
  {
    id: 'demo',
    nameKey: 'home.band_space_presentation.demo.files.demo',
    folderKey: 'home.band_space_presentation.demo.folders.demos',
    type: 'MP3',
    addedBy: 1
  },
  {
    id: 'bass',
    nameKey: 'home.band_space_presentation.demo.files.bass',
    folderKey: 'home.band_space_presentation.demo.folders.scores',
    type: 'PDF',
    addedBy: -1
  },
  {
    id: 'press',
    nameKey: 'home.band_space_presentation.demo.files.press',
    folderKey: 'home.band_space_presentation.demo.folders.promo',
    type: 'JPG',
    addedBy: 2
  }
]

const DEMO_NOTES = [
  {
    id: 'rehearsal',
    titleKey: 'home.band_space_presentation.demo.notes.rehearsal_title',
    bodyKey: 'home.band_space_presentation.demo.notes.rehearsal_body',
    author: 0
  },
  {
    id: 'covers',
    titleKey: 'home.band_space_presentation.demo.notes.covers_title',
    bodyKey: 'home.band_space_presentation.demo.notes.covers_body',
    author: -1
  }
]

// Each attachment opens the tab it names, the way a chip in the real chat opens the item.
const DEMO_MESSAGES = [
  {
    id: 'reschedule',
    bodyKey: 'home.band_space_presentation.demo.chat.reschedule',
    author: 1,
    attachment: { module: 'agenda', labelKey: 'home.band_space_presentation.demo.events.rehearsal' }
  },
  {
    id: 'amp',
    bodyKey: 'home.band_space_presentation.demo.chat.amp',
    author: 2,
    attachment: null
  },
  {
    id: 'demo',
    bodyKey: 'home.band_space_presentation.demo.chat.demo',
    author: -1,
    attachment: { module: 'files', labelKey: 'home.band_space_presentation.demo.files.demo' }
  }
]

const BASE_EXPENSES = [
  {
    id: 'van',
    labelKey: 'home.band_space_presentation.demo.expenses.van',
    amount: 180,
    paidBy: 1
  }
]

const EXTRA_EXPENSES = [
  {
    id: 'room',
    labelKey: 'home.band_space_presentation.demo.expenses.room',
    amount: 120,
    paidBy: 0
  },
  {
    id: 'strings',
    labelKey: 'home.band_space_presentation.demo.expenses.strings',
    amount: 45,
    paidBy: 2
  }
]

export function demoRoster(size) {
  return [t('home.band_space_presentation.demo.you'), ...BAND_MATES].slice(0, size)
}

export function memberAt(size, index) {
  const roster = demoRoster(size)
  return roster[index < 0 ? roster.length + index : Math.min(index, roster.length - 1)]
}

export function sizeLabel(size) {
  return size === DEMO_SIZES.at(-1) ? `${size}+` : String(size)
}

export function ctaLabel(name) {
  const trimmed = name.trim()
  return trimmed
    ? t('home.band_space_presentation.cta.named', { name: trimmed })
    : t('home.band_space_presentation.cta.default')
}

export function canAddMore(addedCount) {
  return addedCount < DEMO_ADD_LIMIT
}

/** The picked modules first, then the others, as many as the dashboard has room for. */
export function dashboardModules(pickedKeys) {
  const keys = DEMO_PAINS.map((pain) => pain.key)
  const picked = keys.filter((key) => pickedKeys.includes(key))
  const others = keys.filter((key) => !pickedKeys.includes(key))
  return [...picked, ...others].slice(0, DASHBOARD_SLOTS)
}

/** Someone always misses a rehearsal, nobody misses the gig. */
export function demoAgenda(size, addedCount) {
  return [...BASE_EVENTS, ...EXTRA_EVENTS.slice(0, addedCount)]
    .sort((a, b) => a.date - b.date)
    .map(({ dayKey, nameKey, detailKey, ...event }) => ({
      ...event,
      day: t(dayKey),
      name: t(nameKey),
      detail: t(detailKey),
      date: String(event.date).padStart(2, '0'),
      available: event.isGig ? size : Math.max(1, size - 1)
    }))
}

export function demoTasks(size, doneIds) {
  return DEMO_TASKS.map(({ labelKey, detailKey, ...task }) => ({
    ...task,
    label: t(labelKey),
    detail: t(detailKey),
    who: memberAt(size, task.assignee),
    done: doneIds.includes(task.id)
  }))
}

export function demoSetlist(order) {
  return order.map((id) => DEMO_SONGS.find((song) => song.id === id))
}

/** Swaps the song at `index` with its neighbour; a move off either end leaves the order as it is. */
export function moveSong(order, index, step) {
  const target = index + step
  if (target < 0 || target >= order.length) {
    return [...order]
  }
  const moved = [...order]
  ;[moved[index], moved[target]] = [moved[target], moved[index]]
  return moved
}

export function formatSongDuration(seconds) {
  return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`
}

export function setlistMinutes(songs) {
  return Math.round(songs.reduce((total, song) => total + song.seconds, 0) / 60)
}

/** An edited note is signed by the visitor, since they are the one who just typed in it. */
export function demoNotes(size, editedBodies) {
  return DEMO_NOTES.map(({ titleKey, bodyKey, ...note }) => {
    const isEdited = note.id in editedBodies
    return {
      ...note,
      title: t(titleKey),
      body: isEdited ? editedBodies[note.id] : t(bodyKey),
      who: isEdited ? memberAt(size, 0) : memberAt(size, note.author),
      isEdited
    }
  })
}

/** The band's messages, then whatever the visitor sent, signed by them. */
export function demoChat(size, sentBodies) {
  const received = DEMO_MESSAGES.map(({ bodyKey, attachment, ...message }) => ({
    ...message,
    body: t(bodyKey),
    who: memberAt(size, message.author),
    isMine: false,
    attachment: attachment && { module: attachment.module, label: t(attachment.labelKey) }
  }))
  const sent = sentBodies.map((body, index) => ({
    id: `sent-${index}`,
    body,
    who: memberAt(size, 0),
    isMine: true,
    attachment: null
  }))
  return [...received, ...sent]
}

export function demoFiles(size, deletedIds) {
  return DEMO_FILES.filter((file) => !deletedIds.includes(file.id)).map(
    ({ nameKey, folderKey, ...file }) => ({
      ...file,
      name: t(nameKey),
      folder: t(folderKey),
      who: memberAt(size, file.addedBy)
    })
  )
}

export function demoExpenses(size, addedCount) {
  return [...BASE_EXPENSES, ...EXTRA_EXPENSES.slice(0, addedCount)].map(
    ({ labelKey, ...expense }) => ({
      ...expense,
      label: t(labelKey),
      payer: memberAt(size, expense.paidBy)
    })
  )
}

/** What each member is owed (positive) or owes (negative), in cents, with every expense split evenly. */
export function demoBalances(size, expenses) {
  const roster = demoRoster(size)
  return roster.map((name, index) => {
    const cents = expenses.reduce((balance, expense) => {
      const paid = memberAt(size, expense.paidBy) === roster[index] ? expense.amount * 100 : 0
      return balance + paid - (expense.amount * 100) / size
    }, 0)
    return { name, cents: Math.round(cents) }
  })
}

// The decimals only when there are some. French keeps the demo's own writing, without grouping;
// another language takes Intl's.
function frenchEuros(amount) {
  const text = Number.isInteger(amount) ? String(amount) : amount.toFixed(2).replace('.', ',')
  return `${text} €`
}

const EURO_WRITERS = {
  fr: frenchEuros,
  en: (amount) => formatEurosFromCentsTrimmed(Math.round(amount * 100))
}

export function formatEuros(amount) {
  return forActiveLocale(EURO_WRITERS)(amount)
}

export function describeBalance(cents) {
  if (cents === 0) {
    return t('home.band_space_presentation.demo.balance.even')
  }
  const amount = formatEuros(Math.abs(cents) / 100)
  return cents > 0
    ? t('home.band_space_presentation.demo.balance.receives', { amount })
    : t('home.band_space_presentation.demo.balance.owes', { amount })
}

// One colour per module, used across the preview.
export const MODULE_ACCENTS = Object.freeze({
  agenda: { text: 'text-primary', dot: 'bg-primary' },
  chat: { text: 'text-indigo-700 dark:text-indigo-300', dot: 'bg-indigo-500' },
  taches: { text: 'text-teal-700 dark:text-teal-300', dot: 'bg-teal-500' },
  notes: { text: 'text-pink-700 dark:text-pink-300', dot: 'bg-pink-500' },
  setlists: { text: 'text-fuchsia-700 dark:text-fuchsia-300', dot: 'bg-fuchsia-500' },
  files: { text: 'text-sky-700 dark:text-sky-300', dot: 'bg-sky-500' },
  finances: { text: 'text-amber-700 dark:text-amber-300', dot: 'bg-amber-500' }
})

/** The tab a footer link asked for (`?module=finances`), or null when it names no tab. */
export function requestedDemoTab(tabs, requested) {
  return tabs.some((tab) => tab.key === requested) ? requested : null
}
