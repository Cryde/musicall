import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { createMemoryHistory, createRouter } from 'vue-router'
import {
  createVersionWatch,
  fetchDeployedVersion,
  isOtherVersion,
  loadOutdatedPagesInFull,
  shouldLoadInFull,
  throttle
} from './appVersion.js'

/**
 * A tab running an older build than the deployed one (#1105). Run with `npm test`.
 */

describe('isOtherVersion', () => {
  it('tells a deployed build apart from the running one', () => {
    assert.equal(isOtherVersion('20261114150000000', '20261113090000000'), true)
    assert.equal(isOtherVersion('20261113090000000', '20261113090000000'), false)
  })

  it('reads nothing into a missing or empty version', () => {
    for (const deployed of [undefined, null, '', 42]) {
      assert.equal(isOtherVersion(deployed, '20261113090000000'), false, String(deployed))
    }
  })
})

describe('createVersionWatch', () => {
  it('says once that a newer build is deployed, and remembers it', () => {
    const calls = []
    const watch = createVersionWatch({ running: 'a', onOutdated: () => calls.push('outdated') })

    watch.observe('a')
    assert.equal(watch.isOutdated(), false)
    watch.observe('b')
    watch.observe('c')
    watch.observe(undefined)

    assert.equal(watch.isOutdated(), true)
    assert.deepEqual(calls, ['outdated'])
  })
})

describe('throttle', () => {
  it('runs at most once per interval', () => {
    let clock = 1_000
    let runs = 0
    const check = throttle(() => runs++, { now: () => clock, intervalMs: 60_000 })

    assert.equal(check(), true)
    clock += 59_999
    assert.equal(check(), false)
    clock += 1
    assert.equal(check(), true)
    assert.equal(runs, 2)
  })

  it('runs the first time even at time zero', () => {
    let runs = 0
    const check = throttle(() => runs++, { now: () => 0, intervalMs: 60_000 })

    assert.equal(check(), true)
    assert.equal(runs, 1)
  })
})

describe('fetchDeployedVersion', () => {
  it('reads the version the file names, bypassing every cache', async () => {
    const requests = []
    const fetchVersion = async (url, init) => {
      requests.push([url, init])
      return { ok: true, json: async () => ({ version: '20261114150000000' }) }
    }

    assert.equal(
      await fetchDeployedVersion(fetchVersion, '/build/version.json'),
      '20261114150000000'
    )
    assert.deepEqual(requests, [['/build/version.json', { cache: 'no-store' }]])
  })

  it('reads nothing into a failed request or an unexpected body', async () => {
    const answers = [
      async () => ({ ok: false, json: async () => ({ version: 'x' }) }),
      async () => {
        throw new TypeError('Failed to fetch')
      },
      async () => ({
        ok: true,
        json: async () => {
          throw new SyntaxError('Unexpected token <')
        }
      }),
      async () => ({ ok: true, json: async () => ({ version: 12 }) })
    ]
    for (const fetchVersion of answers) {
      assert.equal(await fetchDeployedVersion(fetchVersion, '/build/version.json'), null)
    }
  })
})

describe('shouldLoadInFull', () => {
  const outdated = {
    isOutdated: true,
    isFirstNavigation: false,
    fromPath: '/band-space/1/notes',
    toPath: '/band-space/1/agenda',
    isReloadAsked: false
  }

  it('loads another page in full once a newer build is known', () => {
    assert.equal(shouldLoadInFull(outdated), true)
  })

  it('switches in place while the build is current', () => {
    assert.equal(shouldLoadInFull({ ...outdated, isOutdated: false }), false)
  })

  it('leaves the first navigation alone, the page load itself made it', () => {
    assert.equal(shouldLoadInFull({ ...outdated, isFirstNavigation: true }), false)
  })

  it('keeps a change of query or hash on the same page in place', () => {
    assert.equal(shouldLoadInFull({ ...outdated, toPath: outdated.fromPath }), false)
  })

  it('reloads the page shown when the member asks for it', () => {
    assert.equal(
      shouldLoadInFull({ ...outdated, toPath: outdated.fromPath, isReloadAsked: true }),
      true
    )
  })
})

describe('loadOutdatedPagesInFull', () => {
  const page = { render: () => null }

  /** A real router on /notes, with the full load installed and every assign recorded. */
  async function routerOnNotes({ isOutdated = true, at = '/notes' } = {}) {
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/notes', component: page },
        { path: '/agenda', component: page },
        { path: '/files', component: page }
      ]
    })
    const assigned = []
    const outdated = { value: false }
    const fullPageLoad = loadOutdatedPagesInFull(router, {
      isOutdated: () => outdated.value,
      assign: (url) => assigned.push(url)
    })
    await router.push(at)
    outdated.value = isOutdated

    return { router, assigned, fullPageLoad }
  }

  it('loads another page in full, instead of switching to it in place', async () => {
    const { router, assigned } = await routerOnNotes()

    await router.push('/agenda?view=month')

    assert.deepEqual(assigned, ['/agenda?view=month'])
    assert.equal(router.currentRoute.value.fullPath, '/notes')
  })

  it('loads nothing when a guard refuses the navigation, as a page with unsaved edits does', async () => {
    const { router, assigned } = await routerOnNotes()
    router.beforeEach(() => false)

    await router.push('/agenda')

    assert.deepEqual(assigned, [])
    assert.equal(router.currentRoute.value.fullPath, '/notes')
  })

  it('loads nothing for a navigation a newer one cancelled, only the newer one', async () => {
    const { router, assigned } = await routerOnNotes()
    // The member clicks Files while the Agenda navigation is still in its guards.
    let toFiles
    router.beforeEach((to) => {
      if (to.path !== '/agenda') return
      toFiles = router.push('/files')
      return toFiles.then(() => true)
    })

    await router.push('/agenda')
    await toFiles

    assert.deepEqual(assigned, ['/files'])
  })

  it('switches in place while the build is current', async () => {
    const { router, assigned } = await routerOnNotes({ isOutdated: false })

    await router.push('/agenda')

    assert.deepEqual(assigned, [])
    assert.equal(router.currentRoute.value.fullPath, '/agenda')
  })

  it('keeps a change of query on the same page in place', async () => {
    const { router, assigned } = await routerOnNotes()

    await router.push('/notes?note=42')

    assert.deepEqual(assigned, [])
    assert.equal(router.currentRoute.value.fullPath, '/notes?note=42')
  })

  it('reloads the page shown when the member asks for it', async () => {
    const { assigned, fullPageLoad } = await routerOnNotes({ at: '/notes?note=42#top' })

    await fullPageLoad.reload()

    assert.deepEqual(assigned, ['/notes?note=42#top'])
  })

  it('does not reload the page shown when a guard refuses, and forgets the request', async () => {
    const { router, assigned, fullPageLoad } = await routerOnNotes()
    const refuse = router.beforeEach(() => false)

    await fullPageLoad.reload()
    refuse()
    await router.push('/notes?note=42')

    assert.deepEqual(assigned, [])
  })
})
