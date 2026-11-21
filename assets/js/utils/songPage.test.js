import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { songPageRedirect, songPageRoute, songPageWayBack } from './songPage.js'

describe('songPageRoute', () => {
  it('names the song page with the space and the song', () => {
    assert.deepEqual(songPageRoute('space-1', 'song-9'), {
      name: 'app_band_song',
      params: { id: 'space-1', songId: 'song-9' }
    })
  })

  it('carries the name of the screen that opened it in the history state', () => {
    assert.deepEqual(songPageRoute('space-1', 'song-9', 'Concert du 12'), {
      name: 'app_band_song',
      params: { id: 'space-1', songId: 'song-9' },
      state: { backLabel: 'Concert du 12' }
    })
  })
})

describe('songPageRedirect', () => {
  it('sends a ?song= link, made for the old drawer, to the page, replacing the history entry', () => {
    assert.deepEqual(songPageRedirect({ params: { id: 'space-1' }, query: { song: 'song-9' } }), {
      name: 'app_band_song',
      params: { id: 'space-1', songId: 'song-9' },
      replace: true
    })
  })

  it('leaves the module alone without a song in the query', () => {
    assert.equal(songPageRedirect({ params: { id: 'space-1' }, query: {} }), null)
    assert.equal(songPageRedirect({ params: { id: 'space-1' }, query: { setlist: 'set-2' } }), null)
  })

  it('ignores a song that is not one value', () => {
    assert.equal(songPageRedirect({ params: { id: 'space-1' }, query: { song: '' } }), null)
    assert.equal(songPageRedirect({ params: { id: 'space-1' }, query: { song: ['a', 'b'] } }), null)
  })
})

describe('songPageWayBack', () => {
  function routeNameOf(path) {
    if (path.startsWith('/band/space-1/setlists/chansons/')) return 'app_band_song'
    if (path.startsWith('/band/space-1/setlists')) return 'app_band_setlist'
    return 'app_band_files'
  }

  it('steps back to the répertoire it came from, which needs no name', () => {
    assert.deepEqual(songPageWayBack({ back: '/band/space-1/setlists?q=ember' }, routeNameOf), {
      returns: true,
      label: ''
    })
  })

  it('steps back to the set it came from, under the name the set gave', () => {
    assert.deepEqual(
      songPageWayBack(
        { back: '/band/space-1/setlists?setlist=set-2', backLabel: 'Concert du 12' },
        routeNameOf
      ),
      {
        returns: true,
        label: 'Concert du 12'
      }
    )
  })

  it('opens the répertoire when the page was reached from elsewhere, whatever name it carries', () => {
    assert.deepEqual(
      songPageWayBack({ back: '/band/space-1/fichiers', backLabel: 'Fichiers' }, routeNameOf),
      { returns: false, label: '' }
    )
    assert.deepEqual(
      songPageWayBack({ back: '/band/space-1/setlists/chansons/song-8' }, routeNameOf),
      { returns: false, label: '' }
    )
  })

  it('opens the répertoire for a page opened on its own', () => {
    assert.deepEqual(songPageWayBack({ back: null }, routeNameOf), { returns: false, label: '' })
    assert.deepEqual(songPageWayBack(null, routeNameOf), { returns: false, label: '' })
  })
})
