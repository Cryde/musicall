import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { describe, it } from 'node:test'
import { createMemoryHistory, createRouter } from 'vue-router'

// A page with English enabled, which the English paths need. Set before the i18n modules load, since they read
// the attribute once.
globalThis.document = { documentElement: { lang: 'fr', dataset: { enabledLocales: 'fr,en' } } }
const { isLocalizedRoute } = await import('../i18n/localizedPath.js')
const { withLocalizedHrefs } = await import('./localizedRoutes.js')
const { default: routes } = await import('./routes.js')

// Shared with the server's PageVersionsResolverTest, so the pages the router localizes are the ones the server
// links with hreflang.
const { pages } = JSON.parse(
  readFileSync(new URL('../../../tests/Fixtures/Locale/localized_paths.json', import.meta.url))
)

// The real route table. resolve() loads no component, so the views are never imported.
const router = withLocalizedHrefs(createRouter({ history: createMemoryHistory(), routes }))
const resolve = (location, from) => router.resolve(location, from && router.resolve(from))

function paramsFor(path) {
  const names = [...String(path).matchAll(/:(\w+)/g)].map(([, name]) => name)

  return Object.fromEntries(names.filter((name) => name !== 'locale').map((name) => [name, 'x']))
}

describe('the route table', () => {
  it('keeps every French URL on the page it showed', () => {
    // `/` is the layout's redirect to app_home, pinned below.
    const pages = {
      '/decouvrir': 'app_discover',
      '/band-space': 'app_band_space_presentation',
      '/publications': 'app_publications',
      '/publications/category/news': 'app_publications_by_category',
      '/publications/tag/metal': 'app_publication_tag',
      '/publications/un-article': 'app_publication_show',
      '/photos/une-galerie': 'app_gallery_show',
      '/cours': 'app_course',
      '/cours/category/guitare': 'app_course_by_category',
      '/cours/un-cours': 'app_course_show',
      '/forums': 'app_forum_index',
      '/forums/my-topics': 'app_forum_my_topics',
      '/forums/un-forum': 'forum_topic_list',
      '/forums/topic/un-sujet/2': 'forum_topic_item',
      '/rechercher-un-musicien': 'app_search_musician',
      '/rechercher-un-guitariste': 'app_search_guitarist',
      '/u/jeanne/musician': 'app_user_musician_profile',
      '/mentions-legales': 'app_mentions_legales',
      '/supprimer-mon-compte': 'app_account_deletion',
      '/login': 'app_login',
      '/user/settings/password': 'app_user_settings_password',
      '/shares/abc': 'app_public_share',
      '/admin/users': 'admin_users_dashboard',
      '/band/1/agenda': 'app_band_agenda',
      '/nowhere': 'not_found'
    }

    for (const [path, name] of Object.entries(pages)) {
      assert.equal(resolve(path).name, name, path)
    }
  })

  it('serves the same pages under /en, with English paths for the landing pages', () => {
    const pages = {
      '/en': 'app_home',
      '/en/discover': 'app_discover',
      '/en/publications/category/news': 'app_publications_by_category',
      '/en/lessons': 'app_course',
      '/en/lessons/category/guitare': 'app_course_by_category',
      '/en/lessons/un-cours': 'app_course_show',
      '/en/forums/topic/un-sujet': 'forum_topic_item',
      '/en/find-a-guitarist': 'app_search_guitarist',
      '/en/find-a-teacher': 'app_search_teacher',
      '/en/u/jeanne': 'app_user_public_profile',
      '/en/legal-notice': 'app_mentions_legales',
      '/en/delete-my-account': 'app_account_deletion',
      '/en/privacy': 'app_privacy',
      '/en/nowhere': 'not_found'
    }

    for (const [path, name] of Object.entries(pages)) {
      const route = resolve(path)
      assert.equal(route.name, name, path)
      assert.equal(route.params.locale, 'en', path)
    }
  })

  it('gives the sign-in flow and a member own pages no /en version', () => {
    for (const path of [
      '/en/login',
      '/en/user/settings',
      '/en/messages',
      '/en/admin',
      '/en/shares/abc'
    ]) {
      assert.equal(resolve(path).name, 'not_found', path)
    }
  })

  it('sends /en/forums/my-topics to the member page, not to a forum named my-topics', () => {
    const route = resolve('/en/forums/my-topics')
    const target = router.resolve(route.matched.at(-1).redirect, route)

    assert.deepEqual([target.name, target.path], ['app_forum_my_topics', '/forums/my-topics'])
  })

  it('renders a named link to a landing page with the path of the locale it inherits', () => {
    // What RouterLink puts in href, so what a crawler follows.
    assert.equal(resolve({ name: 'app_course' }, '/en/forums').href, '/en/lessons')
    assert.equal(
      resolve({ name: 'app_course_by_category', params: { slug: 'piano' } }, '/en').href,
      '/en/lessons/category/piano'
    )
    assert.equal(
      resolve({ name: 'app_search_guitarist' }, '/en/forums').href,
      '/en/find-a-guitarist'
    )
    assert.equal(
      resolve({ name: 'app_search_guitarist' }, '/en/forums').name,
      'app_search_guitarist'
    )
    assert.equal(resolve({ name: 'app_course' }, '/forums').href, '/cours')
  })

  it('localizes the pages the server gives a version in every locale, and only those', () => {
    // `/` is the layout's redirect, followed here as a navigation would.
    const shown = (path) => {
      const route = resolve(path)
      const redirect = route.matched.at(-1)?.redirect

      return redirect ? router.resolve(redirect) : route
    }

    for (const path of pages.in_every_locale) {
      assert.equal(isLocalizedRoute(shown(path)), true, path)
    }
    for (const path of pages.without_versions) {
      assert.equal(isLocalizedRoute(shown(path)), false, path)
    }
  })

  it('splits the pages between localized and unprefixed as #1192 lists them', () => {
    const named = router.getRoutes().filter((record) => record.name && !record.aliasOf)
    const localized = named
      .filter((record) =>
        isLocalizedRoute(resolve({ name: record.name, params: paramsFor(record.path) }))
      )
      .map((record) => record.name)
      .sort()

    assert.deepEqual(localized, [
      'app_account_deletion',
      'app_band_space_presentation',
      'app_contact',
      'app_course',
      'app_course_by_category',
      'app_course_show',
      'app_discover',
      'app_forum_index',
      'app_gallery_show',
      'app_home',
      'app_mentions_legales',
      'app_privacy',
      'app_publication_show',
      'app_publication_tag',
      'app_publications',
      'app_publications_by_category',
      'app_search_bassist',
      'app_search_drummer',
      'app_search_guitarist',
      'app_search_musician',
      'app_search_pianist',
      'app_search_singer',
      'app_search_teacher',
      'app_terms',
      'app_user_musician_profile',
      'app_user_public_profile',
      'app_user_teacher_profile',
      'forum_topic_item',
      'forum_topic_list',
      'not_found'
    ])
  })

  it('has a named link inherit the locale of the page it is on', () => {
    assert.equal(resolve({ name: 'app_publications' }, '/en/forums').path, '/en/publications')
    assert.equal(resolve({ name: 'app_home' }, '/en/forums').path, '/en')
    assert.equal(resolve({ name: 'app_publications' }, '/forums').path, '/publications')
    assert.equal(resolve({ name: 'app_login' }, '/en/forums').path, '/login')
  })

  it('takes `/` to the home page, never to the bare layout', () => {
    // resolve() does not follow redirects. The layout's names the locale, so `/` reached by Back from an
    // English page stays French.
    const layout = resolve('/')
    const home = router.resolve(layout.matched.at(-1).redirect, resolve('/en/forums'))

    assert.equal(layout.name, 'app_layout')
    assert.deepEqual([home.name, home.path], ['app_home', '/'])
  })
})
