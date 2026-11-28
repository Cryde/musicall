import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { SERVICE_KINDS } from '../constants/serviceKinds.js'
import { visibleServiceSearches } from './serviceSearchLinks.js'

const kindsOf = (searches) => searches.map(([kind]) => kind)

describe('visibleServiceSearches', () => {
  const kinds = {
    announced: { search: { testerOnly: false } },
    hidden: { search: { testerOnly: true } }
  }

  it('shows a search not yet announced to a tester, in the kinds order', () => {
    assert.deepEqual(kindsOf(visibleServiceSearches(kinds, true)), ['announced', 'hidden'])
  })

  it('keeps it from anyone else', () => {
    assert.deepEqual(kindsOf(visibleServiceSearches(kinds, false)), ['announced'])
  })

  // The teacher search is announced; sound engineers, photographers, videographers, graphic designers, webmasters and
  // producers not yet (#1410, #1411, #1412, #1413).
  it('shows only the teacher search to a member who is not a tester today', () => {
    assert.deepEqual(kindsOf(visibleServiceSearches(SERVICE_KINDS, false)), ['teaching'])
    assert.deepEqual(kindsOf(visibleServiceSearches(SERVICE_KINDS, true)), [
      'teaching',
      'sound_engineering',
      'photography',
      'video',
      'graphic_design',
      'web',
      'production'
    ])
  })
})
