import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { createMemoryHistory, createRouter, START_LOCATION } from 'vue-router'

// A page with English enabled. Set before the i18n modules load, since they read the attribute once.
globalThis.document = { documentElement: { lang: 'fr', dataset: { enabledLocales: 'fr,en' } } }
const { default: routes } = await import('../router/routes.js')
const { contentLanguageLocation, localeRedirect, localizedLocation } = await import(
  './localizedLocation.js'
)

// The real route table, so these run against the routes the router matches. resolve() loads no component.
const router = createRouter({ history: createMemoryHistory(), routes })
const resolve = (location) => router.resolve(location)

describe('localizedLocation', () => {
  it('finds the English version of a French page, query and hash included', () => {
    const route = resolve('/cours/category/guitare?page=2#top')

    assert.deepEqual(localizedLocation(route, 'en'), {
      path: '/en/lessons/category/guitare',
      query: { page: '2' },
      hash: '#top'
    })
  })

  it('finds the French version of an English page', () => {
    assert.equal(
      localizedLocation(resolve('/en/find-a-drummer'), 'fr').path,
      '/rechercher-un-batteur'
    )
    assert.equal(localizedLocation(resolve('/en'), 'fr').path, '/')
  })

  it('gives a publication its other prefix: a text in another language redirects from there', () => {
    assert.equal(
      localizedLocation(resolve('/publications/une-video'), 'en').path,
      '/en/publications/une-video'
    )
  })

  it('has no other version of a forum, a topic or a page without prefix', () => {
    for (const path of [
      '/forums/un-forum',
      '/en/forums/topic/un-sujet',
      '/login',
      '/band/1',
      '/user/settings'
    ]) {
      assert.equal(localizedLocation(resolve(path), 'en'), null, path)
    }
  })

  it('has no version in a locale that is not enabled', () => {
    assert.equal(localizedLocation(resolve('/publications'), 'de'), null)
  })
})

describe('contentLanguageLocation', () => {
  // The content of the page still shown: its load started on the path the route is on.
  const loadedOn = (path, contentLocale) => {
    const route = resolve(path)

    return contentLanguageLocation(route, contentLocale, route.path)
  }

  it('sends French content shown under /en to its French URL', () => {
    assert.deepEqual(loadedOn('/en/forums/topic/un-sujet/2#post-4', 'fr'), {
      path: '/forums/topic/un-sujet/2',
      query: {},
      hash: '#post-4'
    })
  })

  it('sends English content shown without prefix to /en', () => {
    assert.equal(loadedOn('/publications/an-article', 'en').path, '/en/publications/an-article')
  })

  it('leaves content already under its language, or shown in every language, where it is', () => {
    assert.equal(loadedOn('/en/publications/an-article', 'en'), null)
    assert.equal(loadedOn('/en/publications/une-video', null), null)
    assert.equal(loadedOn('/publications/ein-artikel', 'de'), null)
  })

  it('leaves alone a visitor who has moved on while the content loaded', () => {
    // An English text whose answer arrives once the visitor is on another publication.
    const route = resolve('/publications/autre-article')

    assert.equal(contentLanguageLocation(route, 'en', '/publications/an-article'), null)
  })
})

describe('localeRedirect', () => {
  const redirect = (to, from, activeLocale) =>
    localeRedirect(resolve(to), from === START_LOCATION ? from : resolve(from), activeLocale)

  it('lets the URL decide on arrival', () => {
    assert.equal(redirect('/publications', START_LOCATION, 'en'), null)
    assert.equal(redirect('/en/publications', START_LOCATION, 'fr'), null)
  })

  it('sends an upper case prefix to the lower case one', () => {
    assert.equal(redirect('/EN/publications', START_LOCATION, 'fr').path, '/en/publications')
  })

  it('corrects a landing page path to the locale it is shown in', () => {
    assert.deepEqual(redirect('/en/cours?page=2', START_LOCATION, 'fr'), {
      path: '/en/lessons',
      query: { page: '2' },
      hash: ''
    })
    assert.equal(redirect('/lessons', START_LOCATION, 'fr').path, '/cours')
  })

  it('follows the active locale for a link from a page without prefix', () => {
    // English chosen on a Band Space page: a `{ name }` link out of it resolves to French.
    assert.equal(redirect('/publications', '/band/1', 'en').path, '/en/publications')
    assert.equal(
      localeRedirect(resolve({ name: 'app_home' }), resolve('/user/settings'), 'en').path,
      '/en'
    )
    assert.equal(redirect('/publications', '/band/1', 'fr'), null)
  })

  it('follows the active locale for a link within one language', () => {
    // English chosen on a French forum, which has no English version.
    assert.equal(redirect('/forums', '/forums/un-forum', 'en').path, '/en/forums')
  })

  it('lets a navigation that changes language win over the active locale', () => {
    // The switcher, a link to a translation, the redirect to the content's language.
    assert.equal(redirect('/en/publications', '/publications', 'fr'), null)
    assert.equal(redirect('/publications/un-article', '/en/publications/un-article', 'en'), null)
  })

  it('leaves a forum and a topic under the prefix of the link that leads there', () => {
    // Their page moves to its content's language once it knows it.
    assert.equal(redirect('/forums/topic/un-sujet', '/forums/my-topics', 'en'), null)
  })

  it('leaves the pages without prefix alone', () => {
    assert.equal(redirect('/user/settings', '/en/publications', 'en'), null)
  })
})
