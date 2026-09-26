import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { songFileCounts } from './songFileCounts.js'

describe('songFileCounts', () => {
  it('counts the files of each song and ignores other attachments', () => {
    const counts = songFileCounts([
      { attachments: [{ source_type: 'song', source_id: 'a' }] },
      {
        attachments: [
          { source_type: 'song', source_id: 'a' },
          { source_type: 'task', source_id: 't' }
        ]
      },
      { attachments: [{ source_type: 'song', source_id: 'b' }] },
      {}
    ])

    assert.deepEqual(
      [...counts],
      [
        ['a', 2],
        ['b', 1]
      ]
    )
  })
})
