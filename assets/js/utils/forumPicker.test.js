import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { forumPickerGroups } from './forumPicker.js'

describe('forumPickerGroups', () => {
  const introductions = { id: 'f1', title: 'Introductions', slug: 'introductions' }
  const theory = { id: 'f2', title: 'Music theory', slug: 'music-theory' }
  const production = { id: 'f3', title: 'Music production', slug: 'music-production' }

  it('keeps every category with its forums, in the order the API lists them', () => {
    const categories = [
      { id: 'c1', title: 'General', forums: [introductions] },
      { id: 'c2', title: 'Help', forums: [theory, production] }
    ]

    assert.deepEqual(forumPickerGroups(categories), categories)
  })

  it('leaves out a category without a forum', () => {
    const help = { id: 'c2', title: 'Help', forums: [theory] }

    assert.deepEqual(forumPickerGroups([{ id: 'c1', title: 'Empty', forums: [] }, help]), [help])
  })

  it('offers nothing when there is no category', () => {
    assert.deepEqual(forumPickerGroups([]), [])
  })
})
