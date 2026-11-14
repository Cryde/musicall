import { START_LOCATION } from 'vue-router'

/**
 * A tab keeps running the front end it was loaded with, whatever was deployed since (#1105). Each
 * build names itself, the server names the deployed one on its responses and in a static
 * version.json, and a tab that learns it is behind loads the next page in full rather than
 * switching to it in place.
 */

/** The response header naming the deployed build, as axios spells it. */
export const VERSION_HEADER = 'x-app-version'

/** At most one version.json request per minute, however often the tab comes back or navigates. */
export const VERSION_CHECK_INTERVAL_MS = 60_000

/** Whether `deployed` names a build other than the running one. An empty or missing value says nothing. */
export function isOtherVersion(deployed, running) {
  return typeof deployed === 'string' && deployed !== '' && deployed !== running
}

/**
 * Remembers whether a newer build was deployed, and says so once. A rollback counts as well: what
 * the server serves is what the tab should run.
 *
 * @param {{ running: string, onOutdated: () => void }} options
 */
export function createVersionWatch({ running, onOutdated }) {
  let isOutdated = false

  return {
    observe(deployed) {
      if (isOutdated || !isOtherVersion(deployed, running)) return
      isOutdated = true
      onOutdated()
    },
    isOutdated: () => isOutdated
  }
}

/**
 * Runs `action` unless it ran less than `intervalMs` ago. Returns whether it ran.
 *
 * @param {() => void} action
 * @param {{ now: () => number, intervalMs: number }} options
 */
export function throttle(action, { now, intervalMs }) {
  let lastRun = null

  return () => {
    if (lastRun !== null && now() - lastRun < intervalMs) return false
    lastRun = now()
    action()
    return true
  }
}

/**
 * The version a version.json response names, or null for anything else: a failed request is not
 * news about a deploy.
 *
 * @param {(url: string, init: RequestInit) => Promise<Response>} fetchVersion
 */
export async function fetchDeployedVersion(fetchVersion, url) {
  try {
    // The file must come from the release being served, whatever the server says about caching it.
    // no-store also sends `Cache-Control: no-cache`, so a proxy revalidates too.
    const response = await fetchVersion(url, { cache: 'no-store' })
    if (!response.ok) return null
    const { version } = await response.json()
    return typeof version === 'string' ? version : null
  } catch {
    return null
  }
}

/**
 * Whether a navigation, once every guard let it through, should load its target in full so that
 * the page comes from the deployed build. Only when a newer one is known, never on the page load's
 * own first navigation, and only to another page, so a filter or a tab change in the URL stays in
 * place. `isReloadAsked` is the « Recharger » toast, which navigates to the page already shown.
 *
 * @param {{ isOutdated: boolean, isFirstNavigation: boolean, fromPath: string, toPath: string, isReloadAsked: boolean }} navigation
 */
export function shouldLoadInFull({
  isOutdated,
  isFirstNavigation,
  fromPath,
  toPath,
  isReloadAsked
}) {
  if (!isOutdated || isFirstNavigation) return false

  return isReloadAsked || fromPath !== toPath
}

/**
 * Makes the router load pages in full while the tab runs an outdated build. The check sits in a
 * beforeResolve guard, which runs only once every leave and update guard has let the navigation
 * through, so a page protecting unsaved edits keeps the last word: a navigation it refuses, or one
 * a newer navigation cancels, never reaches `assign`.
 *
 * `reload` is the « Recharger » toast: a forced navigation to the page shown, so that its update
 * guards run before it loads again.
 *
 * @param {import('vue-router').Router} router
 * @param {{ isOutdated: () => boolean, assign: (url: string) => void }} options
 */
export function loadOutdatedPagesInFull(router, { isOutdated, assign }) {
  let isReloadAsked = false

  const removeGuard = router.beforeResolve((to, from) => {
    const isFull = shouldLoadInFull({
      isOutdated: isOutdated(),
      isFirstNavigation: from === START_LOCATION,
      fromPath: from.path,
      toPath: to.path,
      isReloadAsked
    })
    if (!isFull) return
    assign(to.fullPath)
    // The page is loading again, so the in-place switch is not wanted.
    return false
  })

  async function reload() {
    const { path, query, hash } = router.currentRoute.value
    isReloadAsked = true
    try {
      await router.replace({ path, query, hash, force: true })
    } finally {
      isReloadAsked = false
    }
  }

  return { reload, remove: removeGuard }
}
