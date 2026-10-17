import assert from 'node:assert/strict'
import { afterEach, describe, it } from 'node:test'
import { createRenderer, h } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'
import { i18n } from './index.js'
import { localizedPage } from './localizedPage.js'

function version(text) {
  return { default: { render: () => h('p', text) } }
}

// Loaders that record which version was downloaded. The English one can be swapped for a download
// the test settles itself.
function pageVersions({ en = () => Promise.resolve(version('Hello')) } = {}) {
  const loaded = []
  const recorded = (locale, load) => () => {
    loaded.push(locale)

    return load()
  }

  return {
    loaded,
    loaders: {
      fr: recorded('fr', () => Promise.resolve(version('Bonjour'))),
      en: recorded('en', en)
    }
  }
}

function pendingDownload() {
  let resolve
  const download = new Promise((settle) => {
    resolve = settle
  })

  return { load: () => download, resolve }
}

// Unmounted after each test, or an open page would keep reacting to the next tests' locale switches.
const mountedApps = []

// Just enough of a DOM for Vue to mount into once and keep patching; reads back as the page's text.
function mountInMemory(component) {
  const node = (fields) => ({ children: [], parent: null, text: '', ...fields })
  function remove(child) {
    if (!child.parent) return
    child.parent.children.splice(child.parent.children.indexOf(child), 1)
    child.parent = null
  }
  const { createApp } = createRenderer({
    createElement: (tag) => node({ tag }),
    createText: (text) => node({ text }),
    createComment: () => node({ comment: true }),
    setText: (target, text) => {
      target.text = text
    },
    setElementText: (element, text) => {
      element.children = text ? [node({ text, parent: element })] : []
    },
    insert: (child, parent, anchor = null) => {
      remove(child)
      const index = anchor ? parent.children.indexOf(anchor) : parent.children.length
      parent.children.splice(index, 0, child)
      child.parent = parent
    },
    remove,
    parentNode: (child) => child.parent,
    nextSibling: (child) =>
      child.parent?.children[child.parent.children.indexOf(child) + 1] ?? null,
    patchProp: () => {}
  })
  const root = node({ tag: 'root' })
  const app = createApp(component)
  app.mount(root)
  mountedApps.push(app)
  const textOf = (target) =>
    target.comment ? '' : target.text + target.children.map(textOf).join('')

  return () => textOf(root)
}

async function openPage(page) {
  await page.beforeRouteEnter()

  return mountInMemory(page)
}

const settled = () => new Promise((resolve) => setImmediate(resolve))

describe('localizedPage', () => {
  afterEach(() => {
    for (const app of mountedApps.splice(0)) app.unmount()
    i18n.global.locale.value = 'fr'
  })

  it('shows the French version without downloading the English one', async () => {
    const { loaded, loaders } = pageVersions()

    assert.equal((await openPage(localizedPage(loaders)))(), 'Bonjour')
    assert.deepEqual(loaded, ['fr'])
  })

  it('shows the version of the active locale', async () => {
    const { loaded, loaders } = pageVersions()
    i18n.global.locale.value = 'en'

    assert.equal((await openPage(localizedPage(loaders)))(), 'Hello')
    assert.deepEqual(loaded, ['en'])
  })

  it('falls back on French for a locale with no version', async () => {
    const { loaded, loaders } = pageVersions()
    i18n.global.locale.value = 'de'

    assert.equal((await openPage(localizedPage(loaders)))(), 'Bonjour')
    assert.deepEqual(loaded, ['fr'])
  })

  it('keeps showing the open version until the other locale has loaded, then swaps it in', async () => {
    const english = pendingDownload()
    const { loaded, loaders } = pageVersions({ en: english.load })
    const pageText = await openPage(localizedPage(loaders))

    i18n.global.locale.value = 'en'
    await settled()
    assert.equal(pageText(), 'Bonjour')

    english.resolve(version('Hello'))
    await settled()
    assert.equal(pageText(), 'Hello')
    assert.deepEqual(loaded, ['fr', 'en'])
  })

  it('downloads its version when mounted in a locale it has not loaded, as a language switch remounts it', async () => {
    const english = pendingDownload()
    const { loaded, loaders } = pageVersions({ en: english.load })
    const page = localizedPage(loaders)
    await page.beforeRouteEnter()

    i18n.global.locale.value = 'en'
    const pageText = mountInMemory(page)
    english.resolve(version('Hello'))
    await settled()

    assert.equal(pageText(), 'Hello')
    assert.deepEqual(loaded, ['fr', 'en'])
  })

  it('keeps the open version when the other locale fails to download', async (t) => {
    const logged = t.mock.method(console, 'error', () => {})
    const { loaders } = pageVersions({ en: () => Promise.reject(new Error('chunk failed')) })
    const pageText = await openPage(localizedPage(loaders))

    i18n.global.locale.value = 'en'
    await settled()

    assert.equal(pageText(), 'Bonjour')
    assert.equal(logged.mock.callCount(), 1)
  })
})

describe('localizedPage as a route component', () => {
  afterEach(() => {
    i18n.global.locale.value = 'fr'
  })

  function routerTo(page) {
    return createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/', component: { render: () => null } },
        { path: '/terms', component: page }
      ]
    })
  }

  it('downloads the active version on every visit, not only the first', async () => {
    const { loaded, loaders } = pageVersions()
    const router = routerTo(localizedPage(loaders))

    await router.push('/terms')
    await router.push('/')
    i18n.global.locale.value = 'en'
    await router.push('/terms#recours')

    assert.deepEqual(loaded, ['fr', 'en'])
  })

  it('rejects the navigation when the version fails to download', async () => {
    const failure = new Error('chunk failed')
    const router = routerTo(localizedPage({ fr: () => Promise.reject(failure) }))
    const errors = []
    router.onError((error) => errors.push(error))

    await assert.rejects(router.push('/terms'), failure)
    assert.deepEqual(errors, [failure])
  })
})
