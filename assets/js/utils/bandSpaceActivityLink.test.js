import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { activityLink } from './bandSpaceActivityLink.js'

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

  it('leads nowhere without a resource id', () => {
    assert.equal(link('task', 'status_changed', null), null)
    assert.equal(link('agenda', 'feed_generated', null), null)
  })
})
