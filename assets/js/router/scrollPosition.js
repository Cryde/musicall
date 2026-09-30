/**
 * Whether a navigation should leave the page where it is. Filters write themselves to the query
 * string with `router.replace`, and each of those used to scroll the visitor back to the top of the
 * results. A change of page number is the exception: new results start at the top.
 */
export function keepsScrollPosition(to, from) {
  return to.path === from.path && to.hash === from.hash && to.query.page === from.query.page
}

// Set by scrollBehavior on every navigation: whether it put the visitor back where they had scrolled.
let restoredSavedPosition = false

export function rememberScrollRestore(savedPosition) {
  restoredSavedPosition = savedPosition != null
}

/**
 * True when the current page was reached with Back or Forward and the router restored its scroll. A
 * page that scrolls itself once its data has loaded checks this first, or it would undo the restore.
 */
export function lastNavigationRestoredScroll() {
  return restoredSavedPosition
}
