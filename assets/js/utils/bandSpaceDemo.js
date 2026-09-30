/**
 * The pretend Band Space a visitor configures on /band-space. Nothing here is saved: the visitor
 * plays with it, then creates the real one. Everything is keyed by member index rather than name so
 * that changing the band size keeps every row pointing at someone who is still in the band.
 */

export const DEMO_SIZES = Object.freeze([2, 3, 4, 5, 6])

// How many fake dates or expenses a visitor can add: enough to see the list react, not a real tool.
export const DEMO_ADD_LIMIT = 2

const MEMBERS = ['Vous', 'Léa', 'Tom', 'Sam', 'Nico', 'Alex']

// Keys match BAND_SPACE_MODULES, so a picked pain is the module it lights up.
export const DEMO_PAINS = Object.freeze([
  { key: 'agenda', label: 'Trouver une date' },
  { key: 'taches', label: 'Qui fait quoi' },
  { key: 'setlists', label: 'Les setlists' },
  { key: 'finances', label: "L'argent" },
  { key: 'files', label: 'Les fichiers' }
])

const DASHBOARD_SLOTS = 4

const BASE_EVENTS = [
  {
    id: 'rehearsal-1',
    day: 'JEU',
    date: 1,
    name: 'Répétition',
    detail: '20:00 à 23:00 · Le Local 13',
    isGig: false
  },
  {
    id: 'gig',
    day: 'SAM',
    date: 10,
    name: 'Concert',
    detail: '21:30 · Le Hangar · setlist associée',
    isGig: true
  },
  {
    id: 'rehearsal-2',
    day: 'JEU',
    date: 15,
    name: 'Répétition',
    detail: '20:00 à 23:00 · Le Local 13',
    isGig: false
  }
]

const EXTRA_EVENTS = [
  {
    id: 'extra-rehearsal',
    day: 'MAR',
    date: 6,
    name: 'Filage avant le concert',
    detail: '19:30 à 22:30 · Le Local 13',
    isGig: false
  },
  {
    id: 'extra-studio',
    day: 'SAM',
    date: 24,
    name: 'Enregistrement démo',
    detail: '14:00 · Studio Onde',
    isGig: false
  }
]

// A negative assignee counts from the end of the band, so « the last one » stays the last one.
const DEMO_TASKS = [
  {
    id: 'van',
    label: 'Réserver le camion',
    detail: 'Avant le 7 oct. · priorité haute',
    assignee: 1
  },
  { id: 'rider', label: 'Envoyer la fiche technique', detail: 'Avant le 3 oct.', assignee: 0 },
  { id: 'strings', label: 'Racheter des cordes', detail: 'Matériel', assignee: -1 },
  { id: 'rent', label: 'Payer le local', detail: 'Finances · 120 €', assignee: 2 }
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
  { id: 'rider', name: 'fiche-technique.pdf', folder: 'Concert', type: 'PDF', addedBy: 0 },
  { id: 'demo', name: 'demo-last-train-home.mp3', folder: 'Démos', type: 'MP3', addedBy: 1 },
  {
    id: 'bass',
    name: 'partition-basse-asphalt.pdf',
    folder: 'Partitions',
    type: 'PDF',
    addedBy: -1
  },
  { id: 'press', name: 'photo-presse.jpg', folder: 'Promo', type: 'JPG', addedBy: 2 }
]

const DEMO_NOTES = [
  {
    id: 'rehearsal',
    title: 'Répète du 24 sept.',
    body: "Ralentir le pont d'« Asphalt ».\nTester l'intro de « Neon Lights » à deux guitares.\nGarder « Shockwave » pour le rappel.",
    author: 0
  },
  {
    id: 'covers',
    title: 'Idées de reprises',
    body: 'Trois propositions à voter avant jeudi.',
    author: -1
  }
]

const BASE_EXPENSES = [{ id: 'van', label: 'Location du camion', amount: 180, paidBy: 1 }]

const EXTRA_EXPENSES = [
  { id: 'room', label: 'Local de répète', amount: 120, paidBy: 0 },
  { id: 'strings', label: 'Cordes et baguettes', amount: 45, paidBy: 2 }
]

export function demoRoster(size) {
  return MEMBERS.slice(0, size)
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
  return trimmed ? `Créer « ${trimmed} »` : 'Créer mon Band Space'
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
    .map((event) => ({
      ...event,
      date: String(event.date).padStart(2, '0'),
      available: event.isGig ? size : Math.max(1, size - 1)
    }))
}

export function demoTasks(size, doneIds) {
  return DEMO_TASKS.map((task) => ({
    ...task,
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
  return DEMO_NOTES.map((note) => {
    const isEdited = note.id in editedBodies
    return {
      ...note,
      body: isEdited ? editedBodies[note.id] : note.body,
      who: isEdited ? memberAt(size, 0) : memberAt(size, note.author),
      isEdited
    }
  })
}

export function demoFiles(size, deletedIds) {
  return DEMO_FILES.filter((file) => !deletedIds.includes(file.id)).map((file) => ({
    ...file,
    who: memberAt(size, file.addedBy)
  }))
}

export function demoExpenses(size, addedCount) {
  return [...BASE_EXPENSES, ...EXTRA_EXPENSES.slice(0, addedCount)].map((expense) => ({
    ...expense,
    payer: memberAt(size, expense.paidBy)
  }))
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

export function formatEuros(amount) {
  const text = Number.isInteger(amount) ? String(amount) : amount.toFixed(2).replace('.', ',')
  return `${text} €`
}

export function describeBalance(cents) {
  if (cents === 0) {
    return "à l'équilibre"
  }
  return `${cents > 0 ? 'reçoit' : 'doit'} ${formatEuros(Math.abs(cents) / 100)}`
}

// One colour per module, used across the preview.
export const MODULE_ACCENTS = Object.freeze({
  agenda: { text: 'text-primary', dot: 'bg-primary' },
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
