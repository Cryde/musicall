import { ROLE_ADMIN, ROLE_TESTER } from '../utils/roles.js'

/**
 * Mirrors `AdminUserRoleUpdate::GRANTABLE_ROLES`, pinned by
 * tests/Unit/Security/AdminUserRolesClientMirrorTest.php. Anything else is a 422, so a role added
 * here without its PHP counterpart is a dead toggle.
 *
 * The order is deliberate: the harmless one first. ROLE_TESTER grants no permission at all, it only
 * reveals modules that are merged but not yet announced. ROLE_ADMIN hands over the whole back
 * office, which is why it is the one that asks before it applies.
 */
export const GRANTABLE_ROLES = Object.freeze([
  {
    value: ROLE_TESTER,
    label: 'Testeur',
    description:
      "Voit les modules terminés mais pas encore annoncés. N'accorde aucune permission supplémentaire.",
    requiresConfirmation: false
  },
  {
    value: ROLE_ADMIN,
    label: 'Administrateur',
    description:
      "Accès complet à l'administration : modération, utilisateurs, retours et publications.",
    requiresConfirmation: true
  }
])

/** Mirrors `AdminUser::SEARCH_MIN_LENGTH`, the Assert\Length on the search query parameter. */
export const USER_SEARCH_MIN_LENGTH = 2

/** Mirrors `AdminUser::ITEMS_PER_PAGE`, so the paginator agrees with what the API returns. */
export const USER_SEARCH_ROWS_PER_PAGE = 20
