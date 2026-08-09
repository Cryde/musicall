import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { FILE_SOURCE_LIST_LABEL, FILE_SOURCE_NOUNS } from './fileSources.js'

/**
 * The folder delete dialog promises the cascade is refused when a file is attached. Naming fewer
 * sources than the API actually refuses on is the drift this pins down.
 *
 * Run with `npm test`.
 */

describe('FILE_SOURCE_NOUNS', () => {
  it('covers the five source types the API accepts', () => {
    assert.deepEqual(FILE_SOURCE_NOUNS, [
      'une tâche',
      'une note',
      'une entrée financière',
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
      'une tâche, une note, une entrée financière, une chanson ou une setlist'
    )
  })

  it('names as many sources as there are types', () => {
    assert.equal(FILE_SOURCE_LIST_LABEL.split(/, | ou /).length, FILE_SOURCE_NOUNS.length)
  })
})
