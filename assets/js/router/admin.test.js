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

const adminRouteNames = adminRoute.children.map((child) => child.name)

describe('the admin route tree', () => {
  it('has children to protect', () => {
    assert.ok(adminRouteNames.length > 0)
  })

  it('requires admin on every page, not just the index', () => {
    for (const name of adminRouteNames) {
      const resolved = router.resolve({ name })
      assert.equal(
        resolved.meta.isAdminRequired,
        true,
        `${name} does not inherit isAdminRequired, so the guard never fires for it`
      )
    }
  })

  it('still requires authentication on every page', () => {
    for (const name of adminRouteNames) {
      assert.equal(router.resolve({ name }).meta.isAuthRequired, true)
    }
  })

  it('puts every admin page under /admin', () => {
    for (const name of adminRouteNames) {
      assert.match(router.resolve({ name }).path, /^\/admin(\/|$)/)
    }
  })
})
