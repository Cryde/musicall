import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  FILE_SOURCE_LIST_LABEL,
  FILE_SOURCE_NOUNS,
  FILE_SOURCE_TYPES,
  fileSourceAttachedMessage,
  fileSourceDefiniteNoun,
  fileSourceDetachHint,
  fileSourceIcon,
  fileSourceLabel,
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

describe('FILE_SOURCE_NOUNS', () => {
  it('covers the five source types the API accepts', () => {
    assert.deepEqual(FILE_SOURCE_NOUNS, [
      'une tâche',
      'une entrée financière',
      'une note',
      'une chanson',
      'une setlist'
    ])
  })

  it('cannot be edited in place by a caller', () => {
    assert.equal(Object.isFrozen(FILE_SOURCE_NOUNS), true)
  })
})

describe('FILE_SOURCE_LIST_LABEL', () => {
  it('lists every source, the last one after « ou »', () => {
    assert.equal(
      FILE_SOURCE_LIST_LABEL,
      'une tâche, une entrée financière, une note, une chanson ou une setlist'
    )
  })

  it('names as many sources as there are types', () => {
    assert.equal(FILE_SOURCE_LIST_LABEL.split(/, | ou /).length, FILE_SOURCE_TYPES.length)
  })
})

describe('fileSourceLabel', () => {
  it('names each source type', () => {
    assert.equal(fileSourceLabel('task'), 'Tâche')
    assert.equal(fileSourceLabel('finance'), 'Entrée financière')
    assert.equal(fileSourceLabel('note'), 'Note')
    assert.equal(fileSourceLabel('song'), 'Chanson')
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
    assert.equal(fileSourceDefiniteNoun('song'), 'la chanson')
    assert.equal(fileSourceDefiniteNoun('setlist'), 'la setlist')
  })

  it('is null for an unknown type, so a sentence around it can be dropped', () => {
    assert.equal(fileSourceDefiniteNoun('gigposter'), null)
    assert.equal(fileSourceDefiniteNoun(undefined), null)
  })
})

describe('fileSourceDetachHint', () => {
  it('names the source to detach from', () => {
    assert.equal(fileSourceDetachHint('setlist'), "Détachez-le d'abord depuis la setlist")
    assert.equal(fileSourceDetachHint('song'), "Détachez-le d'abord depuis la chanson")
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
      "Détachez-le d'abord depuis la ressource concernée"
    )
  })
})

describe('fileSourceAttachedMessage', () => {
  it('says what the file hangs on and how to free it', () => {
    assert.equal(
      fileSourceAttachedMessage('song'),
      "Ce fichier est attaché à une chanson. Détachez-le d'abord depuis la chanson."
    )
    assert.equal(
      fileSourceAttachedMessage('task'),
      "Ce fichier est attaché à une tâche. Détachez-le d'abord depuis la tâche."
    )
  })

  it('stays a sentence for an unknown type', () => {
    assert.equal(
      fileSourceAttachedMessage('gigposter'),
      "Ce fichier est attaché à une autre ressource. Détachez-le d'abord depuis la ressource concernée."
    )
  })
})

describe('fileSourceRoute', () => {
  it('sends every source type somewhere inside its band space', () => {
    for (const sourceType of BACKEND_SOURCE_TYPES) {
      const target = fileSourceRoute(sourceType, 'space-1', 'source-1')

      assert.notEqual(target, null, `${sourceType} has no destination`)
      assert.deepEqual(target.params, { id: 'space-1' })
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
  })

  it('stops at the module for a source no view can deep link to', () => {
    assert.deepEqual(fileSourceRoute('note', 'space-1', 'note-1'), {
      name: 'app_band_notes',
      params: { id: 'space-1' }
    })
    assert.deepEqual(fileSourceRoute('song', 'space-1', 'song-1'), {
      name: 'app_band_setlist',
      params: { id: 'space-1' }
    })
  })

  it('drops the query param when the source id is missing', () => {
    assert.deepEqual(fileSourceRoute('setlist', 'space-1', null), {
      name: 'app_band_setlist',
      params: { id: 'space-1' }
    })
  })

  it('is null when there is nowhere sensible to go', () => {
    assert.equal(fileSourceRoute('gigposter', 'space-1', 'x'), null)
    assert.equal(fileSourceRoute(undefined, 'space-1', 'x'), null)
    assert.equal(fileSourceRoute('task', null, 'task-1'), null)
  })
})

describe('QUOTA_BREAKDOWN_SOURCES', () => {
  it('covers every source type the API can emit, plus the unattached bucket', () => {
    assert.deepEqual(
      QUOTA_BREAKDOWN_SOURCES.map((source) => source.key),
      ['manual', ...BACKEND_SOURCE_TYPES]
    )
  })

  it('labels every bucket in the plural', () => {
    assert.deepEqual(
      QUOTA_BREAKDOWN_SOURCES.map((source) => source.label),
      ['Manuels', 'Tâches', 'Finances', 'Notes', 'Chansons', 'Setlists']
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
