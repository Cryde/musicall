import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  arrivalPlan,
  endonym,
  explicitLocale,
  followArrivalPlan,
  hasChosenLocale,
  LOCALE_COOKIE,
  localeNamedByPath,
  readLocaleCookie,
  saveLocaleCookie,
  showInLocale,
  suggestedLocale,
  switchLocale
} from './localeChoice.js'
import { LOCALE_SUGGESTIONS } from './localeSuggestion.js'
import { punctuationViolations } from './plainPunctuation.js'

const BILINGUAL = ['fr', 'en']
const FRENCH_ONLY = ['fr']

function fakeCookies(jar = {}) {
  const calls = []

  return {
    calls,
    get: (name) => jar[name],
    set: (...args) => calls.push(args)
  }
}

// A router on `route`, recording where it is sent, and a setLocale recording what it is asked for.
function fakeNavigation(route = { name: 'app_publications', path: '/publications' }) {
  const calls = []

  return {
    calls,
    router: {
      currentRoute: { value: route },
      push: async (target) => calls.push(['push', target]),
      replace: async (target) => calls.push(['replace', target])
    },
    setLocale: async (locale) => calls.push(['setLocale', locale])
  }
}

describe('the locale cookie', () => {
  it('is the one the server reads', () => {
    assert.equal(LOCALE_COOKIE, 'locale')
  })

  it('is written for the whole site, for a year, sent on top level navigations', () => {
    const cookies = fakeCookies()

    saveLocaleCookie('en', cookies)

    assert.deepEqual(cookies.calls, [
      ['locale', 'en', { expires: 365, path: '/', sameSite: 'lax' }]
    ])
  })

  it('reads the stored choice', () => {
    assert.equal(readLocaleCookie(fakeCookies({ locale: 'en' })), 'en')
  })

  it('reads null when there is none', () => {
    assert.equal(readLocaleCookie(fakeCookies()), null)
  })
})

describe('endonym', () => {
  it('names each language in that language, capitalised as a label', () => {
    assert.deepEqual(['fr', 'en', 'de'].map(endonym), ['Français', 'English', 'Deutsch'])
  })
})

describe('explicitLocale', () => {
  it('prefers the account over the cookie, as the server does', () => {
    assert.equal(
      explicitLocale({ accountLocale: 'fr', cookieLocale: 'en', enabledLocales: BILINGUAL }),
      'fr'
    )
  })

  it('reads the cookie when nobody is signed in', () => {
    assert.equal(explicitLocale({ cookieLocale: 'en', enabledLocales: BILINGUAL }), 'en')
  })

  it('skips a value that is not an enabled locale', () => {
    assert.equal(
      explicitLocale({ accountLocale: 'en', cookieLocale: 'fr', enabledLocales: FRENCH_ONLY }),
      'fr'
    )
    assert.equal(explicitLocale({ cookieLocale: 'de', enabledLocales: BILINGUAL }), null)
  })

  it('is null without a choice', () => {
    assert.equal(explicitLocale({ enabledLocales: BILINGUAL }), null)
  })
})

describe('hasChosenLocale', () => {
  const visitor = {
    isAuthLoading: false,
    isSignedIn: false,
    cookieLocale: null,
    enabledLocales: BILINGUAL
  }

  it('is false for a visitor who never chose', () => {
    assert.equal(hasChosenLocale(visitor), false)
  })

  it('is true once the cookie holds an enabled locale', () => {
    assert.equal(hasChosenLocale({ ...visitor, cookieLocale: 'fr' }), true)
  })

  it('ignores a cookie naming a locale that is not enabled', () => {
    assert.equal(hasChosenLocale({ ...visitor, cookieLocale: 'de' }), false)
  })

  it('is true for a member, whose account always has a language', () => {
    assert.equal(hasChosenLocale({ ...visitor, isSignedIn: true }), true)
  })

  it('is true while the auth check has not answered', () => {
    assert.equal(hasChosenLocale({ ...visitor, isAuthLoading: true }), true)
  })
})

describe('localeNamedByPath', () => {
  const locales = { enabledLocales: BILINGUAL, defaultLocale: 'fr' }

  it('reads the locale prefix', () => {
    assert.equal(localeNamedByPath('/en/publications', locales), 'en')
    assert.equal(localeNamedByPath('/en', locales), 'en')
  })

  it('names none for an unprefixed URL', () => {
    assert.equal(localeNamedByPath('/publications', locales), null)
    assert.equal(localeNamedByPath('/', locales), null)
  })

  it('names none for the default locale, which has no prefix', () => {
    assert.equal(localeNamedByPath('/fr/publications', locales), null)
  })

  it('only reads a whole segment that is an enabled locale', () => {
    assert.equal(localeNamedByPath('/english', locales), null)
    assert.equal(localeNamedByPath('/de/publications', locales), null)
    assert.equal(
      localeNamedByPath('/en/publications', { enabledLocales: FRENCH_ONLY, defaultLocale: 'fr' }),
      null
    )
  })
})

describe('arrivalPlan', () => {
  it('switches a French page to the language the cookie chose', () => {
    assert.deepEqual(
      arrivalPlan({ pageLocale: 'fr', cookieLocale: 'en', enabledLocales: BILINGUAL }),
      { locale: 'en', cookie: null }
    )
  })

  it('switches to the account language and copies it into the cookie', () => {
    assert.deepEqual(
      arrivalPlan({ pageLocale: 'fr', accountLocale: 'en', enabledLocales: BILINGUAL }),
      { locale: 'en', cookie: 'en' }
    )
  })

  it('lets the account win over a cookie left by another choice', () => {
    assert.deepEqual(
      arrivalPlan({
        pageLocale: 'en',
        accountLocale: 'fr',
        cookieLocale: 'en',
        enabledLocales: BILINGUAL
      }),
      { locale: 'fr', cookie: 'fr' }
    )
  })

  it('only fixes the cookie when the page is already in the account language', () => {
    assert.deepEqual(
      arrivalPlan({ pageLocale: 'en', accountLocale: 'en', enabledLocales: BILINGUAL }),
      { locale: null, cookie: 'en' }
    )
  })

  it('does nothing when the page, the account and the cookie agree', () => {
    assert.deepEqual(
      arrivalPlan({
        pageLocale: 'en',
        accountLocale: 'en',
        cookieLocale: 'en',
        enabledLocales: BILINGUAL
      }),
      { locale: null, cookie: null }
    )
  })

  it('moves nothing without a choice, whatever language the page is in', () => {
    assert.deepEqual(arrivalPlan({ pageLocale: 'en', enabledLocales: BILINGUAL }), {
      locale: null,
      cookie: null
    })
  })

  it('ignores a choice of a locale that is not enabled', () => {
    assert.deepEqual(
      arrivalPlan({ pageLocale: 'fr', cookieLocale: 'de', enabledLocales: BILINGUAL }),
      { locale: null, cookie: null }
    )
  })

  it('never moves a visitor off a URL that names a locale', () => {
    assert.deepEqual(
      arrivalPlan({
        pageLocale: 'en',
        urlLocale: 'en',
        cookieLocale: 'fr',
        enabledLocales: BILINGUAL
      }),
      { locale: null, cookie: null }
    )
  })

  it('keeps a member on a shared English link, while still fixing the cookie', () => {
    assert.deepEqual(
      arrivalPlan({
        pageLocale: 'en',
        urlLocale: 'en',
        accountLocale: 'fr',
        enabledLocales: BILINGUAL
      }),
      { locale: null, cookie: 'fr' }
    )
  })

  // Production until #1194: the page stays French and no cookie is written, whatever the account says.
  it('changes nothing while French is the only enabled locale', () => {
    assert.deepEqual(
      arrivalPlan({
        pageLocale: 'fr',
        accountLocale: 'en',
        cookieLocale: 'en',
        enabledLocales: FRENCH_ONLY
      }),
      { locale: null, cookie: null }
    )
    assert.deepEqual(
      arrivalPlan({ pageLocale: 'fr', accountLocale: 'fr', enabledLocales: FRENCH_ONLY }),
      { locale: null, cookie: null }
    )
  })
})

describe('switchLocale', () => {
  const englishVersion = { name: 'app_publications', params: { locale: 'en' } }

  // One log for the saves and the navigation, so the tests can check their order.
  function recordingSwitch() {
    const navigation = fakeNavigation()

    return {
      calls: navigation.calls,
      saveAccountLocale: async (locale) => navigation.calls.push(['account', locale]),
      saveCookie: (locale) => navigation.calls.push(['cookie', locale]),
      router: navigation.router,
      setLocale: navigation.setLocale
    }
  }

  it('saves on the account, then in the cookie, then shows the page in that language', async () => {
    const { calls, ...dependencies } = recordingSwitch()

    await switchLocale('en', { ...dependencies, locate: () => englishVersion })

    assert.deepEqual(calls, [
      ['account', 'en'],
      ['cookie', 'en'],
      ['push', englishVersion]
    ])
  })

  it('only writes the cookie for an anonymous visitor', async () => {
    const { calls, saveAccountLocale: _, ...dependencies } = recordingSwitch()

    await switchLocale('en', { ...dependencies, locate: () => null })

    assert.deepEqual(calls, [
      ['cookie', 'en'],
      ['setLocale', 'en']
    ])
  })

  it('changes nothing when the account refuses the change', async () => {
    const { calls, ...dependencies } = recordingSwitch()
    const refusal = new Error('refused')

    await assert.rejects(
      switchLocale('en', {
        ...dependencies,
        saveAccountLocale: async () => {
          throw refusal
        },
        locate: () => null
      }),
      refusal
    )
    assert.deepEqual(calls, [])
  })
})

describe('followArrivalPlan', () => {
  const englishVersion = { name: 'app_publications', params: { locale: 'en' } }

  function recordingArrival() {
    const navigation = fakeNavigation()

    return {
      calls: navigation.calls,
      saveCookie: (locale) => navigation.calls.push(['cookie', locale]),
      router: navigation.router,
      setLocale: navigation.setLocale
    }
  }

  it('fixes the cookie, then replaces the location with the chosen language', async () => {
    const { calls, ...dependencies } = recordingArrival()

    await followArrivalPlan(
      { locale: 'en', cookie: 'en' },
      { ...dependencies, locate: () => englishVersion }
    )

    assert.deepEqual(calls, [
      ['cookie', 'en'],
      ['replace', englishVersion]
    ])
  })

  it('switches in place a page with no version in the chosen language', async () => {
    const { calls, ...dependencies } = recordingArrival()

    await followArrivalPlan({ locale: 'en', cookie: null }, { ...dependencies, locate: () => null })

    assert.deepEqual(calls, [['setLocale', 'en']])
  })

  it('only writes the cookie when the page is already in the right language', async () => {
    const { calls, ...dependencies } = recordingArrival()

    await followArrivalPlan({ locale: null, cookie: 'fr' }, dependencies)

    assert.deepEqual(calls, [['cookie', 'fr']])
  })

  it('does nothing for an empty plan', async () => {
    const { calls, ...dependencies } = recordingArrival()

    await followArrivalPlan({ locale: null, cookie: null }, dependencies)

    assert.deepEqual(calls, [])
  })
})

describe('showInLocale', () => {
  const englishVersion = { name: 'app_publications', params: { locale: 'en' } }

  it('switches the interface in place when the page has no version in that language', async () => {
    const navigation = fakeNavigation()

    await showInLocale('en', { ...navigation, locate: () => null })

    assert.deepEqual(navigation.calls, [['setLocale', 'en']])
  })

  it('opens the version in that language, leaving the locale to its URL', async () => {
    const navigation = fakeNavigation()

    await showInLocale('en', { ...navigation, locate: () => englishVersion })

    assert.deepEqual(navigation.calls, [['push', englishVersion]])
  })

  it('replaces the location on arrival, so going back does not undo the choice', async () => {
    const navigation = fakeNavigation()

    await showInLocale('en', { ...navigation, replace: true, locate: () => englishVersion })

    assert.deepEqual(navigation.calls, [['replace', englishVersion]])
  })

  it('asks for the version of the current page in that language', async () => {
    const route = { name: 'app_forum_index', path: '/forums' }
    const navigation = fakeNavigation(route)
    const asked = []

    await showInLocale('en', {
      ...navigation,
      locate: (...args) => {
        asked.push(args)
        return null
      }
    })

    assert.deepEqual(asked, [[route, 'en']])
  })
})

describe('suggestedLocale', () => {
  const firstVisit = {
    browserLanguage: 'en-GB',
    pageLocale: 'fr',
    enabledLocales: BILINGUAL,
    hasExplicitChoice: false
  }

  it('offers English to an English browser reading a French page', () => {
    assert.equal(suggestedLocale(firstVisit), 'en')
  })

  it('reads the language whatever its region and case', () => {
    assert.equal(suggestedLocale({ ...firstVisit, browserLanguage: 'EN' }), 'en')
    assert.equal(suggestedLocale({ ...firstVisit, browserLanguage: 'en-US' }), 'en')
  })

  it('offers nothing over a choice already made', () => {
    assert.equal(suggestedLocale({ ...firstVisit, hasExplicitChoice: true }), null)
  })

  it('offers nothing once dismissed', () => {
    assert.equal(suggestedLocale({ ...firstVisit, dismissedLocale: 'en' }), null)
  })

  it('offers nothing when the page is already in the browser language', () => {
    assert.equal(suggestedLocale({ ...firstVisit, pageLocale: 'en' }), null)
  })

  it('offers nothing to a French browser', () => {
    assert.equal(suggestedLocale({ ...firstVisit, browserLanguage: 'fr-FR' }), null)
  })

  it('offers nothing for a language that is not enabled', () => {
    assert.equal(suggestedLocale({ ...firstVisit, enabledLocales: FRENCH_ONLY }), null)
    assert.equal(suggestedLocale({ ...firstVisit, browserLanguage: 'de-DE' }), null)
  })

  it('offers nothing for an enabled language the suggestion is not written in', () => {
    assert.equal(
      suggestedLocale({
        ...firstVisit,
        browserLanguage: 'de',
        enabledLocales: ['fr', 'en', 'de']
      }),
      null
    )
  })

  it('offers nothing when the browser does not say', () => {
    assert.equal(suggestedLocale({ ...firstVisit, browserLanguage: undefined }), null)
    assert.equal(suggestedLocale({ ...firstVisit, browserLanguage: '' }), null)
  })

  it('has its suggestion written for every locale it can offer', () => {
    for (const messages of Object.values(LOCALE_SUGGESTIONS)) {
      assert.deepEqual(Object.keys(messages).sort(), ['accept', 'dismiss', 'label', 'message'])
    }
  })

  it('writes its suggestions with plain punctuation', () => {
    const violations = Object.entries(LOCALE_SUGGESTIONS).flatMap(([locale, messages]) =>
      Object.entries(messages).flatMap(([key, message]) =>
        punctuationViolations(message).map((violation) => `${locale}.${key}: ${violation}`)
      )
    )

    assert.deepEqual(violations, [])
  })
})
