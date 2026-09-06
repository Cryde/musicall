import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { createMemoryHistory, createRouter } from 'vue-router'
import adminRoute from './admin.js'

/**
 * #940 was not a missing check, it was a check that could never fire: the admin parent declared
 * `isAdminRequired` and the guard in App.vue read `isAuthRequired` only, so every logged-in account
 * rendered the back office.
 *
 * Guarding the parent is worth nothing if `meta` does not reach the children, and "the page loaded
 * for an admin" cannot tell the two apart: an inherited flag that passes and a flag that never
 * arrived look identical from the browser. So this resolves the real route table instead.
 */
const router = createRouter({
  history: createMemoryHistory(),
  routes: [{ path: '/', children: [adminRoute] }]
})

/**
 * A child with a required param cannot be resolved by name alone, so each one gets a placeholder.
 * Derived from the path rather than listed, so a future admin route with params needs no change
 * here.
 */
function paramsFor(path) {
  const names = [...String(path).matchAll(/:(\w+)/g)].map(([, name]) => name)

  return Object.fromEntries(names.map((name) => [name, 'placeholder']))
}

const adminPages = adminRoute.children.map((child) => ({
  name: child.name,
  params: paramsFor(child.path)
}))

describe('the admin route tree', () => {
  it('has children to protect', () => {
    assert.ok(adminPages.length > 0)
  })

  it('requires admin on every page, not just the index', () => {
    for (const page of adminPages) {
      assert.equal(
        router.resolve(page).meta.isAdminRequired,
        true,
        `${page.name} does not inherit isAdminRequired, so the guard never fires for it`
      )
    }
  })

  it('still requires authentication on every page', () => {
    for (const page of adminPages) {
      assert.equal(
        router.resolve(page).meta.isAuthRequired,
        true,
        `${page.name} lost isAuthRequired`
      )
    }
  })

  it('puts every admin page under /admin', () => {
    for (const page of adminPages) {
      assert.match(router.resolve(page).path, /^\/admin(\/|$)/, `${page.name} escaped the prefix`)
    }
  })
})
