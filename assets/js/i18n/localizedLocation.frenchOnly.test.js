import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { createMemoryHistory, createRouter, START_LOCATION } from 'vue-router'

// Production until #1194: the page names no enabled locales, so French is the only one. A file of its own,
// since the list is read once, when the module loads.
globalThis.document = { documentElement: { lang: 'fr', dataset: {} } }
const { default: routes } = await import('../router/routes.js')
const { contentLanguageLocation, localeRedirect, localizedLocation } = await import(
  './localizedLocation.js'
)

const router = createRouter({ history: createMemoryHistory(), routes })

describe('with French as the only enabled locale', () => {
  it('sends an /en page to its French version', () => {
    assert.deepEqual(
      localeRedirect(router.resolve('/en/publications?page=2'), START_LOCATION, 'fr'),
      {
        path: '/publications',
        query: { page: '2' },
        hash: ''
      }
    )
  })

  it('has no English landing paths', () => {
    assert.equal(router.resolve('/lessons').name, 'not_found')
    assert.equal(router.resolve('/find-a-guitarist').name, 'not_found')
    assert.equal(localeRedirect(router.resolve('/lessons'), START_LOCATION, 'fr'), null)
  })

  it('leaves the French pages as they are', () => {
    assert.equal(localeRedirect(router.resolve('/cours'), START_LOCATION, 'fr'), null)
  })

  it('offers no English version and never moves English content', () => {
    assert.equal(localizedLocation(router.resolve('/cours'), 'en'), null)
    const article = router.resolve('/publications/an-article')
    assert.equal(contentLanguageLocation(article, 'en', article.path), null)
  })
})
