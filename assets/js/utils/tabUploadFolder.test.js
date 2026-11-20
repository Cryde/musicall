import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { rememberedTabFolder, rememberTabFolder } from './tabUploadFolder.js'

function memoryStorage() {
  const items = new Map()
  const storage = {
    getItem: (key) => (items.has(key) ? items.get(key) : null),
    setItem: (key, value) => items.set(key, String(value)),
    removeItem: (key) => items.delete(key)
  }

  return () => storage
}

// What a browser blocking site data does: reading `window.localStorage` itself throws.
function blockedStorage() {
  throw new Error('SecurityError')
}

const folders = [
  { id: 'songs', name: 'Morceaux', children: [{ id: 'year', name: '2026', children: [] }] }
]

describe('the folder « Ajouter une tablature » proposes', () => {
  it('is the root the first time', () => {
    assert.equal(rememberedTabFolder('space-1', folders, memoryStorage()), null)
  })

  it('is the last folder chosen, subfolders included', () => {
    const storage = memoryStorage()
    rememberTabFolder('space-1', 'year', storage)

    assert.equal(rememberedTabFolder('space-1', folders, storage), 'year')
  })

  it('is remembered per space', () => {
    const storage = memoryStorage()
    rememberTabFolder('space-1', 'songs', storage)

    assert.equal(rememberedTabFolder('space-2', folders, storage), null)
  })

  it('goes back to the root once the root is chosen', () => {
    const storage = memoryStorage()
    rememberTabFolder('space-1', 'songs', storage)
    rememberTabFolder('space-1', null, storage)

    assert.equal(rememberedTabFolder('space-1', folders, storage), null)
  })

  it('is the root when the remembered folder is gone, so the upload is not refused', () => {
    const storage = memoryStorage()
    rememberTabFolder('space-1', 'deleted-folder', storage)

    assert.equal(rememberedTabFolder('space-1', folders, storage), null)
  })

  it('is the root when the browser refuses storage', () => {
    assert.doesNotThrow(() => rememberTabFolder('space-1', 'songs', blockedStorage))
    assert.equal(rememberedTabFolder('space-1', folders, blockedStorage), null)
  })
})
