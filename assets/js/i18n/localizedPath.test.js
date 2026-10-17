import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { describe, it } from 'node:test'
// Vue first: runtime-dom reads `document` as it loads, and the stub below is no DOM.
import './index.js'

// A page with English enabled: translated segments only exist for an enabled locale. Set before the module
// loads, since it reads the attribute once.
globalThis.document = { documentElement: { lang: 'fr', dataset: { enabledLocales: 'fr,en' } } }
const { isLocalizedRoute, LOCALE_PARAM_PATH, localeOfPath, pathInLocale } = await import(
  './localizedPath.js'
)

// Shared with the server's LocalizedPathTest, so the router and the hreflang links agree on every URL.
const cases = JSON.parse(
  readFileSync(new URL('../../../tests/Fixtures/Locale/localized_paths.json', import.meta.url))
)

describe('localeOfPath', () => {
  for (const { path, locale } of cases.locale_of) {
    it(`reads ${path} as ${locale}`, () => {
      assert.equal(localeOfPath(path), locale)
    })
  }
})

describe('pathInLocale', () => {
  for (const { path, locale, expected } of cases.in_locale) {
    it(`puts ${path} in ${locale} as ${expected}`, () => {
      assert.equal(pathInLocale(path, locale), expected)
    })
  }

  it('drops a trailing slash', () => {
    assert.equal(pathInLocale('/publications/', 'en'), '/en/publications')
  })

  it('reads a prefix whatever its case and writes it in lower case', () => {
    // The router matches /EN/... too: the guard sends it to /en/...
    assert.equal(localeOfPath('/EN/publications'), 'en')
    assert.equal(pathInLocale('/EN/publications', 'en'), '/en/publications')
    assert.equal(pathInLocale('/En/lessons', 'fr'), '/cours')
  })
})

describe('isLocalizedRoute', () => {
  it('is a route under the locale param', () => {
    const route = { matched: [{ path: '/' }, { path: `/${LOCALE_PARAM_PATH}/publications` }] }

    assert.equal(isLocalizedRoute(route), true)
  })

  it('is not a route without prefix', () => {
    assert.equal(isLocalizedRoute({ matched: [{ path: '/' }, { path: '/login' }] }), false)
  })

  it('is not the start of navigation, which matches nothing', () => {
    assert.equal(isLocalizedRoute({ matched: [] }), false)
  })
})
