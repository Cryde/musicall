import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { fileSongs, songFileCounts, songsWithFiles } from './songFileCounts.js'

const song = (id, title) => ({ source_type: 'song', source_id: id, source_label: title })

describe('fileSongs', () => {
  it('names the songs a file is attached to, in the order they were attached', () => {
    const file = {
      attachments: [
        song('ember', 'Ember Road'),
        { source_type: 'task', source_id: 't1', source_label: 'Envoyer la grille' },
        song('new45', 'New 45')
      ]
    }

    assert.deepEqual(fileSongs(file), [
      { id: 'ember', title: 'Ember Road' },
      { id: 'new45', title: 'New 45' }
    ])
  })

  it('is empty for a file attached to no song', () => {
    assert.deepEqual(fileSongs({}), [])
    assert.deepEqual(fileSongs({ attachments: [{ source_type: 'note', source_id: 'n1' }] }), [])
  })
})

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

describe('songsWithFiles', () => {
  it('makes one node per song, with how many files it has', () => {
    const songs = songsWithFiles([
      { attachments: [song('new45', 'New 45')] },
      { attachments: [song('new45', 'New 45'), song('ember', 'Ember Road')] },
      {
        attachments: [{ source_type: 'task', source_id: 't1', source_label: 'Réserver la salle' }]
      },
      {}
    ])

    assert.deepEqual(songs, [
      { id: 'ember', title: 'Ember Road', fileCount: 1 },
      { id: 'new45', title: 'New 45', fileCount: 2 }
    ])
  })

  it('sorts by title in French, accents and case aside, as the répertoire does', () => {
    const titles = songsWithFiles([
      { attachments: [song('g', 'Glass Harbour')] },
      { attachments: [song('n', 'New 45')] },
      { attachments: [song('e', 'Écume')] },
      { attachments: [song('t', 'neon tide')] },
      { attachments: [song('d', 'Driftwood')] }
    ]).map((node) => node.title)

    // A plain comparison would put « Écume » last and « neon tide » after « New 45 ».
    assert.deepEqual(titles, ['Driftwood', 'Écume', 'Glass Harbour', 'neon tide', 'New 45'])
  })

  it('is empty when no file is attached to a song', () => {
    assert.deepEqual(songsWithFiles([]), [])
    assert.deepEqual(songsWithFiles([{ attachments: [] }]), [])
  })
})
