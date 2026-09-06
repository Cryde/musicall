export const ROLE_USER = 'ROLE_USER'
export const ROLE_ADMIN = 'ROLE_ADMIN'
export const ROLE_SUPER_ADMIN = 'ROLE_SUPER_ADMIN'

/**
 * The role hierarchy from `config/packages/security.yaml`, as a map from a role to everything it
 * also grants. Pinned against the YAML by tests/Unit/Security/RoleHierarchyClientMirrorTest.php.
 *
 * The client needs its own copy because neither source of roles is expanded. `User::getRoles()`
 * returns the stored roles plus ROLE_USER and nothing else, and that is what lands both in the JWT
 * claim and in the /api/users/self payload. So a super admin, stored as ["ROLE_SUPER_ADMIN"],
 * arrives carrying no ROLE_ADMIN at all: an `includes('ROLE_ADMIN')` check answers false for the
 * most privileged account on the site.
 */
const GRANTS = Object.freeze({
  [ROLE_ADMIN]: [ROLE_USER],
  [ROLE_SUPER_ADMIN]: [ROLE_ADMIN]
})

/**
 * Every role a list of roles grants, following the hierarchy transitively, so ROLE_SUPER_ADMIN
 * reaches ROLE_USER through ROLE_ADMIN.
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

export function grantsSuperAdmin(roles) {
  return grantsRole(roles, ROLE_SUPER_ADMIN)
}
