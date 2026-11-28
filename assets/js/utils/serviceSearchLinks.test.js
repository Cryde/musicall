import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { DIRECTORY, SERVICE_KINDS } from '../constants/serviceKinds.js'
import { serviceMenuEntries, visibleServiceSearches } from './serviceSearchLinks.js'

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

describe('serviceMenuEntries', () => {
  const kinds = {
    announced: {
      icon: 'pi pi-a',
      search: { route: 'app_search_a', menuKey: 'menu.a', testerOnly: false }
    },
    hidden: {
      icon: 'pi pi-h',
      search: { route: 'app_search_h', menuKey: 'menu.h', testerOnly: true }
    }
  }
  const directory = (testerOnly) => ({
    route: 'app_directory',
    menuKey: 'menu.directory',
    icon: 'pi pi-id-card',
    testerOnly
  })
  const annuaire = [
    { key: 'directory', menuKey: 'menu.directory', icon: 'pi pi-id-card', route: 'app_directory' }
  ]

  it('replaces every kind search with the annuaire once it is announced', () => {
    assert.deepEqual(serviceMenuEntries(kinds, directory(false), false), annuaire)
    assert.deepEqual(serviceMenuEntries(kinds, directory(false), true), annuaire)
  })

  it('shows the annuaire alone to a tester before it is announced', () => {
    assert.deepEqual(serviceMenuEntries(kinds, directory(true), true), annuaire)
  })

  it('keeps the announced kind searches for anyone else until then', () => {
    assert.deepEqual(serviceMenuEntries(kinds, directory(true), false), [
      { key: 'announced', menuKey: 'menu.a', icon: 'pi pi-a', route: 'app_search_a' }
    ])
  })

  // The annuaire is not announced yet (#1414): a tester gets it, everyone else keeps the teacher search.
  it('gives a tester the annuaire and everyone else the teacher search today', () => {
    assert.deepEqual(serviceMenuEntries(SERVICE_KINDS, DIRECTORY, true), annuaire)
    assert.deepEqual(serviceMenuEntries(SERVICE_KINDS, DIRECTORY, false), [
      {
        key: 'teaching',
        menuKey: 'menu.search_teachers',
        icon: 'pi pi-graduation-cap',
        route: 'app_search_teacher'
      }
    ])
  })
})
