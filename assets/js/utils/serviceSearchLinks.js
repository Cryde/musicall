/**
 * The kinds whose search the viewer may see (#1410, #1411), in their order, as `[kind, config]`: a search not yet
 * announced (`testerOnly`) shows to ROLE_TESTER accounts only, in the menus as in the annuaire (#1414).
 */
export function visibleServiceSearches(kinds, isTester) {
  return Object.entries(kinds).filter(([, kind]) => !kind.search.testerOnly || isTester)
}

/**
 * What the menus offer after « Musiciens », as `{ key, menuKey, icon, route }`: the annuaire alone once it shows
 * (#1414), since it leads to every kind's search; until then, each visible kind's search.
 */
export function serviceMenuEntries(kinds, directory, isTester) {
  if (!directory.testerOnly || isTester) {
    return [
      { key: 'directory', menuKey: directory.menuKey, icon: directory.icon, route: directory.route }
    ]
  }

  return visibleServiceSearches(kinds, isTester).map(([key, kind]) => ({
    key,
    menuKey: kind.search.menuKey,
    icon: kind.icon,
    route: kind.search.route
  }))
}
