/**
 * Everything the frontend knows about the resources a band space file can be attached to.
 *
 * The backend allowlist is `BandSpaceFileSourceTypes::ALL` and its French wording lives in
 * `BandSpaceFileAttachmentLabels`; both delete paths refuse to trash a file attached to any of them.
 * The screens reading `source_type` each used to carry their own three-of-five copy of that list, so
 * a file attached to a song or a setlist read as a generic « Ressource », had no way back to its
 * source, and its bytes were missing from the quota breakdown. One row per source type here is what
 * keeps the file drawer, the file list, the folder tree, the activity feed and the quota breakdown
 * saying the same thing.
 *
 * Ordered like `BandSpaceFileSourceTypes::ALL`, so the two sides can be read side by side. A sixth
 * source type is one row here plus its two backend halves.
 */

/**
 * @typedef {object} FileSource
 * @property {string} type The `source_type` the API emits.
 * @property {string} label Heading above the source name, in the file drawer.
 * @property {string} indefiniteNoun Reads after « attaché à ».
 * @property {string} definiteNoun Reads after « depuis » or « à ».
 * @property {string} icon PrimeIcons classes, colour included.
 * @property {string} quotaLabel Legend entry in the storage breakdown, always plural.
 * @property {string} color Segment colour in the storage breakdown.
 * @property {string} routeName Band space route showing this kind of source.
 * @property {?string} routeQueryKey Query param that opens the source itself, when the view reads one.
 */

/** @type {readonly FileSource[]} */
const FILE_SOURCES = Object.freeze([
  Object.freeze({
    type: 'task',
    label: 'Tâche',
    indefiniteNoun: 'une tâche',
    definiteNoun: 'la tâche',
    icon: 'pi pi-check-square text-blue-500',
    quotaLabel: 'Tâches',
    color: '#8b5cf6',
    routeName: 'app_band_tasks',
    routeQueryKey: 'task'
  }),
  Object.freeze({
    type: 'finance',
    label: 'Entrée financière',
    indefiniteNoun: 'une entrée financière',
    definiteNoun: "l'entrée financière",
    icon: 'pi pi-euro text-amber-600',
    quotaLabel: 'Finances',
    color: '#f59e0b',
    routeName: 'app_band_finance',
    routeQueryKey: 'entry'
  }),
  Object.freeze({
    type: 'note',
    label: 'Note',
    indefiniteNoun: 'une note',
    definiteNoun: 'la note',
    icon: 'pi pi-file-edit text-purple-500',
    quotaLabel: 'Notes',
    color: '#06b6d4',
    routeName: 'app_band_notes',
    // Notes.vue selects through its store and reads no query param, so the link stops at the tree.
    routeQueryKey: null
  }),
  Object.freeze({
    type: 'song',
    label: 'Chanson',
    indefiniteNoun: 'une chanson',
    definiteNoun: 'la chanson',
    icon: 'pi pi-headphones text-emerald-600',
    quotaLabel: 'Chansons',
    color: '#10b981',
    // Songs live in the Répertoire, which is what Setlist.vue shows when no query param is set.
    routeName: 'app_band_setlist',
    routeQueryKey: null
  }),
  Object.freeze({
    type: 'setlist',
    label: 'Setlist',
    indefiniteNoun: 'une setlist',
    definiteNoun: 'la setlist',
    icon: 'pi pi-list text-rose-600',
    quotaLabel: 'Setlists',
    color: '#f43f5e',
    routeName: 'app_band_setlist',
    routeQueryKey: 'setlist'
  })
])

/**
 * Stands in for a source type the frontend has never heard of, so a backend that grew a sixth one
 * degrades to neutral wording instead of an empty label.
 */
const UNKNOWN_SOURCE = Object.freeze({
  label: 'Ressource',
  indefiniteNoun: 'une autre ressource',
  definiteNoun: null,
  icon: 'pi pi-link text-surface-500'
})

const FILE_SOURCE_BY_TYPE = Object.freeze(
  Object.fromEntries(FILE_SOURCES.map((source) => [source.type, source]))
)

/** Mirrors `BandSpaceFileSourceTypes::ALL`. */
export const FILE_SOURCE_TYPES = Object.freeze(FILE_SOURCES.map((source) => source.type))

/** « une tâche », « une entrée financière », ... for prose naming the sources one by one. */
export const FILE_SOURCE_NOUNS = Object.freeze(FILE_SOURCES.map((source) => source.indefiniteNoun))

/** « une tâche, une entrée financière, ... ou une setlist », for prose listing every source at once. */
export const FILE_SOURCE_LIST_LABEL = `${FILE_SOURCE_NOUNS.slice(0, -1).join(', ')} ou ${FILE_SOURCE_NOUNS.at(-1)}`

/**
 * The storage breakdown the quota endpoint returns, in display order.
 *
 * `manual` is not a source type: the query coalesces a missing attachment into it, so it is the
 * bucket for files nothing points at. It leads the list because it is usually the biggest one.
 */
export const QUOTA_BREAKDOWN_SOURCES = Object.freeze([
  Object.freeze({ key: 'manual', label: 'Manuels', color: '#3b82f6' }),
  ...FILE_SOURCES.map((source) =>
    Object.freeze({ key: source.type, label: source.quotaLabel, color: source.color })
  )
])

/**
 * Enumerates source types as a French list, « une tâche et une note ».
 *
 * Mirrors BandSpaceFileAttachmentLabels::describe down to the deduplication and the « et » before
 * the last one, because the bulk delete bar and the endpoint it calls have to say the same sentence:
 * one greys the button out, the other refuses the request, and a member comparing the two must not
 * read two different reasons.
 *
 * @param {string[]} sourceTypes
 * @returns {string}
 */
export function describeFileSources(sourceTypes) {
  const labels = [...new Set(sourceTypes.map((type) => sourceFor(type).indefiniteNoun))]

  if (labels.length === 0) return UNKNOWN_SOURCE.indefiniteNoun
  if (labels.length === 1) return labels[0]

  return `${labels.slice(0, -1).join(', ')} et ${labels.at(-1)}`
}

function sourceFor(sourceType) {
  return FILE_SOURCE_BY_TYPE[sourceType] ?? UNKNOWN_SOURCE
}

/**
 * @param {?string} sourceType
 * @returns {string} « Tâche », « Setlist », ... or « Ressource » for a type we do not know.
 */
export function fileSourceLabel(sourceType) {
  return sourceFor(sourceType).label
}

/**
 * @param {?string} sourceType
 * @returns {string} PrimeIcons classes, colour included.
 */
export function fileSourceIcon(sourceType) {
  return sourceFor(sourceType).icon
}

/**
 * @param {?string} sourceType
 * @returns {?string} « la tâche », « l'entrée financière », ... or null, so a caller building a
 *                    sentence around it can drop the sentence rather than name nothing.
 */
export function fileSourceDefiniteNoun(sourceType) {
  return sourceFor(sourceType).definiteNoun
}

/**
 * Why a file cannot be deleted from the library while it hangs on a source.
 *
 * @param {?string} sourceType
 * @returns {string}
 */
export function fileSourceDetachHint(sourceType) {
  return `Détachez-le d'abord depuis ${fileSourceDefiniteNoun(sourceType) ?? 'la ressource concernée'}`
}

/**
 * The same refusal, as a full sentence naming what the file is attached to.
 *
 * @param {?string} sourceType
 * @returns {string}
 */
export function fileSourceAttachedMessage(sourceType) {
  return `Ce fichier est attaché à ${sourceFor(sourceType).indefiniteNoun}. ${fileSourceDetachHint(sourceType)}.`
}

/**
 * Where an attachment sends the member, as a router target.
 *
 * The query param is only added when the destination view actually reads one, so a note or a song
 * lands on its module rather than on a URL carrying an id nothing opens.
 *
 * @param {?string} sourceType
 * @param {?string} bandSpaceId
 * @param {?string} sourceId
 * @returns {?{name: string, params: {id: string}, query?: Record<string, string>}} null when there is
 *          nowhere sensible to go, so the caller renders plain text instead of a link.
 */
export function fileSourceRoute(sourceType, bandSpaceId, sourceId) {
  const source = FILE_SOURCE_BY_TYPE[sourceType]
  if (!source || !bandSpaceId) return null

  const target = { name: source.routeName, params: { id: bandSpaceId } }
  if (source.routeQueryKey && sourceId) {
    target.query = { [source.routeQueryKey]: sourceId }
  }

  return target
}
