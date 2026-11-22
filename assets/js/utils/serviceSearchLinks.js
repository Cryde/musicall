/**
 * The kinds whose search the menus link to (#1410, #1411), in their order, as `[kind, config]`: a search not yet
 * announced (`testerOnly`) shows to ROLE_TESTER accounts only.
 */
export function visibleServiceSearches(kinds, isTester) {
  return Object.entries(kinds).filter(([, kind]) => !kind.search.testerOnly || isTester)
}
