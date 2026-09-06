export const ROLE_USER = 'ROLE_USER'
export const ROLE_ADMIN = 'ROLE_ADMIN'
export const ROLE_TESTER = 'ROLE_TESTER'

/**
 * The role hierarchy from `config/packages/security.yaml`, as a map from a role to everything it
 * also grants. Pinned against the YAML by tests/Unit/Security/RoleHierarchyClientMirrorTest.php.
 *
 * The client needs its own copy because neither source of roles is expanded. `User::getRoles()`
 * returns the stored roles plus ROLE_USER and nothing else, and that is what lands both in the JWT
 * claim and in the /api/users/self payload, so anything the hierarchy would have added has to be
 * added here instead.
 *
 * ROLE_TESTER is absent on purpose, and that absence is the point: it is a feature flag rather than
 * a rank, so it grants nothing and nothing grants it. Its predecessor ROLE_SUPER_ADMIN sat above
 * ROLE_ADMIN, which meant handing someone a preview also handed them the back office (#942).
 */
const GRANTS = Object.freeze({
  [ROLE_ADMIN]: [ROLE_USER]
})

/**
 * Every role a list of roles grants, following the hierarchy transitively, so a role two levels up
 * still reaches the bottom. A role absent from the map, like ROLE_TESTER, grants exactly itself.
 */
function expand(roles) {
  const granted = new Set()
  const pending = [...(Array.isArray(roles) ? roles : [])]

  while (pending.length > 0) {
    const role = pending.pop()
    if (typeof role !== 'string' || granted.has(role)) {
      continue
    }
    granted.add(role)
    pending.push(...(GRANTS[role] ?? []))
  }

  return granted
}

/** Whether this role list reaches `role` through the hierarchy. */
export function grantsRole(roles, role) {
  return expand(roles).has(role)
}

export function grantsAdmin(roles) {
  return grantsRole(roles, ROLE_ADMIN)
}

/**
 * Whether this account sees modules that are merged but not yet announced. Presentation only: the
 * APIs behind those modules stay open, so this draws a curtain rather than enforcing anything.
 */
export function grantsTester(roles) {
  return grantsRole(roles, ROLE_TESTER)
}
