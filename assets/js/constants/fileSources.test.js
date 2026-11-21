import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  CHAT_MESSAGE_SOURCE_TYPE,
  deleteBlockingAttachments,
  FILE_SOURCE_TYPES,
  fileActivitySourceNoun,
  fileSourceAttachedMessage,
  fileSourceDefiniteNoun,
  fileSourceDetachHint,
  fileSourceIcon,
  fileSourceLabel,
  fileSourceListLabel,
  fileSourceNouns,
  fileSourceRoute,
  QUOTA_BREAKDOWN_SOURCES
} from './fileSources.js'

/**
 * Every screen reading `source_type` used to carry its own three-of-five copy of this list, so a
 * file attached to a song or a setlist read as a generic « Ressource » and its bytes were missing
 * from the quota breakdown. The sweeps over FILE_SOURCE_TYPES below are what pin that down: a sixth
 * type added without wording, an icon, a destination or a quota bucket fails here.
 *
 * Run with `npm test`.
 */

/** BandSpaceFileSourceTypes::ALL, spelled out rather than derived, so a silent drop fails. */
const BACKEND_SOURCE_TYPES = ['task', 'finance', 'note', 'song', 'setlist']

describe('FILE_SOURCE_TYPES', () => {
  it('mirrors the backend allowlist, in the same order', () => {
    assert.deepEqual(FILE_SOURCE_TYPES, BACKEND_SOURCE_TYPES)
  })

  it('cannot be edited in place by a caller', () => {
    assert.equal(Object.isFrozen(FILE_SOURCE_TYPES), true)
  })
})

describe('fileSourceNouns', () => {
  it('covers the five source types the API accepts', () => {
    assert.deepEqual(fileSourceNouns(), [
      'une tâche',
      'une entrée financière',
      'une note',
      'un titre',
      'une setlist'
    ])
  })
})

describe('fileSourceListLabel', () => {
  it('lists every source, the last one after « ou »', () => {
    assert.equal(
      fileSourceListLabel(),
      'une tâche, une entrée financière, une note, un titre ou une setlist'
    )
  })

  it('names as many sources as there are types', () => {
    assert.equal(fileSourceListLabel().split(/, | ou /).length, FILE_SOURCE_TYPES.length)
  })
})

describe('fileSourceLabel', () => {
  it('names each source type', () => {
    assert.equal(fileSourceLabel('task'), 'Tâche')
    assert.equal(fileSourceLabel('finance'), 'Entrée financière')
    assert.equal(fileSourceLabel('note'), 'Note')
    assert.equal(fileSourceLabel('song'), 'Titre')
    assert.equal(fileSourceLabel('setlist'), 'Setlist')
  })

  it('never falls back to the generic label for a type the API can emit', () => {
    for (const sourceType of BACKEND_SOURCE_TYPES) {
      assert.notEqual(fileSourceLabel(sourceType), 'Ressource', `${sourceType} has no label`)
    }
  })

  it('falls back for an unknown type, and for none at all', () => {
    assert.equal(fileSourceLabel('gigposter'), 'Ressource')
    assert.equal(fileSourceLabel(undefined), 'Ressource')
    assert.equal(fileSourceLabel(null), 'Ressource')
  })
})

describe('fileSourceIcon', () => {
  it('gives each source type its own icon', () => {
    const icons = BACKEND_SOURCE_TYPES.map((sourceType) => fileSourceIcon(sourceType))

    assert.equal(new Set(icons).size, BACKEND_SOURCE_TYPES.length)
  })

  it('never falls back to the generic icon for a type the API can emit', () => {
    for (const sourceType of BACKEND_SOURCE_TYPES) {
      assert.notEqual(
        fileSourceIcon(sourceType),
        'pi pi-link text-surface-500',
        `${sourceType} has no icon`
      )
    }
  })

  it('is a PrimeIcons class list', () => {
    assert.equal(fileSourceIcon('setlist'), 'pi pi-list text-rose-600')
    assert.equal(fileSourceIcon('song'), 'pi pi-headphones text-emerald-600')
  })

  it('falls back for an unknown type', () => {
    assert.equal(fileSourceIcon('gigposter'), 'pi pi-link text-surface-500')
  })
})

describe('fileSourceDefiniteNoun', () => {
  it('reads after « depuis »', () => {
    assert.equal(fileSourceDefiniteNoun('task'), 'la tâche')
    assert.equal(fileSourceDefiniteNoun('finance'), "l'entrée financière")
    assert.equal(fileSourceDefiniteNoun('note'), 'la note')
    assert.equal(fileSourceDefiniteNoun('song'), 'le titre')
    assert.equal(fileSourceDefiniteNoun('setlist'), 'la setlist')
  })

  it('is null for an unknown type, so a sentence around it can be dropped', () => {
    assert.equal(fileSourceDefiniteNoun('gigposter'), null)
    assert.equal(fileSourceDefiniteNoun(undefined), null)
  })
})

// « à » and « de » contract with « le », so a file activity cannot put its preposition in front of
// the definite noun: « à le titre » is not French (#1436).
describe('fileActivitySourceNoun', () => {
  it('contracts « à » and « de » with a masculine noun', () => {
    assert.equal(fileActivitySourceNoun('attached', 'song'), 'au titre')
    assert.equal(fileActivitySourceNoun('detached', 'song'), 'du titre')
    assert.equal(fileActivitySourceNoun('source_deleted', 'song'), 'le titre')
  })

  it('keeps the article of a feminine or elided noun', () => {
    assert.equal(fileActivitySourceNoun('attached', 'task'), 'à la tâche')
    assert.equal(fileActivitySourceNoun('detached', 'task'), 'de la tâche')
    assert.equal(fileActivitySourceNoun('attached', 'finance'), "à l'entrée financière")
    assert.equal(fileActivitySourceNoun('detached', 'finance'), "de l'entrée financière")
  })

  it('covers every source type the API can emit', () => {
    for (const sourceType of BACKEND_SOURCE_TYPES) {
      assert.ok(fileActivitySourceNoun('attached', sourceType), `${sourceType} has no « à » form`)
      assert.ok(fileActivitySourceNoun('detached', sourceType), `${sourceType} has no « de » form`)
    }
  })

  it('is null for an unknown source, or an activity naming none, so its sentence can be dropped', () => {
    assert.equal(fileActivitySourceNoun('attached', 'gigposter'), null)
    assert.equal(fileActivitySourceNoun('detached', undefined), null)
    assert.equal(fileActivitySourceNoun('uploaded', 'song'), null)
  })
})

describe('fileSourceDetachHint', () => {
  it('names the source to detach from', () => {
    assert.equal(fileSourceDetachHint('setlist'), "Détache-le d'abord depuis la setlist")
    assert.equal(fileSourceDetachHint('song'), "Détache-le d'abord depuis le titre")
  })

  it('never names the generic resource for a type the API can emit', () => {
    for (const sourceType of BACKEND_SOURCE_TYPES) {
      assert.equal(
        fileSourceDetachHint(sourceType).includes('la ressource concernée'),
        false,
        `${sourceType} is described generically`
      )
    }
  })

  it('stays a sentence for an unknown type', () => {
    assert.equal(
      fileSourceDetachHint('gigposter'),
      "Détache-le d'abord depuis la ressource concernée"
    )
  })
})

describe('fileSourceAttachedMessage', () => {
  it('says what the file hangs on and how to free it', () => {
    assert.equal(
      fileSourceAttachedMessage('song'),
      "Ce fichier est attaché à un titre. Détache-le d'abord depuis le titre."
    )
    assert.equal(
      fileSourceAttachedMessage('task'),
      "Ce fichier est attaché à une tâche. Détache-le d'abord depuis la tâche."
    )
  })

  it('stays a sentence for an unknown type', () => {
    assert.equal(
      fileSourceAttachedMessage('gigposter'),
      "Ce fichier est attaché à une autre ressource. Détache-le d'abord depuis la ressource concernée."
    )
  })
})

describe('fileSourceRoute', () => {
  it('sends every source type somewhere inside its band space', () => {
    for (const sourceType of BACKEND_SOURCE_TYPES) {
      const target = fileSourceRoute(sourceType, 'space-1', 'source-1')

      assert.notEqual(target, null, `${sourceType} has no destination`)
      assert.equal(target.params.id, 'space-1')
      assert.match(target.name, /^app_band_/)
    }
  })

  it('opens the source itself when the destination view reads a query param', () => {
    assert.deepEqual(fileSourceRoute('task', 'space-1', 'task-1'), {
      name: 'app_band_tasks',
      params: { id: 'space-1' },
      query: { task: 'task-1' }
    })
    assert.deepEqual(fileSourceRoute('finance', 'space-1', 'entry-1'), {
      name: 'app_band_finance',
      params: { id: 'space-1' },
      query: { entry: 'entry-1' }
    })
    assert.deepEqual(fileSourceRoute('setlist', 'space-1', 'setlist-1'), {
      name: 'app_band_setlist',
      params: { id: 'space-1' },
      query: { setlist: 'setlist-1' }
    })
    assert.deepEqual(fileSourceRoute('note', 'space-1', 'note-1'), {
      name: 'app_band_notes',
      params: { id: 'space-1' },
      query: { note: 'note-1' }
    })
    assert.deepEqual(fileSourceRoute('song', 'space-1', 'song-1'), {
      name: 'app_band_song',
      params: { id: 'space-1', songId: 'song-1' }
    })
  })

  it('drops the query param when the source id is missing', () => {
    assert.deepEqual(fileSourceRoute('setlist', 'space-1', null), {
      name: 'app_band_setlist',
      params: { id: 'space-1' }
    })
  })

  it('is null when there is nowhere sensible to go', () => {
    // A song's page is named by the song: without it there is no page.
    assert.equal(fileSourceRoute('song', 'space-1', null), null)
    assert.equal(fileSourceRoute('gigposter', 'space-1', 'x'), null)
    assert.equal(fileSourceRoute(undefined, 'space-1', 'x'), null)
    assert.equal(fileSourceRoute('task', null, 'task-1'), null)
  })
})

describe('QUOTA_BREAKDOWN_SOURCES', () => {
  it('covers every source type the API can emit, plus the unattached bucket and the chat', () => {
    assert.deepEqual(
      QUOTA_BREAKDOWN_SOURCES.map((source) => source.key),
      ['manual', ...BACKEND_SOURCE_TYPES, CHAT_MESSAGE_SOURCE_TYPE]
    )
  })

  it('labels every bucket in the plural', () => {
    assert.deepEqual(
      QUOTA_BREAKDOWN_SOURCES.map((source) => source.label),
      ['Manuels', 'Tâches', 'Finances', 'Notes', 'Titres', 'Setlists', 'Chat']
    )
  })

  it('gives every bucket its own colour, so two segments never merge', () => {
    const colors = QUOTA_BREAKDOWN_SOURCES.map((source) => source.color)

    assert.equal(new Set(colors).size, QUOTA_BREAKDOWN_SOURCES.length)
    for (const color of colors) {
      assert.match(color, /^#[0-9a-f]{6}$/)
    }
  })

  it('cannot be edited in place by a caller', () => {
    assert.equal(Object.isFrozen(QUOTA_BREAKDOWN_SOURCES), true)
    assert.equal(Object.isFrozen(QUOTA_BREAKDOWN_SOURCES[0]), true)
  })
})

describe('chat images (#973)', () => {
  it('stays out of the sources a member can attach to', () => {
    assert.equal(FILE_SOURCE_TYPES.includes(CHAT_MESSAGE_SOURCE_TYPE), false)
  })

  it('never blocks a delete, while every other attachment does', () => {
    const attachments = [
      { source_type: 'message', source_id: 'm1' },
      { source_type: 'task', source_id: 't1' }
    ]

    assert.deepEqual(deleteBlockingAttachments(attachments), [
      { source_type: 'task', source_id: 't1' }
    ])
    assert.deepEqual(deleteBlockingAttachments([{ source_type: 'message', source_id: 'm1' }]), [])
    assert.deepEqual(deleteBlockingAttachments(undefined), [])
  })

  it('names the chat and leads back to the message', () => {
    assert.equal(fileSourceLabel('message'), 'Message du chat')
    assert.deepEqual(fileSourceRoute('message', 'space-1', 'm1'), {
      name: 'app_band_chat',
      params: { id: 'space-1' },
      query: { message: 'm1' }
    })
  })
})
