/**
 * Whether a navigation should leave the page where it is. Filters write themselves to the query
 * string with `router.replace`, and each of those used to scroll the visitor back to the top of the
 * results. A change of page number is the exception: new results start at the top.
 */
export function keepsScrollPosition(to, from) {
  return to.path === from.path && to.hash === from.hash && to.query.page === from.query.page
}
