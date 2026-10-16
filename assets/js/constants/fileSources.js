import { intlLocale } from '../i18n/formatLocale.js'
import { i18n } from '../i18n/index.js'

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
    get label() {
      return i18n.global.t('band_space_files.source.task.label')
    },
    get indefiniteNoun() {
      return i18n.global.t('band_space_files.source.task.indefinite')
    },
    get definiteNoun() {
      return i18n.global.t('band_space_files.source.task.definite')
    },
    icon: 'pi pi-check-square text-blue-500',
    get quotaLabel() {
      return i18n.global.t('band_space_files.source.task.quota')
    },
    color: '#8b5cf6',
    routeName: 'app_band_tasks',
    routeQueryKey: 'task'
  }),
  Object.freeze({
    type: 'finance',
    get label() {
      return i18n.global.t('band_space_files.source.finance.label')
    },
    get indefiniteNoun() {
      return i18n.global.t('band_space_files.source.finance.indefinite')
    },
    get definiteNoun() {
      return i18n.global.t('band_space_files.source.finance.definite')
    },
    icon: 'pi pi-euro text-amber-600',
    get quotaLabel() {
      return i18n.global.t('band_space_files.source.finance.quota')
    },
    color: '#f59e0b',
    routeName: 'app_band_finance',
    routeQueryKey: 'entry'
  }),
  Object.freeze({
    type: 'note',
    get label() {
      return i18n.global.t('band_space_files.source.note.label')
    },
    get indefiniteNoun() {
      return i18n.global.t('band_space_files.source.note.indefinite')
    },
    get definiteNoun() {
      return i18n.global.t('band_space_files.source.note.definite')
    },
    icon: 'pi pi-file-edit text-purple-500',
    get quotaLabel() {
      return i18n.global.t('band_space_files.source.note.quota')
    },
    color: '#06b6d4',
    routeName: 'app_band_notes',
    routeQueryKey: 'note'
  }),
  Object.freeze({
    type: 'song',
    get label() {
      return i18n.global.t('band_space_files.source.song.label')
    },
    get indefiniteNoun() {
      return i18n.global.t('band_space_files.source.song.indefinite')
    },
    get definiteNoun() {
      return i18n.global.t('band_space_files.source.song.definite')
    },
    icon: 'pi pi-headphones text-emerald-600',
    get quotaLabel() {
      return i18n.global.t('band_space_files.source.song.quota')
    },
    color: '#10b981',
    // Songs live in the Répertoire, which opens the song's drawer from `?song=`.
    routeName: 'app_band_setlist',
    routeQueryKey: 'song'
  }),
  Object.freeze({
    type: 'setlist',
    get label() {
      return i18n.global.t('band_space_files.source.setlist.label')
    },
    get indefiniteNoun() {
      return i18n.global.t('band_space_files.source.setlist.indefinite')
    },
    get definiteNoun() {
      return i18n.global.t('band_space_files.source.setlist.definite')
    },
    icon: 'pi pi-list text-rose-600',
    get quotaLabel() {
      return i18n.global.t('band_space_files.source.setlist.quota')
    },
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
  get label() {
    return i18n.global.t('band_space_files.source.unknown.label')
  },
  get indefiniteNoun() {
    return i18n.global.t('band_space_files.source.unknown.indefinite')
  },
  definiteNoun: null,
  icon: 'pi pi-link text-surface-500'
})

/**
 * An image posted in the chat (#973), mirroring `BandSpaceFileSourceTypes::CHAT_MESSAGE`. Kept out of
 * FILE_SOURCES on purpose, like it is kept out of `ALL`: nobody attaches a file to a message by hand,
 * and the message owns its image rather than pointing at it, so it never blocks a delete and has no
 * place in the sentences listing what does.
 */
export const CHAT_MESSAGE_SOURCE_TYPE = 'message'

const CHAT_MESSAGE_SOURCE = Object.freeze({
  type: CHAT_MESSAGE_SOURCE_TYPE,
  get label() {
    return i18n.global.t('band_space_files.source.message.label')
  },
  get indefiniteNoun() {
    return i18n.global.t('band_space_files.source.message.indefinite')
  },
  get definiteNoun() {
    return i18n.global.t('band_space_files.source.message.definite')
  },
  icon: 'pi pi-comments text-sky-600',
  get quotaLabel() {
    return i18n.global.t('band_space_files.source.message.quota')
  },
  color: '#0ea5e9',
  routeName: 'app_band_chat',
  routeQueryKey: 'message'
})

const FILE_SOURCE_BY_TYPE = Object.freeze(
  Object.fromEntries([...FILE_SOURCES, CHAT_MESSAGE_SOURCE].map((source) => [source.type, source]))
)

/** Mirrors `BandSpaceFileSourceTypes::ALL`. */
export const FILE_SOURCE_TYPES = Object.freeze(FILE_SOURCES.map((source) => source.type))

/** « une tâche », « une entrée financière », ... for prose naming the sources one by one. */
export function fileSourceNouns() {
  return FILE_SOURCES.map((source) => source.indefiniteNoun)
}

/** « une tâche, une entrée financière, ... ou une setlist », for prose listing every source at once. */
export function fileSourceListLabel() {
  return new Intl.ListFormat(intlLocale(), { type: 'disjunction' }).format(fileSourceNouns())
}

/**
 * The storage breakdown the quota endpoint returns, in display order.
 *
 * `manual` is not a source type: the query coalesces a missing attachment into it, so it is the
 * bucket for files nothing points at. It leads the list because it is usually the biggest one.
 */
export const QUOTA_BREAKDOWN_SOURCES = Object.freeze([
  Object.freeze({
    key: 'manual',
    get label() {
      return i18n.global.t('band_space_files.source.manual_quota')
    },
    color: '#3b82f6'
  }),
  ...[...FILE_SOURCES, CHAT_MESSAGE_SOURCE].map((source) =>
    Object.freeze({
      key: source.type,
      get label() {
        return source.quotaLabel
      },
      color: source.color
    })
  )
])

/**
 * The attachments that keep a file from being deleted: every one but a chat image's (#973). Mirrors
 * `BandSpaceFileAttachmentRepository::findDeleteBlockingSourceTypesByFileIds`.
 *
 * @param {{source_type?: string}[]} attachments
 */
export function deleteBlockingAttachments(attachments = []) {
  return attachments.filter((attachment) => attachment.source_type !== CHAT_MESSAGE_SOURCE_TYPE)
}

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

  return new Intl.ListFormat(intlLocale(), { type: 'conjunction' }).format(labels)
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
  const definiteNoun = fileSourceDefiniteNoun(sourceType)

  return definiteNoun === null
    ? i18n.global.t('band_space_files.source.detach_hint_unknown')
    : i18n.global.t('band_space_files.source.detach_hint', { source: definiteNoun })
}

/**
 * The same refusal, as a full sentence naming what the file is attached to.
 *
 * @param {?string} sourceType
 * @returns {string}
 */
export function fileSourceAttachedMessage(sourceType) {
  return i18n.global.t('band_space_files.source.attached_message', {
    source: sourceFor(sourceType).indefiniteNoun,
    hint: fileSourceDetachHint(sourceType)
  })
}

/**
 * Where an attachment sends the member, as a router target.
 *
 * The query param is only added when the destination view actually reads one, so a source no
 * view can open lands on its module rather than on a URL carrying an id nothing opens.
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
