import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { describe, it } from 'node:test'
import { activityLink } from './bandSpaceActivityLink.js'

// Every activity type, per module, and the kind of item it names (null: none). The PHP side checks the
// same file against BandSpaceActivityResourceKind and the activity type enums (#1354).
const LINKS = JSON.parse(
  readFileSync(new URL('../../../tests/Fixtures/BandSpaceActivity/links.json', import.meta.url))
)

// The screen and the query key each kind of item is opened by.
const OPENED_BY = {
  task: ['app_band_tasks', 'task'],
  note: ['app_band_notes', 'note'],
  agenda_entry: ['app_band_agenda', 'entry'],
  finance_entry: ['app_band_finance', 'entry'],
  file: ['app_band_files', 'file'],
  setlist: ['app_band_setlist', 'setlist'],
  song: ['app_band_setlist', 'song'],
  lyrics: ['app_band_setlist', 'lyrics'],
  rider: ['app_band_rider', 'rider']
}

/** Where an activity line leads (#1159). Run with `npm test`. */
function link(module, type, resourceId = 'item-1') {
  return activityLink({ module, type, resource_id: resourceId }, 'space-1')
}

function to(name, query) {
  return { name, params: { id: 'space-1' }, query }
}

describe('activityLink', () => {
  it('opens the task of any task activity, a comment included', () => {
    assert.deepEqual(link('task', 'comment_added'), to('app_band_tasks', { task: 'item-1' }))
    assert.deepEqual(link('task', 'status_changed'), to('app_band_tasks', { task: 'item-1' }))
  })

  it('opens a note, an agenda entry and a finance entry', () => {
    assert.deepEqual(link('notes', 'note_renamed'), to('app_band_notes', { note: 'item-1' }))
    assert.deepEqual(link('agenda', 'title_changed'), to('app_band_agenda', { entry: 'item-1' }))
    assert.deepEqual(link('finance', 'split_added'), to('app_band_finance', { entry: 'item-1' }))
  })

  it('opens a file, a setlist, a song and a rider', () => {
    assert.deepEqual(link('file', 'uploaded'), to('app_band_files', { file: 'item-1' }))
    assert.deepEqual(
      link('setlist', 'setlist_renamed'),
      to('app_band_setlist', { setlist: 'item-1' })
    )
    assert.deepEqual(link('setlist', 'song_updated'), to('app_band_setlist', { song: 'item-1' }))
    for (const type of ['lyrics_added', 'lyrics_updated', 'lyrics_linked', 'lyrics_unlinked']) {
      assert.deepEqual(
        link('setlist', type),
        to('app_band_setlist', { view: 'lyrics', lyrics: 'item-1' })
      )
    }
    assert.deepEqual(link('rider', 'rider_renamed'), to('app_band_rider', { rider: 'item-1' }))
  })

  it('leads nowhere for an item that is gone or in the trash', () => {
    assert.equal(link('notes', 'note_deleted'), null)
    assert.equal(link('agenda', 'entry_deleted'), null)
    assert.equal(link('finance', 'entry_deleted'), null)
    assert.equal(link('file', 'archived'), null)
    assert.equal(link('file', 'purged'), null)
    assert.equal(link('setlist', 'setlist_archived'), null)
    assert.equal(link('setlist', 'song_archived'), null)
    assert.equal(link('setlist', 'lyrics_archived'), null)
    assert.equal(link('rider', 'rider_archived'), null)
  })

  it('leads nowhere when the id names something with no screen of its own', () => {
    // A category, a recurrence, a folder, a rider section, the space, a member or an invitation.
    assert.equal(link('finance', 'category_created'), null)
    assert.equal(link('finance', 'recurrence_updated'), null)
    assert.equal(link('file', 'folder_created'), null)
    assert.equal(link('rider', 'rider_item_updated'), null)
    assert.equal(link('setlist', 'songs_archived'), null)
    assert.equal(link('settings', 'member_role_changed'), null)
  })

  it('leads nowhere for a deleted task, alone or in a selection', () => {
    assert.equal(link('task', 'task_deleted'), null)
    assert.equal(link('task', 'tasks_deleted', null), null)
  })

  it('leads nowhere when the server says the item is gone since (#1354)', () => {
    const activity = { module: 'task', type: 'task_archived', resource_id: 'item-1' }
    assert.equal(activityLink({ ...activity, resource_available: false }, 'space-1'), null)
    assert.deepEqual(
      activityLink({ ...activity, resource_available: true }, 'space-1'),
      to('app_band_tasks', { task: 'item-1' })
    )
    // A payload from before the field existed still links.
    assert.deepEqual(activityLink(activity, 'space-1'), to('app_band_tasks', { task: 'item-1' }))
  })

  it('agrees with the server on which types name an item, and which kind', () => {
    for (const [module, types] of Object.entries(LINKS)) {
      for (const [type, kind] of Object.entries(types)) {
        const location = link(module, type)
        if (kind === null) {
          assert.equal(location, null, `${module}.${type}`)
        } else {
          const [route, key] = OPENED_BY[kind]
          assert.equal(location?.name, route, `${module}.${type}`)
          assert.equal(location?.query[key], 'item-1', `${module}.${type}`)
        }
      }
    }
  })

  it('leads nowhere without a resource id', () => {
    assert.equal(link('task', 'status_changed', null), null)
    assert.equal(link('agenda', 'feed_generated', null), null)
  })
})
