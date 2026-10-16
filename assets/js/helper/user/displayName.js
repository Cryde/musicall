import { i18n } from '../../i18n/index.js'

/** What the site calls a closed account, in the active language. */
export function deletedDisplayName() {
  return i18n.global.t('shared.user.deleted')
}

/** The name the site shows for a user: their profile name when the API sends one (#1118). */
export function displayName(user) {
  if (user.deletion_datetime) {
    return deletedDisplayName()
  }
  return user.display_name || user.username
}
